<?php

namespace Epesi\Modules\Currencies\Services;

use Carbon\CarbonInterface;
use Epesi\Modules\Currencies\Models\Currency;
use Epesi\Modules\Currencies\Models\CurrencyHomePeriod;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use InvalidArgumentException;

/**
 * What other modules ask about currencies: which are in use, how many decimals
 * an amount in one has, and which was the home currency on a given date.
 */
class CurrencyRepository
{
    /** Before anything is set up (or with the table missing mid-install). */
    public const FALLBACK_HOME = 'USD';

    /** @var array<string, string> date => home code, for this request */
    protected array $homeByDate = [];

    /** @var array<string, int> code => minor units, for this request */
    protected array $decimalsByCode = [];

    /** @return array<string, string> code => name, active ones in display order */
    public function active(): array
    {
        return Currency::query()->active()->orderBy('position')->orderBy('code')->pluck('name', 'code')->all();
    }

    /** @return array<string, string> code => "CODE (Name)", for a select */
    public function options(bool $activeOnly = true): array
    {
        return Currency::query()
            ->when($activeOnly, fn ($query) => $query->active())
            ->orderBy('position')
            ->orderBy('code')
            ->get(['code', 'name'])
            ->mapWithKeys(fn (Currency $currency): array => [$currency->code => $currency->code.' ('.__($currency->name).')'])
            ->all();
    }

    public function isActive(string $code): bool
    {
        return Currency::query()->active()->where('code', strtoupper($code))->exists();
    }

    /** Minor units of an amount in $code: this install's setting, then ISO 4217, then 2. */
    public function decimals(string $code): int
    {
        $code = strtoupper($code);

        return $this->decimalsByCode[$code] ??= Currency::query()->where('code', $code)->value('decimals') ?? Currency::iso()[$code][1] ?? 2;
    }

    /**
     * The currency a new amount starts in: the signed-in user's own choice
     * (Regional Settings), else the system default there, else the home
     * currency. One that has since been deactivated is skipped.
     */
    public function defaultCode(): string
    {
        $chosen = rescue(fn (): ?string => class_exists(RegionalSetting::class)
            ? (RegionalSetting::effective()->currency ?: RegionalSetting::defaults()->currency)
            : null, null, report: false);

        return filled($chosen) && $this->isActive($chosen) ? strtoupper($chosen) : $this->home();
    }

    /**
     * "1,234.50 PLN" in the app's language: the locale's separators and
     * symbol through intl when it is there, the number and the code otherwise.
     * Rounded to the currency's minor units unless $decimals says otherwise.
     */
    public function format(float|int|string|null $amount, ?string $code, ?int $decimals = null): ?string
    {
        if ($amount === null || $amount === '' || ! is_numeric($amount)) {
            return null;
        }

        $code = filled($code) ? strtoupper((string) $code) : null;
        $decimals ??= $code === null ? 2 : $this->decimals($code);

        if ($code !== null && extension_loaded('intl')) {
            $formatted = rescue(fn () => Number::currency((float) $amount, $code, app()->getLocale(), $decimals), null, report: false);

            if (is_string($formatted) && $formatted !== '') {
                return $formatted;
            }
        }

        return number_format((float) $amount, $decimals).($code === null ? '' : ' '.$code);
    }

    /**
     * The home (functional) currency on $date, today when null. A dated fact:
     * a document must be read against the home currency of its own date, not
     * today's, or moving the company to another country restates history.
     */
    public function home(CarbonInterface|string|null $date = null): string
    {
        $date = Carbon::parse($date ?? now())->toDateString();

        return $this->homeByDate[$date] ??= rescue(
            fn () => CurrencyHomePeriod::query()->whereDate('effective_from', '<=', $date)->orderByDesc('effective_from')->value('currency_code')
                ?? CurrencyHomePeriod::query()->orderBy('effective_from')->value('currency_code'),
            null,
            report: false,
        ) ?? self::FALLBACK_HOME;
    }

    /**
     * Makes $code the home currency from $from on, or for every date when
     * $from is null (an install that has no history yet, or a correction).
     */
    public function setHome(string $code, CarbonInterface|string|null $from = null): void
    {
        $code = strtoupper($code);

        if (! $this->isActive($code)) {
            throw new InvalidArgumentException("{$code} is not an active currency.");
        }

        DB::transaction(function () use ($code, $from): void {
            if ($from === null) {
                CurrencyHomePeriod::query()->delete();
                CurrencyHomePeriod::query()->create(['currency_code' => $code, 'effective_from' => '1970-01-01']);

                return;
            }

            CurrencyHomePeriod::query()->updateOrCreate(
                ['effective_from' => Carbon::parse($from)->toDateString()],
                ['currency_code' => $code],
            );
        });

        $this->homeByDate = [];
    }

    /** Whether $code is the home currency in any period, today's or a past one. */
    public function isHomeInAnyPeriod(string $code): bool
    {
        return CurrencyHomePeriod::query()->where('currency_code', strtoupper($code))->exists();
    }
}
