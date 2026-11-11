<?php

namespace Epesi\Modules\TaxRates\LegacyImport;

use App\Services\LegacyImport\Importer;
use App\Services\LegacyImport\ImportSummary;
use Epesi\Modules\TaxRates\Models\TaxRate;
use Illuminate\Support\Facades\DB;

/** Legacy Data/TaxRates (`data_tax_rates`): the rates invoices, expenses and items point at. */
class TaxRatesImporter extends Importer
{
    public function legacyTab(): string
    {
        return 'data_tax_rates';
    }

    public function modelClass(): string
    {
        return TaxRate::class;
    }

    public function logName(): string
    {
        return 'tax_rate';
    }

    public function run(bool $withHistory = true): ImportSummary
    {
        if (! DB::connection('legacy')->getSchemaBuilder()->hasTable('data_tax_rates_data_1')) {
            $summary = new ImportSummary;
            $summary->warn('Legacy tax rates were not found.');

            return $summary;
        }

        return parent::run($withHistory);
    }

    protected function trackedFields(): array
    {
        return ['name' => 'name', 'percentage' => 'rate'];
    }

    protected function decodeTrackedValue(string $legacyField, ?string $raw): array
    {
        return $legacyField === 'name' ? ['name' => html_entity_decode((string) $raw, ENT_QUOTES | ENT_HTML5)] : ['rate' => (string) ((float) $raw)];
    }

    protected function extraAttributes(object $row): array
    {
        return ['active' => true, 'code' => 'LEGACY-'.$row->id, 'tax_type' => 'standard'];
    }
}
