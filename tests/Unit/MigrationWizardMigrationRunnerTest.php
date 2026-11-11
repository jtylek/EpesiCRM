<?php

use Epesi\Modules\MigrationWizard\Admin\AdminCredentials;
use Epesi\Modules\MigrationWizard\Cleanup\CleanupRefused;
use Epesi\Modules\MigrationWizard\Database\LegacyTableNamespace;
use Epesi\Modules\MigrationWizard\Orchestration\MigrationFailed;
use Epesi\Modules\MigrationWizard\Orchestration\MigrationPhases;
use Epesi\Modules\MigrationWizard\Orchestration\MigrationRunner;
use Epesi\Modules\MigrationWizard\Orchestration\MigrationStopped;
use Epesi\Modules\MigrationWizard\State\MigrationLock;
use Epesi\Modules\MigrationWizard\State\MigrationState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Scripted phases: records calls, creates Epesi 3 tables on install, can fail on demand. */
final class RunnerFakePhases implements MigrationPhases
{
    /** @var list<string> */
    public array $calls = [];

    public ?string $failAt = null;

    /** @var (callable(): void)|null runs inside install(), after the tables exist */
    public $duringInstall = null;

    public array $verification = ['status' => 'pass', 'cleanup_allowed' => true, 'outcome' => 'verified'];

    /** @var list<string> the legacy tables legacyTables() reports */
    public array $legacyTables = ['modules', 'user_login'];

    /** @var list<string> the namespaced tables cleanup() reports as dropped */
    public array $dropped = ['legacy_modules'];

    /** @var list<list<string>> the completed importers each import() call was given */
    public array $completedSeen = [];

    /** @var list<string> importers the fake runs, recording each as it finishes */
    public array $importTabs = ['users', 'companies'];

    /** @var array<string, mixed>|null the state verify() was given */
    public ?array $verifyState = null;

    /** @var list<string> importers to fail on (after the earlier ones were recorded) */
    public array $failImporter = [];

    /** @var string|null the message every scripted failure carries (default "<step> failed") */
    public ?string $failureText = null;

    /** @var bool cleanup() refuses before it changes anything */
    public bool $cleanupRefuses = false;

    public function __construct(private PDO $pdo) {}

    private function enter(string $name): void
    {
        $this->calls[] = $name;
        if ($this->failAt === $name) {
            throw new RuntimeException($this->failureText ?? "{$name} failed");
        }
    }

    public function freeze(array $state): void
    {
        $this->enter('freeze');
    }

    public function legacyTables(): array
    {
        $this->enter('legacyTables');

        return $this->legacyTables;
    }

    public function install(array $renameMap): array
    {
        $this->enter('install');
        // Epesi 3 takes the freed names and adds tables of its own.
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS modules (id INTEGER)');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER)');

        if ($this->duringInstall !== null) {
            ($this->duringInstall)();
        }

        return ['epesi/core'];
    }

    public function import(array $completed, callable $record, array $renameMap): void
    {
        $this->enter('import');
        $this->completedSeen[] = $completed;
        foreach ($this->importTabs as $tab) {
            if (in_array($tab, $completed, true)) {
                continue;
            }
            if (in_array($tab, $this->failImporter, true)) {
                throw new RuntimeException($this->failureText ?? "importer {$tab} failed");
            }
            $record($tab, ['created' => 2, 'updated' => 0, 'history_rows' => 3, 'warnings' => 0]);
        }
    }

    public function handOffAdministrator(AdminCredentials $credentials, array $renameMap): array
    {
        $this->enter('handoff');

        return ['user_id' => 1, 'legacy_id' => $credentials->legacyId, 'login' => 'root'];
    }

    public function verify(array $renameMap, array $state): array
    {
        $this->enter('verify');
        $this->verifyState = $state;

        return $this->verification;
    }

    public function cleanup(array $verification, array $renameMap, array $state): array
    {
        $this->enter('cleanup');
        if ($this->cleanupRefuses) {
            throw new CleanupRefused('Cleanup is switched off.');
        }

        return ['dropped_tables' => $this->dropped, 'removed_files' => 3];
    }
}

