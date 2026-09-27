<?php

namespace App\Services\LegacyImport\Importers;

use App\Enums\RecordPermission;
use App\Services\LegacyImport\Importer;
use App\Services\LegacyImport\LegacyValue;
use Epesi\Modules\CRM\Companies\Models\Company;

/**
 * Legacy `company` recordset (CRM_ContactsInstall::install()'s company
 * fields) -> Epesi\Modules\CRM\Companies\Models\Company. Field list read directly from the live
 * `company_field` table, matching what Phase 1 built Company's schema from.
 */
class CompaniesImporter extends Importer
{
    public function legacyTab(): string
    {
        return 'company';
    }

    public function modelClass(): string
    {
        return Company::class;
    }

    public function logName(): string
    {
        return 'company';
    }

    protected function uniqueColumns(): array
    {
        return ['email'];
    }

    protected function trackedFields(): array
    {
        return [
            'company_name' => 'company_name',
            'short_name' => 'short_name',
            'phone' => 'phone',
            'fax' => 'fax',
            'email' => 'email',
            'web_address' => 'web_address',
            'memo' => 'memo',
            'group' => 'groups',
            'permission' => 'permission',
            'address_1' => 'address_1',
            'address_2' => 'address_2',
            'city' => 'city',
            'country' => 'country',
            'zone' => 'zone',
            'postal_code' => 'postal_code',
            'tax_id' => 'tax_id',
        ];
    }

    protected function decodeTrackedValue(string $legacyField, ?string $raw): array
    {
        $column = $this->trackedFields()[$legacyField];

        return match ($legacyField) {
            'group' => [$column => $this->commonDataKeys(LegacyValue::multi($raw), 'Companies_Groups')],
            'permission' => [$column => $raw !== null && $raw !== '' ? (int) $raw : RecordPermission::Public->value],
            'email' => [$column => $raw !== '' ? $raw : null],
            default => [$column => $raw !== '' ? $raw : null],
        };
    }
}
