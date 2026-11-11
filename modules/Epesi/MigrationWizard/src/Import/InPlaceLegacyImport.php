<?php

namespace Epesi\Modules\MigrationWizard\Import;

use App\Services\LegacyImport\Importer;
use App\Services\LegacyImport\ImportSummary;
use App\Services\LegacyImport\LegacyImportSet;
use App\Services\LegacyImport\TimeBudget;
use App\Services\LegacyImport\TimeBudgetReached;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Runs the app's own `import:legacy` importers (App\Services\LegacyImport) in
 * place, against the legacy tables the wizard renamed to `legacy_<name>` inside
 * the Epesi 3 database. Nothing is re-implemented: the importers, their order and
 * the clean-target check come from LegacyImportSet, and every importer keeps its
 * `legacy_id` upsert, so a rerun updates rows instead of duplicating them.
 *
 * Not a bypass of DisposableTargetGuard: that guard protects the wizard's own
 * harness importers (ImportExecutor), which never run here.
 *
 * Fail closed:
 *  - the `legacy` connection is rebuilt from the default one with the
 *    `legacy_` prefix, and refused unless it points at the very same database;
 *  - the target must be clean (no migrated-table row without a legacy_id). The
 *    wizard never imports alongside hand-entered rows (no --keep-existing);
 *  - the importers that bring business files over (mail, attachments) run only
 *    through a moving file transfer with a file ledger (LivePhases), never copying;
 *    without one they are left out (see withoutFileModules()).
 */
final class InPlaceLegacyImport
{
    public const PREFIX = 'legacy_';

    /** Legacy modules whose tables the always-run importers read; each must match its approved shape. */
    public const REQUIRED_MODULES = ['Base_User', 'CRM_Contacts'];

    /**
     * Which legacy modules an importer's tables belong to. An importer listed here
     * runs only when every one of its modules is approved (ModuleCoverage:
     * schema_approved); otherwise its data stays preserved and, for a module that
     * owns business files, verification blocks. Importers not listed run always.
     *
     * @var array<string, list<string>>
     */
    public const MODULES = [
        'users' => ['Base_User'],
        'companies' => ['CRM_Contacts'],
        'contacts' => ['CRM_Contacts'],
        'mail' => ['CRM_Mail'],
        'attachments' => ['Utils_Attachment', 'Utils_FileStorage'],
    ];

    /**
     * Every importer tab of the in-place run and the legacy modules whose data it reads, for the assessment
     * ("Can be migrated"). Unlike MODULES this gates nothing: an importer not in MODULES runs whatever the
     * shape of its tables. A tab missing here is simply not listed under a module.
     *
     * @var array<string, list<string>>
     */
    public const LEGACY_MODULES = [
        'users' => ['Base_User', 'Base_User_Login'],
        'loginaudit' => ['CRM_LoginAudit'],
        'companies' => ['CRM_Contacts'],
        // Premium_MultipleAddresses: its rows become Shipping addresses in the contacts import (LegacyMultipleAddresses).
        'contacts' => ['CRM_Contacts', 'Premium_MultipleAddresses'],
        'phonecalls' => ['CRM_PhoneCall'],
        'tasks' => ['CRM_Tasks'],
        'meetings' => ['CRM_Meeting'],
        'shoutbox' => ['Apps_Shoutbox'],
        'commondata' => ['Utils_CommonData'],
        'currencies' => ['Utils_CurrencyField'],
        'invoice_tax_rates' => ['Data_TaxRates'],
        'attachments' => ['Utils_Attachment', 'Utils_FileStorage'],
        'mail' => ['CRM_Mail'],
    ];

    /**
     * Importers the in-place run always leaves out, and why (none: mail and
     * attachments move their files through the file ledger now).
     *
     * @var array<string, string>
     */
    public const NOT_RUN = [];

    /** Importers that bring business files over; they need a file ledger and a moving transfer. */
    public const FILE_TABS = ['mail', 'attachments'];

    /** Legacy modules behind FILE_TABS: approved only when a file ledger and a moving transfer are in place. */
    public const FILE_MODULES = ['CRM_Mail', 'Utils_Attachment', 'Utils_FileStorage'];

    public function __construct(private readonly LegacyImportSet $set) {}

    /**
     * The legacy modules a set of completed importers migrated.
     *
     * @param  list<string>  $completed  importer tabs that finished
     * @return list<string>
     */
    public static function migratedModules(array $completed): array
    {
        $modules = [];
        foreach ($completed as $tab) {
            foreach (self::MODULES[$tab] ?? [] as $module) {
                $modules[$module] = true;
            }
        }

        return array_keys($modules);
    }

    /**
     * @param  list<string>  $approvedModules
     * @return list<string> the same, without the modules whose importers move business files
     */
    public static function withoutFileModules(array $approvedModules): array
    {
        return array_values(array_diff($approvedModules, self::FILE_MODULES));
    }