final class MigrationWizardMigrationRunnerTest extends TestCase
{
    private string $dir;

    private PDO $pdo;

    private MigrationState $state;

    private RunnerFakePhases $phases;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/epesi-runner-'.bin2hex(random_bytes(6));
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE modules (id INTEGER, legacy INTEGER)');
        $this->pdo->exec('CREATE TABLE user_login (id INTEGER)');
        $this->pdo->exec('CREATE TABLE other_app (id INTEGER)');
        $this->state = new MigrationState($this->dir);
        $this->phases = new RunnerFakePhases($this->pdo);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function runner(): MigrationRunner
    {
        return new MigrationRunner(
            $this->state,
            new MigrationLock($this->dir),
            new LegacyTableNamespace,
            $this->pdo,
            $this->phases,
        );
    }

    private function toBackedUp(bool $backupConfirmed = true): void
    {
        $this->state->update(0, ['phase' => 'assessed']);
        $this->state->update(1, [
            'phase' => 'backed_up',
            'preupgrade_backup' => $backupConfirmed ? ['confirmed_at' => '2026-01-01T00:00:00+00:00'] : null,
        ]);
    }

    /** @return list<string> */
    private function tables(): array
    {
        $names = (new LegacyTableNamespace)->tables($this->pdo);
        sort($names);

        return $names;
    }

    public function test_resume_refuses_before_a_backup(): void
    {
        $this->expectException(RuntimeException::class);
        $this->runner()->resume();
    }

    public function test_resume_runs_to_the_administrator_handoff_and_persists_the_map_first(): void
    {
        $this->toBackedUp();
        $result = $this->runner()->resume();

        self::assertSame('importing', $result['phase']);
        self::assertSame('awaiting_admin_handoff', $result['stopped']);
        self::assertSame(['freeze', 'namespace_tables', 'install', 'start_import', 'import'], $result['steps']);
        $saved = $this->state->read();
        self::assertSame(['modules' => 'legacy_modules', 'user_login' => 'legacy_user_login'], $saved['rename_map']);
        self::assertSame(['epesi/core'], $saved['installed_modules']);
        self::assertSame(['legacy_modules', 'legacy_user_login', 'modules', 'other_app', 'users'], $this->tables());
    }

    public function test_the_rename_map_is_saved_before_the_first_rename(): void
    {
        $this->toBackedUp();
        $seen = null;
        $this->phases->duringInstall = function () use (&$seen): void {
            $seen = $this->state->read();
        };
        $this->runner()->resume();

        self::assertSame('tables_namespaced', $seen['phase']);
        self::assertSame(['modules' => 'legacy_modules', 'user_login' => 'legacy_user_login'], $seen['rename_map']);
    }

    public function test_resume_is_idempotent_after_an_interruption(): void
    {
        $this->toBackedUp();
        $this->runner()->resume();
        $this->phases->calls = [];

        $result = $this->runner()->resume(new AdminCredentials(7, 'correct horse'));

        self::assertNotContains('freeze', $this->phases->calls);
        self::assertNotContains('legacyTables', $this->phases->calls);
        self::assertContains($result['phase'], ['complete', 'core_complete_with_preserved_data'], 'cleanup always runs after verification');
        self::assertNull($result['stopped']);

        // A third run changes nothing and does not repeat the handoff.
        $this->phases->calls = [];
        $again = $this->runner()->resume(new AdminCredentials(7, 'correct horse'));
        self::assertSame($result['phase'], $again['phase']);
        self::assertSame([], $again['steps']);
        self::assertSame([], $this->phases->calls);
    }

    public function test_a_failure_before_the_rename_changes_nothing(): void
    {
        $this->toBackedUp();
        $this->phases->failAt = 'legacyTables';

        try {
            $this->runner()->resume();
            self::fail('Expected a failure.');
        } catch (RuntimeException $e) {
            self::assertNotInstanceOf(MigrationFailed::class, $e);
        }

        self::assertSame(['modules', 'other_app', 'user_login'], $this->tables());
        self::assertSame('frozen', $this->state->read()['phase']);
    }

