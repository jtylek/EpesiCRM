<?php

namespace Epesi\Modules\CRM\Contacts\LegacyImport;

use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\MigrationWizard\Import\CertifiableImporter;

final class ContactsImporter implements CertifiableImporter
{
    public function id(): string
    {
        return 'contacts';
    }

    public function dependencies(): array
    {
        return ['users', 'companies'];
    }

    public function sourceTables(): array
    {
        return ['contact_data_1'];
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
        return [
            'id' => 'legacy_id',
            'f_first_name' => 'first_name',
            'f_last_name' => 'last_name',
        ];
    }

    public function destinationModels(): array
    {
        return [Contact::class];
    }

    public function verificationRules(): array
    {
        return ['legacy_contact_ids_unique', 'contact_count_reconciles'];
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
        return ['contact_data_1' => array_keys($this->fieldMapping())];
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
