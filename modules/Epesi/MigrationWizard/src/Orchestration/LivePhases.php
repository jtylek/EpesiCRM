<?php

namespace Epesi\Modules\MigrationWizard\Orchestration;

use App\Services\LegacyImport\LegacyFileTransfer;
use App\Services\LegacyImport\LegacyImportSet;
use App\Support\Modules\ModuleRegistry;
use Epesi\Modules\MigrationWizard\Admin\AdminCredentials;
use Epesi\Modules\MigrationWizard\Admin\AdministratorHandoff;
use Epesi\Modules\MigrationWizard\Cleanup\CleanupManifests;
use Epesi\Modules\MigrationWizard\Cleanup\CleanupPreview;
use Epesi\Modules\MigrationWizard\Cleanup\CleanupRefused;
use Epesi\Modules\MigrationWizard\Cleanup\LegacyCleanup;
use Epesi\Modules\MigrationWizard\Cleanup\LegacyFileClassifier;
use Epesi\Modules\MigrationWizard\Cleanup\LegacyModulesMover;
use Epesi\Modules\MigrationWizard\Database\LegacyModuleTables;
use Epesi\Modules\MigrationWizard\Detection\LegacyDatabaseConnection;
use Epesi\Modules\MigrationWizard\Detection\LegacyInstallation;
use Epesi\Modules\MigrationWizard\Files\FileLedger;
use Epesi\Modules\MigrationWizard\Files\LegacyAreaCheck;
use Epesi\Modules\MigrationWizard\Files\MovingLegacyFileTransfer;
use Epesi\Modules\MigrationWizard\Import\ApprovedModulesCache;
use Epesi\Modules\MigrationWizard\Import\ImportProgress;
use Epesi\Modules\MigrationWizard\Import\InPlaceLegacyImport;
use Epesi\Modules\MigrationWizard\Install\EpesiInstaller;
use Epesi\Modules\MigrationWizard\Verification\LeftBehindReport;
use Epesi\Modules\MigrationWizard\Verification\MigrationVerifier;
use Epesi\Modules\MigrationWizard\Verification\VerificationInputBuilder;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Real phase work for the CLI.
 *
 *  freeze    refuses, before anything is changed, unless the pre-upgrade backup is
 *            confirmed, the schema is an approved release, the Core modules the import needs match their approved 1.9.1
 *            shape (Premium/Custom/unknown tables do not matter) and the config's
 *            database is the one this process is connected to
 *  import    the app's own import:legacy importers, in place, against legacy_<name>
 *            (InPlaceLegacyImport); never the DisposableTargetGuard harness importers
 *  verify    builds the MigrationVerifier input from the legacy tables, the imported
 *            rows, the file ledger, the coverage report and the operator's choices
 *  cleanup   needs the shipped release/legacy manifests, the confirmed backup and the
 *            cleanup switch; fails closed (and today nothing ships the manifests)
 */
final class LivePhases implements MigrationPhases
{
    /** @var callable(string): array<string, mixed> */
    private $assessor;

    /** @var (callable(string): object)|null */
    private $disks;

    /**
     * @param  (callable(string): array<string, mixed>)|null  $assessor  config path => assessment (tests)
     * @param  (callable(string): object)|null  $disks  ledger disk name => disk
     * @param  string|null  $databaseName  the connected database, when it cannot be asked of the PDO (tests)
     */
    public function __construct(
        private readonly string $root,
        private readonly PDO $pdo,
        private readonly ?FileLedger $ledger = null,
        ?callable $disks = null,
        private readonly ?InPlaceLegacyImport $import = null,
        ?callable $assessor = null,
        // Cleanup always runs after verification; only tests switch it off to check the refusal path.
        private readonly bool $cleanupEnabled = true,
        private readonly ?string $databaseName = null,
    ) {
        $this->disks = $disks;
        $this->assessor = $assessor ?? static fn (string $configPath): array => app(LegacyInstallation::class)->assess($configPath);
    }

    public function freeze(array $state): void
    {
        if (! is_array($state['preupgrade_backup'] ?? null)) {
            throw new RuntimeException(MigrationRunner::BACKUP_REQUIRED);
        }
        $report = ($this->assessor)($this->root.'/data/config.php');
        if (($report['compatible'] ?? false) !== true) {
            throw new RuntimeException('The legacy installation is not an approved Epesi release (the version must be 1.9.1 in both the database and the files); nothing was changed.');
        }
        self::assertModulesApproved(is_array($report['coverage'] ?? null) ? $report['coverage'] : []);
        self::assertSameDatabase((string) ($report['database'] ?? ''), $this->databaseName ?? $this->connectedDatabase());
        LegacyDatabaseConnection::assertPersistable();
        $tabs = $this->engine()->tabs();
        foreach (['users', 'companies', 'contacts'] as $required) {
            if (! isset($tabs[$required])) {
                throw new RuntimeException("The '{$required}' importer is not available; nothing was changed.");
            }
        }
    }