    public function test_state_never_contains_the_password(): void
    {
        $this->toBackedUp();
        $this->runner()->resume(new AdminCredentials(7, 'sup3r-secret-pw'));

        self::assertStringNotContainsString('sup3r-secret-pw', (string) file_get_contents($this->dir.'/state.json'));
        self::assertSame(7, $this->state->read()['admin_handoff']['legacy_id']);
    }

    public function test_cleanup_runs_right_after_verification_and_the_migration_completes(): void
    {
        $this->toBackedUp();
        $result = $this->runner()->resume(new AdminCredentials(7, 'correct horse'));

        self::assertSame('complete', $result['phase']);
        self::assertNull($result['stopped']);
        self::assertContains('cleanup', $this->phases->calls, 'nobody has to ask for it');
    }

    public function test_preserved_data_outcome_ends_core_complete(): void
    {
        $this->toBackedUp();
        $this->phases->verification = ['status' => 'warn', 'cleanup_allowed' => true, 'outcome' => 'core_complete_with_preserved_data'];
        $this->runner()->resume(new AdminCredentials(7, 'correct horse'));

        self::assertSame('core_complete_with_preserved_data', $this->runner()->resume(null, true)['phase']);
    }

    public function test_an_unknown_phase_is_refused_everywhere(): void
    {
        mkdir($this->dir, 0700, true);
        file_put_contents($this->dir.'/state.json', json_encode(['phase' => 'mystery', 'version' => 3]));

        try {
            $this->runner()->resume();
            self::fail('resume accepted an unknown phase.');
        } catch (RuntimeException $e) {
            self::assertSame('Unknown migration state; refusing to continue.', $e->getMessage());
        }
        // Nothing was written.
        self::assertSame(['mystery', 3], [$this->state->read()['phase'], $this->state->read()['version']]);
    }

    public function test_only_one_run_is_active(): void
    {
        $this->toBackedUp();
        $lock = new MigrationLock($this->dir);
        $result = $lock->withLock('other', function (): string {
            try {
                $this->runner()->resume();

                return 'ran';
            } catch (RuntimeException $e) {
                return $e->getMessage();
            }
        });

        self::assertSame('Another migration run is active.', $result);
        self::assertSame('backed_up', $this->state->read()['phase']);
    }

    public function test_the_verification_is_given_the_operator_choices_through_the_state(): void
    {
        $this->toBackedUp();
        $choices = ['preserve' => ['custom_invoice'], 'discard' => ['old_cache'], 'core_only' => false];
        $this->runner()->setChoices($choices);
        $this->runner()->resume(new AdminCredentials(7, 'correct horse'));

        self::assertSame($choices, $this->phases->verifyState['operator_choices']);
        self::assertSame($choices, $this->runner()->status()['operator_choices']);
    }

    public function test_choices_can_no_longer_change_once_verification_passed(): void
    {
        $this->toBackedUp();
        $this->runner()->resume(new AdminCredentials(7, 'correct horse'));
        self::assertContains($this->state->read()['phase'], ['complete', 'core_complete_with_preserved_data']);

        $this->expectException(RuntimeException::class);
        $this->runner()->setChoices(['preserve' => ['x'], 'discard' => [], 'core_only' => false]);
    }

    public function test_the_typed_backup_confirmation_is_the_only_way_to_backed_up_and_only_before_the_migration_begins(): void
    {
        self::assertSame('detected', $this->runner()->status()['phase']);
        self::assertFalse($this->runner()->status()['preupgrade_backup_confirmed']);

        $this->runner()->confirmPreUpgradeBackup();
        $state = $this->state->read();
        self::assertSame('backed_up', $state['phase']);
        self::assertIsString($state['preupgrade_backup']['confirmed_at']);
        self::assertTrue($this->runner()->status()['preupgrade_backup_confirmed']);

        $this->runner()->resume();
        $this->expectException(RuntimeException::class);
        $this->runner()->confirmPreUpgradeBackup();
    }

