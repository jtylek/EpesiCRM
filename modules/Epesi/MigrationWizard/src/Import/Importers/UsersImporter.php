<?php

namespace Epesi\Modules\MigrationWizard\Import\Importers;

use App\Models\User;
use Epesi\Modules\MigrationWizard\Import\CertifiableImporter;

final class UsersImporter implements CertifiableImporter
{
    public function id(): string
    {
        return 'users';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function sourceTables(): array
    {
        return ['user_login'];
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
        return ['id' => 'legacy_id', 'login' => 'name'];
    }

    public function destinationModels(): array
    {
        return [User::class];
    }

    public function verificationRules(): array
    {
        return ['legacy_user_ids_unique', 'user_count_reconciles'];
    }

    public function deferredMappings(): array
    {
        return ['contact_access_roles'];
    }

    public function legacyModule(): string
    {
        return 'Base_User';
    }

    /** The mapped source columns, nothing else: the remaining columns are not imported yet, so the table is not "supported". */
    public function requiredColumns(): array
    {
        return ['user_login' => array_keys($this->fieldMapping())];
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