    /**
     * The modules the always-run importers read (users, companies, contacts) must
     * match their approved shape: they cannot be skipped one by one, and the rest
     * of the import depends on them. A module that differs is named with its tables.
     *
     * @param  array<string, mixed>  $coverage  ModuleCoverage::report() output
     */
    public static function assertModulesApproved(array $coverage): void
    {
        $rows = [];
        foreach ((array) ($coverage['modules'] ?? []) as $row) {
            if (is_array($row) && is_string($row['legacy_module'] ?? null)) {
                $rows[$row['legacy_module']] = $row;
            }
        }
        foreach (InPlaceLegacyImport::REQUIRED_MODULES as $module) {
            $row = $rows[$module] ?? null;
            if ($row === null) {
                throw new RuntimeException("The legacy module {$module} is not in the installation's module registry; nothing was changed.");
            }
            if (($row['schema_approved'] ?? false) !== true) {
                $tables = array_map(
                    static fn (array $d): string => (string) $d['table'].($d['problem'] === 'missing' ? ' (missing)' : ''),
                    array_values(array_filter((array) ($row['schema_differences'] ?? []), 'is_array')),
                );
                throw new RuntimeException("The legacy module {$module} does not match its approved 1.9.1 shape (".($tables === [] ? 'no approved shape' : implode(', ', $tables)).'); nothing was changed.');
            }
        }
    }

    /** The config's database must be the very database this process renames tables in. */
    public static function assertSameDatabase(string $configured, ?string $connected): void
    {
        if ($configured === '' || $connected === null || $connected === '' || $configured !== $connected) {
            throw new RuntimeException('The database in data/config.php is not the database this Epesi 3 installation is connected to; refusing to rename tables. Nothing was changed.');
        }
    }

    public function legacyTables(): array
    {
        $report = ($this->assessor)($this->root.'/data/config.php');
        if (($report['compatible'] ?? false) !== true) {
            throw new RuntimeException('The legacy installation is not a compatible Epesi 1.x schema; refusing to rename its tables.');
        }

        return array_values(array_map('strval', $report['tables']));
    }

    public function install(array $renameMap): array
    {
        // The finished site must boot on this database without the wizard's override (a no-op unless it is in force).
        LegacyDatabaseConnection::persist();
        // A new import starts: which modules it may run is worked out again.
        ApprovedModulesCache::forget();

        return app(EpesiInstaller::class)->prepare($renameMap)['modules'];
    }

    public function import(array $completed, callable $record, array $renameMap): void
    {
        if ($renameMap === []) {
            throw new RuntimeException('The rename map is empty; refusing to import without knowing which tables are the legacy ones.');
        }
        $engine = $this->engine();
        $engine->useRenamedTables();
        // Fail closed: a module whose own tables do not match the approved shape is not imported
        // (its data stays preserved) while the others are.
        // Worked out once per migration (a full inventory takes seconds), not in every browser slice.
        $approved = ApprovedModulesCache::get($renameMap, fn (): array => InPlaceLegacyImport::approvedModules(app(LegacyInstallation::class)->coverageFor($this->pdo, $this->root, $renameMap)));

        // Mail and notes bring business files over. They run only through a moving transfer
        // that writes the file ledger (so verification can account for every
        // file); without a ledger they are left out and their files block verification.
        if ($this->ledger === null) {
            $approved = InPlaceLegacyImport::withoutFileModules($approved);
        }
        $dataDir = $this->root.'/data';
        config([
            'epesi-mail.legacy_data_dir' => $dataDir,
            'epesi-attachments.legacy_data_dir' => $dataDir,
            // A sliced run (a browser step) keeps its place here between requests.
            'epesi-mail.legacy_checkpoint' => storage_path('app/migration-wizard/mail-checkpoint.json'),
        ]);
        if ($this->ledger !== null) {
            app()->instance(LegacyFileTransfer::class, new MovingLegacyFileTransfer($this->ledger));
        }
        try {
            $engine->run($completed, $record, $approved);
        } finally {
            app()->forgetInstance(LegacyFileTransfer::class);
        }
    }

