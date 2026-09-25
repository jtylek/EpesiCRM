<?php

namespace App\Console\Commands;

use App\Services\LegacyImport\ImporterRegistry;
use App\Services\LegacyImport\Importers\CompaniesImporter;
use App\Services\LegacyImport\Importers\ContactsImporter;
use App\Services\LegacyImport\Importers\MeetingsImporter;
use App\Services\LegacyImport\Importers\PhoneCallsImporter;
use App\Services\LegacyImport\Importers\TasksImporter;
use App\Services\LegacyImport\Importers\UsersImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time cutover from the legacy Epesi installation (see the "legacy" DB
 * connection in config/database.php) into this app's own tables, including
 * edit-history replay into activity_log. CLI rather than a Filament UI,
 * deliberately: this needs --dry-run and per-tab console output to debug
 * FK/history mismatches during a phased cutover, not a progress bar.
 *
 * Idempotent: every importer upserts by `legacy_id` — except commondata, which
 * upserts by path and explains why in CommonDataImporter — so re-running (e.g.
 * after fixing a mapping bug) updates existing rows rather than duplicating
 * them. Order matters — users/companies must exist before contacts can
 * resolve their user_id/company_id, and contacts before anything that
 * references them as Employees/Customers.
 *
 * Refuses to run against a database that already has non-legacy rows (no
 * `legacy_id`) in any migrated table — the first real import landed on top
 * of DatabaseSeeder's demo fixtures and every id was off by the fixture
 * count, confusing to eyeball against the legacy source.
 * `migrate:fresh --seed` (which reintroduces those fixtures) is the specific
 * trap to avoid before running this — use the reset recipe in
 * assertDatabaseIsClean()'s error output instead.
 */
class ImportLegacyData extends Command
{
    protected $signature = 'import:legacy
        {tab=all : users|companies|contacts|phonecalls|tasks|meetings, a module-registered tab (e.g. commondata, mail), or all}
        {--dry-run : roll back every write at the end and only print summaries}
        {--no-history : skip edit-history replay into activity_log}';

    protected $description = 'Import records and edit history from the legacy Epesi database';

    /** @var array<string, class-string> in required dependency order */
    private const IMPORTERS = [
        'users' => UsersImporter::class,
        'companies' => CompaniesImporter::class,
        'contacts' => ContactsImporter::class,
        'phonecalls' => PhoneCallsImporter::class,
        'tasks' => TasksImporter::class,
        'meetings' => MeetingsImporter::class,
    ];

    /** @var list<string> every table add_legacy_id_to_migrated_tables added legacy_id to */
    private const MIGRATED_TABLES = ['users', 'companies', 'contacts', 'phone_calls', 'tasks', 'meetings'];

    public function handle(): int
    {
        $tab = $this->argument('tab');

        $importers = $this->importers();

        if ($tab !== 'all' && ! isset($importers[$tab])) {
            $this->error("Unknown tab \"{$tab}\". Choose one of: ".implode(', ', array_keys($importers)).', all.');

            return self::FAILURE;
        }

        $tabs = $tab === 'all' ? array_keys($importers) : [$tab];

        // Only the core tabs below carry a legacy_id and depend on legacy ids
        // lining up with local ones, so only they are worth refusing over. A
        // module's tab writes none of MIGRATED_TABLES, and importing reference
        // data into a working database is a normal thing to want.
        if (array_intersect($tabs, array_keys(self::IMPORTERS)) !== [] && $dirtyTables = $this->dirtyTables()) {
            $this->error('Refusing to import: '.implode(', ', $dirtyTables).' already have rows with no legacy_id (non-legacy fixtures) — importing on top of them would misalign every legacy_id from its matching id.');
            $this->error('Reset first, then re-import:');
            $this->line('  php artisan migrate:fresh --force');
            $this->line('  php artisan db:seed --class=RoleSeeder --force');
            $this->line('  php artisan import:legacy all');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $withHistory = ! $this->option('no-history');

        if ($dryRun) {
            $this->warn('Dry run: every write below will be rolled back at the end.');
            DB::beginTransaction();
        }

        // Every imported model uses Spatie's LogsActivity, which logs its
        // own "created"/"updated" event on every save() regardless of what
        // triggered it. Left enabled, each imported row would get a second,
        // spurious activity_log row (null causer, import-time timestamp)
        // alongside Importer::replayHistory()'s correctly-dated synthesized
        // one — this command's activity_log rows are meant to be entirely
        // the reconstructed legacy history, not a mix of that and Spatie's
        // own live-editing log.
        activity()->disableLogging();

        try {
            foreach ($tabs as $t) {
                $this->info("Importing {$t}...");
                // Resolved through the container: a module's importer may
                // take constructor dependencies (MailImporter needs the archiver).
                $importer = app($importers[$t]);
                $summary = $t === 'users' ? $importer->run() : $importer->run($withHistory);

                $this->line("  created {$summary->created}, updated {$summary->updated}, history rows {$summary->historyRows}");
                foreach ($summary->warnings as $warning) {
                    $this->warn("  ! {$warning}");
                }
            }
        } catch (\Throwable $e) {
            if ($dryRun) {
                DB::rollBack();
            }

            throw $e;
        }

        if ($dryRun) {
            DB::rollBack();
            $this->warn('Dry run complete: all writes rolled back.');
        } else {
            $this->info('Import complete.');
        }

        return self::SUCCESS;
    }

    /**
     * Core tabs plus whatever the installed modules registered — see
     * ImporterRegistry for why a module's importer cannot simply be named in
     * the list above.
     *
     * @return array<string, class-string> tab => importer, in run order
     */
    private function importers(): array
    {
        $registry = $this->laravel->make(ImporterRegistry::class);

        return [...$registry->before(), ...self::IMPORTERS, ...$registry->after()];
    }

    /** @return list<string> migrated tables that already have a non-legacy (legacy_id IS NULL) row */
    private function dirtyTables(): array
    {
        return collect(self::MIGRATED_TABLES)
            ->filter(fn (string $table) => DB::table($table)->whereNull('legacy_id')->exists())
            ->values()
            ->all();
    }
}