    public function test_cleanup_needs_only_the_backup_confirmation(): void
    {
        $this->toBackedUp();
        $this->runner()->resume(new AdminCredentials(7, 'correct horse'));

        self::assertSame('complete', $this->runner()->resume(null, true)['phase']);
        self::assertContains('cleanup', $this->phases->calls);
    }

    public function test_the_removed_restore_test_and_backup_record_are_gone(): void
    {
        self::assertFalse(method_exists(MigrationRunner::class, 'confirmRestoreTested'));
        self::assertFalse(method_exists(MigrationRunner::class, 'restoreTested'));
        self::assertFalse(method_exists(MigrationRunner::class, 'recordBackup'));
        self::assertArrayNotHasKey('backup_recorded', $this->runner()->status());
        self::assertArrayNotHasKey('backup_restore_tested', $this->runner()->status());
    }

    public function test_an_old_state_with_a_recorded_backup_and_a_restore_test_does_not_crash(): void
    {
        $this->state->update(0, ['phase' => 'backed_up', 'backup' => ['manifest' => '/old/m.json', 'manifest_sha256' => str_repeat('a', 64)], 'restore_tested' => ['manifest_sha256' => str_repeat('a', 64)]]);

        // The old recorded backup is ignored: the typed confirmation is still required.
        try {
            $this->runner()->resume();
            self::fail('The run started without the typed backup confirmation.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('--confirm-backup', $e->getMessage());
        }
        self::assertFalse($this->runner()->status()['preupgrade_backup_confirmed']);

        $this->runner()->confirmPreUpgradeBackup();
        self::assertSame('awaiting_admin_handoff', $this->runner()->resume()['stopped']);
        self::assertSame('complete', $this->runner()->resume(new AdminCredentials(7, 'correct horse'), true)['phase']);
    }

    private function sqliteTables(): array
    {
        return $this->pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Legacy Roundcube tables (obsolete, rc_users is the parent) beside one that must stay, and a live rc_session of Epesi 3's own. */
    private function withObsoleteTables(): void
    {
        $this->pdo->exec('CREATE TABLE rc_users (user_id INTEGER PRIMARY KEY, username TEXT)');
        $this->pdo->exec('CREATE TABLE rc_identities (identity_id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES rc_users (user_id), signature TEXT)');
        $this->pdo->exec('CREATE TABLE rc_accounts_data_1 (id INTEGER PRIMARY KEY, f_login TEXT)');
        $this->pdo->exec("INSERT INTO rc_users VALUES (1, 'eli'), (2, 'ann')");
        $this->pdo->exec("INSERT INTO rc_identities VALUES (1, 1, 'Regards, Eli')");
        $this->pdo->exec("INSERT INTO rc_accounts_data_1 VALUES (1, 'eli')");
        $this->phases->legacyTables = ['modules', 'rc_accounts_data_1', 'rc_identities', 'rc_users', 'user_login'];
        $this->phases->verification = ['status' => 'pass', 'cleanup_allowed' => true, 'outcome' => 'verified', 'obsolete_tables' => ['rc_users', 'rc_identities']];
    }

    private function verifyWithObsoleteTables(): void
    {
        $this->toBackedUp();
        $this->withObsoleteTables();
        $this->runner()->resume(new AdminCredentials(7, 'correct horse'));
    }

    public function test_obsolete_tables_are_dropped_when_verification_passes_before_the_cleanup(): void
    {
        $this->toBackedUp();
        $this->withObsoleteTables();
        // Not before success: the run stops at the administrator handoff with every legacy table still there.
        $this->runner()->resume();
        self::assertContains('legacy_rc_users', $this->sqliteTables());
        self::assertSame([], $this->runner()->status()['obsolete_tables_dropped']);

        $result = $this->runner()->resume(new AdminCredentials(7, 'correct horse'));

        self::assertContains('drop_obsolete_tables', $result['steps']);
        self::assertContains('cleanup', $this->phases->calls, 'cleanup always runs after verification');
        $tables = $this->sqliteTables();
        self::assertNotContains('legacy_rc_users', $tables);
        self::assertNotContains('legacy_rc_identities', $tables);
        // Nothing else is touched: the mail accounts, Epesi 3's own tables, the other legacy tables.
        foreach (['legacy_rc_accounts_data_1', 'legacy_modules', 'legacy_user_login', 'users', 'other_app'] as $stays) {
            self::assertContains($stays, $tables, $stays);
        }
        self::assertSame('1', (string) $this->pdo->query('SELECT COUNT(*) FROM legacy_rc_accounts_data_1')->fetchColumn());
        // Children are dropped before their parent, and the status says so.
        self::assertSame(['rc_identities', 'rc_users'], $this->runner()->status()['obsolete_tables_dropped']);
        self::assertSame(['rc_users', 'rc_identities'], $this->runner()->status()['obsolete_tables']);
    }

    public function test_a_live_table_with_the_original_name_is_never_the_one_dropped(): void
    {
        $this->toBackedUp();
        $this->withObsoleteTables();
        // Epesi 3's own Roundcube may own an rc_session; only the namespaced legacy copy is ever named.
        $this->phases->duringInstall = function (): void {
            $this->pdo->exec('CREATE TABLE rc_users (user_id INTEGER, live TEXT)');
        };

        $this->runner()->resume(new AdminCredentials(7, 'correct horse'));

        self::assertContains('rc_users', $this->sqliteTables(), 'the live table stays');
        self::assertNotContains('legacy_rc_users', $this->sqliteTables());
    }

    public function test_an_absent_obsolete_table_changes_nothing(): void
    {
        $this->toBackedUp();
        $this->runner()->resume(new AdminCredentials(7, 'correct horse'));

        self::assertSame([], $this->runner()->status()['obsolete_tables']);
        self::assertSame([], $this->runner()->status()['obsolete_tables_dropped']);
        self::assertArrayNotHasKey('obsolete_drop', $this->state->read());
    }

    public function test_the_migration_changes_nothing_until_the_preupgrade_backup_is_confirmed(): void
    {
        $this->toBackedUp(backupConfirmed: false);
        $before = $this->tables();

        try {
            $this->runner()->resume();
            self::fail('The run started without a confirmed pre-upgrade backup.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('full backup', $e->getMessage());
            self::assertStringContainsString('database', $e->getMessage());
            self::assertStringContainsString('Epesi directory', $e->getMessage());
            self::assertStringContainsString('--confirm-backup', $e->getMessage());
        }

        self::assertSame($before, $this->tables());
        self::assertSame([], $this->phases->calls, 'not even the freeze ran');
        self::assertSame('backed_up', $this->state->read()['phase']);
        self::assertFalse($this->runner()->status()['preupgrade_backup_confirmed']);

        // Reading the state needs no confirmation.
        self::assertSame('backed_up', $this->runner()->status()['phase']);

        $this->runner()->confirmPreUpgradeBackup();
        self::assertTrue($this->runner()->status()['preupgrade_backup_confirmed']);
        self::assertSame('awaiting_admin_handoff', $this->runner()->resume()['stopped']);
    }

    public function test_the_preupgrade_backup_cannot_be_confirmed_once_the_migration_has_begun_and_is_never_a_default(): void
    {
        $this->toBackedUp(backupConfirmed: false);
        self::assertArrayNotHasKey('preupgrade_backup', $this->state->read());

        $this->runner()->confirmPreUpgradeBackup();
        $this->state->update($this->state->read()['version'], ['phase' => 'frozen']);

        $this->expectException(RuntimeException::class);
        $this->runner()->confirmPreUpgradeBackup();
    }

    private const STOP = 'Migration stopped. Fix the problem and run it again to continue.';

    private function assertTerminal(callable $run, string $reason): void
    {
        try {
            $run();
            self::fail('The failure was not reported.');
        } catch (MigrationFailed $e) {
            self::assertSame(
                "Migration failed and cannot continue.\nReason: {$reason} (copy this before restoring)\nPlease restore your Epesi directory and database from your backup, then switch PHP back to 7.4. See the Upgrade Guide.",
                $e->getMessage(),
            );
        }
        $state = $this->state->read();
        self::assertSame('failed', $state['phase']);
        self::assertSame($reason, $state['failure']['reason']);
    }

    /** @return array<string, array{0: string}> */
    public static function terminalSteps(): array
    {
        return ['install' => ['install'], 'import' => ['import'], 'verify' => ['verify']];
    }

    #[DataProvider('terminalSteps')]
    public function test_a_failure_after_the_wizard_began_to_change_things_is_terminal_and_nothing_is_undone(string $failAt): void
    {
        $this->toBackedUp();
        $this->phases->failAt = $failAt;
        $this->phases->failureText = 'the importer found a record it cannot read';

        $this->assertTerminal(fn () => $this->runner()->resume(new AdminCredentials(7, 'correct horse')), 'the importer found a record it cannot read');

        // The legacy tables keep their new names: there is no way back inside the wizard, only the backup.
        self::assertContains('legacy_user_login', $this->tables());
        self::assertSame($failAt === 'install' ? 'tables_namespaced' : 'importing', $this->state->read()['failure']['failed_in']);
    }

    public function test_after_a_terminal_failure_every_run_and_every_change_refuses_and_status_still_works(): void
    {
        $this->toBackedUp();
        $this->phases->failAt = 'import';
        $this->phases->failureText = 'record 7 is corrupt';
        try {
            $this->runner()->resume(new AdminCredentials(7, 'correct horse'));
            self::fail('Expected the terminal failure.');
        } catch (MigrationFailed) {
        }
        $before = $this->state->read();
        $this->phases->failAt = null;
        $this->phases->calls = [];

        // --resume shows the same message and does nothing.
        $this->assertTerminal(fn () => $this->runner()->resume(new AdminCredentials(7, 'correct horse'), true), 'record 7 is corrupt');
        self::assertSame([], $this->phases->calls);
        // The other steps that change state refuse as well.
        foreach ([
            fn () => $this->runner()->confirmPreUpgradeBackup(),
            fn () => $this->runner()->setChoices(['preserve' => [], 'discard' => [], 'core_only' => true]),
        ] as $mutation) {
            try {
                $mutation();
                self::fail('A change was accepted in the terminal state.');
            } catch (RuntimeException) {
                self::assertTrue(true);
            }
        }
        self::assertSame($before, $this->state->read());

        // --status still works and shows it.
        $status = $this->runner()->status();
        self::assertSame('failed', $status['phase']);
        self::assertSame('record 7 is corrupt', $status['failure']['reason']);
        self::assertSame('importing', $status['failure']['failed_in']);
    }

    public function test_an_interruption_stops_the_run_and_resume_continues_from_where_it_was(): void
    {
        $this->toBackedUp();
        $this->phases->failImporter = ['companies'];
        $this->phases->failureText = 'No space left on device';

        try {
            $this->runner()->resume();
            self::fail('The interruption must stop the run.');
        } catch (MigrationStopped $e) {
            self::assertStringStartsWith(self::STOP, $e->getMessage());
            self::assertStringContainsString('Reason: No space left on device', $e->getMessage());
        }
        // Nothing was lost or undone: the importer that finished is recorded, the phase did not move to failed.
        self::assertSame('importing', $this->state->read()['phase']);
        self::assertSame(['users'], $this->state->read()['completed_importers']);
        self::assertContains('legacy_user_login', $this->tables());

        // The administrator frees the space and runs it again: the finished importer is skipped.
        $this->phases->failImporter = [];
        $this->phases->completedSeen = [];
        $this->runner()->resume();
        self::assertSame([['users']], $this->phases->completedSeen);
        self::assertSame(['users', 'companies'], $this->state->read()['completed_importers']);
        self::assertSame('awaiting_admin_handoff', $this->runner()->resume()['stopped']);
    }

    /** @return array<string, array{0: string}> */
    public static function interruptions(): array
    {
        return [
            'disk' => ['No space left on device'], 'quota' => ['Disk quota exceeded'], 'timeout' => ['Maximum execution time of 30 seconds exceeded'],
            'connection' => ['SQLSTATE[HY000]: General error: 2006 MySQL server has gone away'], 'lost' => ['Lost connection to MySQL server during query'],
        ];
    }

    #[DataProvider('interruptions')]
    public function test_the_known_interruptions_are_stops_not_failures(string $text): void
    {
        $this->toBackedUp();
        $this->phases->failAt = 'install';
        $this->phases->failureText = $text;

        try {
            $this->runner()->resume();
            self::fail('Expected a stop.');
        } catch (MigrationStopped $e) {
            self::assertStringContainsString($text, $e->getMessage());
        }
        self::assertSame('tables_namespaced', $this->state->read()['phase']);

        $this->phases->failAt = null;
        self::assertSame('awaiting_admin_handoff', $this->runner()->resume()['stopped']);
    }

    public function test_a_wrong_administrator_account_is_a_stop_nothing_changed(): void
    {
        $this->toBackedUp();
        $this->phases->failAt = 'handoff';
        $this->phases->failureText = 'The legacy user 99 is not an active administrator.';

        try {
            $this->runner()->resume(new AdminCredentials(99, 'correct horse'));
            self::fail('Expected a stop.');
        } catch (MigrationStopped $e) {
            self::assertStringContainsString('not an active administrator', $e->getMessage());
        }
        self::assertSame('importing', $this->state->read()['phase']);
        $this->phases->failAt = null;
        self::assertContains($this->runner()->resume(new AdminCredentials(7, 'correct horse'))['phase'], ['complete', 'core_complete_with_preserved_data']);
    }

    public function test_a_failure_before_anything_was_changed_is_a_stop(): void
    {
        $this->toBackedUp();
        $this->phases->failAt = 'freeze';
        $this->phases->failureText = 'The legacy installation is not an approved Epesi release.';

        try {
            $this->runner()->resume();
            self::fail('Expected a stop.');
        } catch (MigrationStopped $e) {
            self::assertStringContainsString('approved Epesi release', $e->getMessage());
        }
        self::assertSame('backed_up', $this->state->read()['phase']);
        self::assertSame(['modules', 'other_app', 'user_login'], $this->tables());
    }

    public function test_verification_that_only_needs_a_choice_from_the_operator_is_a_stop(): void
    {
        $this->toBackedUp();
        $this->phases->verification = ['status' => 'block', 'cleanup_allowed' => false, 'blocking' => [['check' => 'unclassified', 'label' => 'Table x', 'detail' => 'Unknown legacy data']]];

        $result = $this->runner()->resume(new AdminCredentials(7, 'correct horse'));

        self::assertSame('verification_blocked', $result['stopped']);
        self::assertSame('importing', $result['phase']);
        self::assertContains('legacy_modules', $this->tables());
    }

    public function test_a_verification_failure_about_files_or_data_is_terminal_with_its_reasons(): void
    {
        $this->toBackedUp();
        $this->phases->verification = ['status' => 'block', 'cleanup_allowed' => false, 'blocking' => [
            ['check' => 'file_ledger', 'label' => 'CRM_Mail/attachments/1/a', 'detail' => 'File problem: missing'],
            ['check' => 'unclassified', 'label' => 'Table x', 'detail' => 'Unknown legacy data'],
        ]];

        $this->assertTerminal(fn () => $this->runner()->resume(new AdminCredentials(7, 'correct horse')), 'Verification failed: CRM_Mail/attachments/1/a: File problem: missing; Table x: Unknown legacy data');
    }

    public function test_a_verification_that_blocks_without_saying_why_is_terminal(): void
    {
        $this->toBackedUp();
        $this->phases->verification = ['status' => 'block', 'cleanup_allowed' => false];

        $this->assertTerminal(fn () => $this->runner()->resume(new AdminCredentials(7, 'correct horse')), 'Verification failed: no reason recorded');
    }

    public function test_nothing_is_dropped_when_verification_fails(): void
    {
        $this->toBackedUp();
        $this->withObsoleteTables();
        $this->phases->verification = ['status' => 'block', 'cleanup_allowed' => false, 'outcome' => 'not_verified', 'obsolete_tables' => ['rc_users', 'rc_identities'], 'blocking' => [['check' => 'unclassified', 'label' => 'x', 'detail' => 'y']]];

        $result = $this->runner()->resume(new AdminCredentials(7, 'correct horse'));

        self::assertSame('verification_blocked', $result['stopped']);
        self::assertContains('legacy_rc_users', $this->sqliteTables());
        self::assertContains('legacy_rc_identities', $this->sqliteTables());
        self::assertArrayNotHasKey('obsolete_drop', $this->state->read());
    }

    public function test_cleanup_that_refuses_before_changing_anything_is_a_stop_and_one_that_fails_midway_is_terminal(): void
    {
        $this->toBackedUp();
        $this->phases->cleanupRefuses = true;
        try {
            $this->runner()->resume(new AdminCredentials(7, 'correct horse'));
            self::fail('Expected a stop.');
        } catch (MigrationStopped $e) {
            self::assertStringContainsString('switched off', $e->getMessage());
        }
        self::assertSame('verified', $this->state->read()['phase']);

        $this->phases->cleanupRefuses = false;
        $this->phases->failAt = 'cleanup';
        $this->phases->failureText = 'could not remove vendor/old/lib.php';
        try {
            $this->runner()->resume(null, true);
            self::fail('A failed cleanup must stop the run, not end it.');
        } catch (MigrationStopped $e) {
            self::assertStringContainsString('could not remove vendor/old/lib.php', $e->getMessage());
        }
        self::assertSame('verified', $this->state->read()['phase']);

        // Cleanup is safe to repeat: Continue runs it again.
        $this->phases->failAt = null;
        self::assertNull($this->runner()->resume(null, true)['stopped']);
    }

    public function test_an_interrupted_drop_of_the_obsolete_tables_resumes_with_what_is_left(): void
    {
        // The cleanup that follows refuses, so the run stays at `verified` right after the drops.
        $this->phases->cleanupRefuses = true;
        try {
            $this->verifyWithObsoleteTables();
            self::fail('Expected the cleanup to stop the run.');
        } catch (MigrationStopped) {
        }
        self::assertSame('verified', $this->state->read()['phase']);
        self::assertSame(['rc_identities', 'rc_users'], $this->state->read()['obsolete_drop']['dropped']);

        // A crash after the child was dropped and recorded, before the parent was: put that state back.
        $this->pdo->exec('CREATE TABLE legacy_rc_users (user_id INTEGER PRIMARY KEY, username TEXT)');
        $this->state->update($this->state->read()['version'], ['obsolete_drop' => ['dropped' => ['rc_identities']]]);

        $this->phases->cleanupRefuses = false;
        $result = $this->runner()->resume();

        self::assertContains('drop_obsolete_tables', $result['steps']);
        self::assertNotContains('legacy_rc_users', $this->sqliteTables());
        self::assertSame(['rc_identities', 'rc_users'], $this->state->read()['obsolete_drop']['dropped']);
    }

    public function test_the_runner_has_no_way_back_of_its_own(): void
    {
        self::assertFalse(method_exists(MigrationRunner::class, 'rollback'));
        self::assertFalse(defined(MigrationState::class.'::ROLLING_BACK'));
        self::assertArrayNotHasKey('rollback_possible', $this->runner()->status());
    }
}