    public function handOffAdministrator(AdminCredentials $credentials, array $renameMap): array
    {
        $table = $renameMap['user_login'] ?? null;
        if (! is_string($table)) {
            throw new RuntimeException('The legacy user table is not in the rename map.');
        }

        return (new AdministratorHandoff)->apply($credentials, $this->pdo, $table);
    }

    public function verify(array $renameMap, array $state): array
    {
        $stage = static function (string $text, ?int $done = null, ?int $total = null): void {
            ImportProgress::stage($text, $done, $total);
        };
        [$report, $coverage, $areaCheck, $leftovers] = $this->measure($renameMap, $state, $stage);

        return $report + [
            'importers_not_run' => InPlaceLegacyImport::NOT_RUN,
            'import_results' => is_array($state['import_results'] ?? null) ? $state['import_results'] : [],
            'left_behind' => (new LeftBehindReport)->build($coverage, $report, $renameMap, $this->root, $this->writeLeftoverList($areaCheck, array_keys($leftovers))),
        ];
    }

    /**
     * Compare what is in the database and on the disk now. Changes nothing by itself: what it says is reported
     * through $stage, and writing that down is the caller's choice.
     *
     * @param  array<string, string>  $renameMap
     * @param  array<string, mixed>  $state
     * @param  callable(string, ?int, ?int): void  $stage
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: LegacyAreaCheck, 3: array<string, mixed>}
     */
    private function measure(array $renameMap, array $state, callable $stage): array
    {
        $this->engine()->useRenamedTables();
        $stage('Verifying data: scanning the old installation', null, null);
        $coverage = app(LegacyInstallation::class)->coverageFor($this->pdo, $this->root, $renameMap);
        $ledger = $this->ledger === null || $this->disks === null ? null : $this->ledger->verify($this->disks, $this->root.'/data', static function (int $done, int $total) use ($stage): void {
            $stage('Verifying the moved files', $done, $total);
        });
        // Every importer that ran, modules' included: each declares how its tables are compared (CountedTables).
        $records = array_diff_key(app(LegacyImportSet::class)->importers(), ['users' => 1, 'loginaudit' => 1]);
        $completed = $this->strings($state['completed_importers'] ?? []);
        $stage('Verifying data: checking the files that stay', null, null);
        $areaCheck = new LegacyAreaCheck;
        $migrated = $areaCheck->migratedAreas($this->root.'/data', $completed, $ledger);
        $leftovers = $areaCheck->leftovers($this->root.'/data', $migrated);
        $stage('Verifying data: counting the records', null, null);
        $input = (new VerificationInputBuilder(DB::connection('legacy'), $records, static fn (string $class): object => app($class)))
            ->build(
                $coverage,
                $completed,
                $this->keepUnmigrated(OperatorChoices::normalise($state['operator_choices'] ?? null)),
                $ledger,
                $migrated,
                $leftovers,
                (new LegacyModuleTables)->declared($this->root),
            );

        return [(new MigrationVerifier)->verify($input), $coverage, $areaCheck, $leftovers];
    }

