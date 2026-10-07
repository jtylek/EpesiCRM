<?php

namespace Epesi\Modules\Currencies\LegacyImport;

use App\Services\LegacyImport\ImportSummary;
use Epesi\Modules\Currencies\Models\Currency;
use Epesi\Modules\Currencies\Services\CurrencyRepository;
use Illuminate\Support\Facades\DB;

/**
 * The legacy `utils_currency` list, registered as the `currencies` tab of
 * `import:legacy`. Matched on the ISO code, so the seeded USD/EUR/GBP/PLN merge
 * with the legacy rows instead of colliding.
 *
 * The legacy default currency becomes the home currency for every date: legacy
 * only ever knew its current value.
 *
 * The legacy rate cache is not imported. It holds every pair it derived; the
 * rates the provider actually published are fetched again from the backfill
 * start (Administration → Exchange Rates).
 */
class CurrenciesImporter
{
    public function run(bool $withHistory = true): ImportSummary
    {
        $summary = new ImportSummary;
        $legacy = DB::connection('legacy');

        if (! $legacy->getSchemaBuilder()->hasTable('utils_currency')) {
            $summary->warn('No utils_currency table in the legacy database.');

            return $summary;
        }

        $default = null;
        $position = (int) Currency::query()->max('position');

        foreach ($legacy->table('utils_currency')->orderBy('id')->get() as $row) {
            $code = strtoupper(trim((string) $row->code));

            if (! preg_match('/^[A-Z]{3}$/', $code)) {
                $summary->warn("Skipped legacy currency {$row->id} (\"{$row->code}\"): not an ISO 4217 code.");

                continue;
            }

            $currency = Currency::query()->firstOrNew(['code' => $code]);
            $currency->exists ? $summary->updated++ : $summary->created++;

            $currency->fill([
                'name' => $currency->name ?? Currency::iso()[$code][0] ?? $code,
                'decimals' => (int) ($row->decimals ?? Currency::iso()[$code][1] ?? 2),
                'active' => (bool) $row->active,
                'position' => $currency->exists ? $currency->position : ++$position,
            ])->save();

            if ((int) $row->default_currency === 1) {
                $default = $code;
            }
        }

        if ($default !== null) {
            // The home currency must be active, whatever legacy said.
            Currency::query()->where('code', $default)->update(['active' => true]);
            app(CurrencyRepository::class)->setHome($default);
        }

        return $summary;
    }
}