    /**
     * Which legacy modules are migrated by importers that exist: legacy module => the importer tabs that
     * read its tables, for every module all of whose importers are registered. The mail and attachment
     * importers move business files and run only with a file ledger and a moving transfer, which the wizard
     * always sets up (withoutFileModules() applies when it does not).
     *
     * @param  array<string, mixed>  $registered  tab => importer, as LegacyImportSet::importers()
     * @return array<string, list<string>>
     */
    public static function moduleImporters(array $registered): array
    {
        $byModule = [];
        foreach (self::LEGACY_MODULES as $tab => $modules) {
            foreach ($modules as $module) {
                $byModule[$module][] = $tab;
            }
        }
        $result = [];
        foreach ($byModule as $module => $tabs) {
            if (array_diff($tabs, array_keys($registered)) === []) {
                $result[$module] = $tabs;
            }
        }

        return $result;
    }

    /**
     * @param  list<string>|null  $approvedModules  legacy modules whose tables match their approved shape; null = no filter
     * @return array<string, class-string> tab => importer class, in run order
     */
    public function tabs(?array $approvedModules = null): array
    {
        $tabs = array_diff_key($this->set->importers(), self::NOT_RUN);
        if ($approvedModules === null) {
            return $tabs;
        }

        return array_filter($tabs, static fn (string $class, string $tab): bool => array_diff(self::MODULES[$tab] ?? [], $approvedModules) === [], ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @param  array<string, mixed>  $coverage  ModuleCoverage::report() output
     * @return list<string> the legacy modules whose own tables match the approved shape
     */
    public static function approvedModules(array $coverage): array
    {
        $approved = [];
        foreach ((array) ($coverage['modules'] ?? []) as $row) {
            if (is_array($row) && is_string($row['legacy_module'] ?? null) && ($row['schema_approved'] ?? false) === true) {
                $approved[] = $row['legacy_module'];
            }
        }

        return $approved;
    }

    /** Point the `legacy` connection at the renamed tables of this very database. */
    public function useRenamedTables(): void
    {
        $name = (string) config('database.default');
        $default = config('database.connections.'.$name);
        if (! is_array($default)) {
            throw new RuntimeException('The default database connection is not configured.');
        }
        config(['database.connections.legacy' => array_merge($default, ['prefix' => self::PREFIX])]);
        DB::purge('legacy');
        $this->assertConnection();
    }

    /** The checks that must hold before a single importer runs. */
    public function assertConnection(): void
    {
        $legacy = DB::connection('legacy');
        if ($legacy->getTablePrefix() !== self::PREFIX) {
            throw new RuntimeException('The legacy connection does not use the legacy_ table prefix; refusing to import.');
        }
        if ($legacy->getDatabaseName() !== DB::connection()->getDatabaseName()) {
            throw new RuntimeException('The legacy connection points at another database than Epesi 3; refusing to import.');
        }
        if (! Schema::connection('legacy')->hasTable('user_login')) {
            throw new RuntimeException('The renamed legacy user table (legacy_user_login) was not found; refusing to import.');
        }
        $dirty = $this->set->dirtyTables();
        if ($dirty !== []) {
            throw new RuntimeException('Refusing to import: '.implode(', ', $dirty).' already have rows with no legacy_id; the migration target must be clean.');
        }
    }

    /**
     * Run every importer not yet completed, in order. $record is called once per
     * importer right after it finished, so a resume skips it.
     *
     * @param  list<string>  $completed  tabs that already finished
     * @param  callable(string, array<string, int>): void  $record
     * @param  list<string>|null  $approvedModules  see tabs()
     * @return list<string> tabs run now
     */
    public function run(array $completed, callable $record, ?array $approvedModules = null): array
    {
        $this->assertConnection();
        @set_time_limit(0);
        $done = array_fill_keys($completed, true);
        $ran = [];
        // Imported models log their own created/updated events; the importers replay
        // the legacy history instead (as import:legacy does).
        activity()->disableLogging();
        Importer::$onProgress = ImportProgress::records(...);
        $finished = false;
        try {
            foreach ($this->tabs($approvedModules) as $tab => $class) {
                if (isset($done[$tab])) {
                    continue;
                }
                // A browser step has a time budget: stop between importers once it is used up (never before the
                // first one of the slice, or nothing would ever advance).
                if ($ran !== [] && app(TimeBudget::class)->exceeded()) {
                    throw new TimeBudgetReached;
                }
                ImportProgress::started($tab);
                $importer = app($class);
                /** @var ImportSummary $summary */
                $summary = $tab === 'users' ? $importer->run() : $importer->run(true);
                ImportWarnings::save($tab, $summary->warnings);
                $record($tab, [
                    'created' => $summary->created,
                    'updated' => $summary->updated,
                    'history_rows' => $summary->historyRows,
                    'warnings' => count($summary->warnings),
                ]);
                $ran[] = $tab;
            }
            $finished = true;
        } finally {
            Importer::$onProgress = null;
            // Kept between the slices of a browser run (a slice ends with TimeBudgetReached), so the page keeps the
            // last count it was shown; removed once the whole import is over.
            if ($finished) {
                ImportProgress::clear();
            }
            activity()->enableLogging();
        }

        return $ran;
    }
}
