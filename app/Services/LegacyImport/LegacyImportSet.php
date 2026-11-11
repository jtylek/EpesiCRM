<?php

namespace App\Services\LegacyImport;

use App\Console\Commands\ImportLegacyData;
use Illuminate\Support\Facades\DB;

/**
 * Which importers `import:legacy` runs, in which order, and the clean-target
 * check that guards the `legacy_id` upserts. Shared by the command and the
 * Migration Wizard so that both run exactly the same importers with exactly the
 * same safeguards (nothing is re-implemented in the wizard).
 */
class LegacyImportSet
{
    /** @var list<string> every table add_legacy_id_to_migrated_tables added legacy_id to */
    public const MIGRATED_TABLES = ['users', 'companies', 'contacts', 'phone_calls', 'tasks', 'meetings'];

    public function __construct(private readonly ImporterRegistry $registry) {}

    /**
     * Core tabs plus whatever the installed modules registered — see
     * ImporterRegistry for why a module's importer cannot simply be named in
     * the command.
     *
     * @return array<string, class-string> tab => importer, in run order
     */
    public function importers(): array
    {
        return [...$this->registry->before(), ...ImportLegacyData::IMPORTERS, ...$this->registry->after(), ...$this->registry->last()];
    }

    /** @return list<string> migrated tables that already have a non-legacy (legacy_id IS NULL) row */
    public function dirtyTables(): array
    {
        return collect(self::MIGRATED_TABLES)
            ->filter(fn (string $table) => DB::table($table)->whereNull('legacy_id')->exists())
            ->values()
            ->all();
    }
}