    /**
     * What the last step would remove if it ran now, measured the way verification measures it. Writes nothing.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed> CleanupPreview::build() output
     */
    public function cleanupPreview(array $state): array
    {
        $renameMap = array_map('strval', (array) ($state['rename_map'] ?? []));
        if ($renameMap === []) {
            throw new RuntimeException('The migration has not renamed any legacy table yet: there is nothing to preview.');
        }
        [$verification] = $this->measure($renameMap, $state, static function (): void {});
        // The tables nobody owns go in the step before cleanup, so they do not keep anything here.
        $dropped = [
            ...array_map('strval', (array) ($verification['cleanup_eligible_tables'] ?? [])),
            ...array_map('strval', (array) ($verification['unowned_tables'] ?? [])),
        ];
        $plan = (new LegacyCleanup(new LegacyFileClassifier))->plan(
            $this->root,
            $this->cleanupManifests($state),
            ['cleanup_eligible_tables' => array_values(array_filter($dropped, static fn (string $table): bool => isset($renameMap[$table])))] + $verification,
            $renameMap,
            $this->pdo,
        );

        return (new CleanupPreview)->build($this->pdo, $this->root, $verification, $plan, $renameMap);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{release: list<string>, options: array<string, mixed>}
     */
    private function cleanupManifests(array $state): array
    {
        // The list of the files this release ships (update/MANIFEST.txt), when there is one: a safety net (a file it
        // names is never removed). Without it the top-level rules and the modules' module.json decide.
        $manifests = (new CleanupManifests)->loadIfPresent($this->root);
        $installed = [];
        foreach (ModuleRegistry::enabled() as $module) {
            $path = (string) ($module['path'] ?? '');
            if ($path !== '') {
                $installed[] = 'modules/'.$path;
            }
        }

        return ['release' => $manifests['release'], 'options' => [
            'installed_modules' => $installed,
            'exclude' => [storage_path('app/migration-wizard')],
            // Only a legacy module a completed importer migrated may lose its directory; every other one stays.
            'migrated_modules' => InPlaceLegacyImport::migratedModules($this->strings($state['completed_importers'] ?? [])),
        ]];
    }

    /**
     * Data of a known module that no importer migrates is kept, and the operator is told what was left behind;
     * data nobody owns is removed after verification. An exact name given with --preserve keeps it, one given with
     * --discard removes it. No browser choice is needed.
     *
     * @param  array{preserve: list<string>, discard: list<string>, core_only: bool}  $choices
     * @return array{preserve: list<string>, discard: list<string>, core_only: bool, discard_unknown: bool}
     */
    private function keepUnmigrated(array $choices): array
    {
        // Data of a known module without an importer is kept (core only); data nobody owns is removed right
        // after verification (VerificationInputBuilder: `unowned`), without asking.
        $choices['core_only'] = true;
        $choices['discard_unknown'] = true;

        return $choices;
    }

    /**
     * The complete list of the files no imported record references, one path per line, for the operator.
     *
     * @param  list<string>  $areas
     * @return string|null the file's path relative to the installation, null when nothing was left
     */
    private function writeLeftoverList(LegacyAreaCheck $check, array $areas): ?string
    {
        if ($areas === []) {
            return null;
        }
        $paths = [];
        foreach ($areas as $area) {
            array_push($paths, ...$check->listRemaining($this->root.'/data', $area));
        }
        $directory = storage_path('app/migration-wizard');
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            return null;
        }
        if (@file_put_contents($directory.'/leftover-files.txt', implode(PHP_EOL, $paths).PHP_EOL, LOCK_EX) === false) {
            return null;
        }

        return 'storage/app/migration-wizard/leftover-files.txt';
    }

    public function cleanup(array $verification, array $renameMap, array $state): array
    {
        // Everything before the first deletion is a refusal: nothing changed, cleanup can be run again once it is fixed.
        try {
            if (! $this->cleanupEnabled) {
                throw new CleanupRefused('Cleanup is switched off.');
            }
            if (! is_array($state['preupgrade_backup'] ?? null)) {
                throw new CleanupRefused('The pre-upgrade backup is not confirmed; refusing cleanup.');
            }
            $manifests = $this->cleanupManifests($state);
            $stateDirectory = storage_path('app/migration-wizard');
            if (! is_dir($stateDirectory) && ! @mkdir($stateDirectory, 0700, true) && ! is_dir($stateDirectory)) {
                throw new CleanupRefused('Cannot create '.$stateDirectory.' for the cleanup list.');
            }
            // Files cleanup cannot place (user-added, left by another tool) are archived here before they go.
            $archiveDirectory = $stateDirectory.'/leftovers';
            if (! is_dir($archiveDirectory) && ! @mkdir($archiveDirectory, 0700, true) && ! is_dir($archiveDirectory)) {
                throw new CleanupRefused('Cannot create '.$archiveDirectory.' for the leftovers archive.');
            }
        } catch (CleanupRefused $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new CleanupRefused($e->getMessage(), 0, $e);
        }

        ImportProgress::stage('Data cleanup: listing the old files');
        $result = (new LegacyCleanup(new LegacyFileClassifier, true))->execute(
            $this->root,
            $archiveDirectory,
            $manifests,
            $verification,
            $renameMap,
            $this->pdo,
            true,
        );

        // The legacy module folders that stay leave modules/ for legacy-modules/ (moved, never deleted), so that
        // modules/ holds Epesi 3's modules only.
        ImportProgress::stage('Data cleanup: moving the old module folders to legacy-modules');
        $result['legacy_modules'] = (new LegacyModulesMover)->move($this->root);

        return $result;
    }

    private function engine(): InPlaceLegacyImport
    {
        return $this->import ?? app(InPlaceLegacyImport::class);
    }

    private function connectedDatabase(): ?string
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return null;
        }
        $name = $this->pdo->query('SELECT DATABASE()')->fetchColumn();

        return is_string($name) ? $name : null;
    }

    /** @return list<string> */
    private function strings(mixed $value): array
    {
        return is_array($value) ? array_values(array_map('strval', $value)) : [];
    }
}
