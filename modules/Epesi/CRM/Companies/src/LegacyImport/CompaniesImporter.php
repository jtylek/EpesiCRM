<?php

namespace Epesi\Modules\CRM\Companies\LegacyImport;

use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\MigrationWizard\Import\CertifiableImporter;

final class CompaniesImporter implements CertifiableImporter
{
    public function id(): string
    {
        return 'companies';
    }

    public function dependencies(): array
    {
        return ['users'];
    }

    public function sourceTables(): array
    {
        return ['company_data_1'];
    }

    public function sourceTable(): string
    {
        return $this->sourceTables()[0];
    }

    public function sourceFileAreas(): array
    {
        return [];
    }

    public function sourceIdColumn(): string
    {
        return 'id';
    }

    public function fieldMapping(): array
    {
        return ['id' => 'legacy_id', 'f_company_name' => 'company_name'];
    }

    public function destinationModels(): array
    {
        return [Company::class];
    }

    public function verificationRules(): array
    {
        return ['legacy_company_ids_unique', 'company_count_reconciles'];
    }

    public function deferredMappings(): array
    {
        return [];
    }

    public function legacyModule(): string
    {
        return 'CRM_Contacts';
    }

    /** The mapped source columns, nothing else: the remaining columns are not imported yet, so the table is not "supported". */
    public function requiredColumns(): array
    {
        return ['company_data_1' => array_keys($this->fieldMapping())];
    }

    public function ignoredColumns(): array
    {
        return [];
    }

    /** Not certified for any legacy release yet. */
    public function certifiedFor(): array
    {
        return [];
    }
}
