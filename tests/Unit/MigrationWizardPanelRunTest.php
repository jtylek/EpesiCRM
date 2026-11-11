<?php

use App\Services\LegacyImport\TimeBudget;
use App\Services\LegacyImport\TimeBudgetReached;
use Epesi\Modules\MigrationWizard\Admin\AdminCredentials;
use Epesi\Modules\MigrationWizard\Cleanup\CleanupRefused;
use Epesi\Modules\MigrationWizard\Database\LegacyTableNamespace;
use Epesi\Modules\MigrationWizard\Orchestration\MigrationPhases;
use Epesi\Modules\MigrationWizard\Orchestration\MigrationRunner;
use Epesi\Modules\MigrationWizard\Orchestration\PanelRun;
use Epesi\Modules\MigrationWizard\State\MigrationLock;
use Epesi\Modules\MigrationWizard\State\MigrationState;
use PHPUnit\Framework\TestCase;

/** Scripted phases for the panel tests: the real runner, state file and lock; nothing else. */
final class PanelFakePhases implements MigrationPhases
{
    /** @var list<string> */
    public array $calls = [];

    public ?string $failAt = null;

    public ?string $failureText = null;

    /** @var list<string> importers to run, each recorded as it finishes */
    public array $tabs = ['users', 'companies', 'contacts'];

    public bool $budgetStopsImport = false;

    public bool $cleanupRefuses = false;

    public function __construct(private readonly PDO $pdo, private readonly TimeBudget $budget) {}

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

        return ['user_login'];
    }

    public function install(array $renameMap): array
    {
        $this->enter('install');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER)');

        return ['epesi/core'];
    }

    public function import(array $completed, callable $record, array $renameMap): void
    {
        $this->enter('import');
        $ran = 0;
        foreach ($this->tabs as $tab) {
            if (in_array($tab, $completed, true)) {
                continue;
            }
            if ($this->budgetStopsImport && $ran > 0 && $this->budget->exceeded()) {
                throw new TimeBudgetReached;
            }
            $record($tab, ['created' => 1, 'updated' => 0, 'history_rows' => 0, 'warnings' => 0]);
            $ran++;
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

        return ['status' => 'pass', 'cleanup_allowed' => true, 'outcome' => 'verified'];
    }

    public function cleanup(array $verification, array $renameMap, array $state): array
    {
        $this->enter('cleanup');
        if ($this->cleanupRefuses) {
            throw new CleanupRefused('Cleanup is switched off.');
        }

        return ['dropped_tables' => [], 'removed_files' => 0];
    }
}

final class MigrationWizardPanelRunTest extends TestCase
{
    private string $dir;

    private PDO $pdo;

    private TimeBudget $budget;

    private PanelFakePhases $phases;

    private MigrationState $state;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/epesi-panel-'.bin2hex(random_bytes(6));
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE user_login (id INTEGER)');
        $this->budget = new TimeBudget;
        $this->phases = new PanelFakePhases($this->pdo, $this->budget);
        $this->state = new MigrationState($this->dir);
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
        return new MigrationRunner($this->state, new MigrationLock($this->dir), new LegacyTableNamespace, $this->pdo, $this->phases, $this->budget);
    }

    private function panel(float $seconds = 20.0): PanelRun
    {
        return new PanelRun($this->runner(), $this->state, $this->budget, $seconds);
    }

    private function toBackedUp(bool $confirmed = true): void
    {
        $this->state->update(0, ['phase' => 'assessed']);
        $this->state->update(1, [
            'phase' => 'backed_up',
            'preupgrade_backup' => $confirmed ? ['confirmed_at' => '2026-01-01T00:00:00+00:00'] : null,
        ]);
    }

    public function test_a_bounded_step_advances_saves_and_the_next_one_continues(): void
    {
        $this->toBackedUp();
        $seen = [];
        for ($i = 0; $i < 12; $i++) {
            // A budget of zero: every step does exactly one unit of work and hands back.
            $result = $this->panel(0.0)->advance();
            $seen[] = $result['phase'];
            if ($result['outcome'] === 'waiting') {
                break;
            }
            self::assertSame('progress', $result['outcome']);
            self::assertNotSame([], $result['steps'], 'every step made progress');
            // Saved: a fresh state reader sees the same phase.
            self::assertSame($result['phase'], (new MigrationState($this->dir))->read()['phase']);
        }

        self::assertSame('awaiting_admin_handoff', $result['stopped']);
        self::assertSame('importing', $result['phase']);
        self::assertSame(['frozen', 'tables_namespaced', 'epesi_installed', 'importing'], array_slice($seen, 0, 4));
        self::assertSame(['users', 'companies', 'contacts'], $this->state->read()['completed_importers']);
    }

    public function test_an_importer_that_stops_on_the_time_budget_saves_its_place_and_the_next_step_goes_on(): void
    {
        $this->toBackedUp();
        $this->phases->budgetStopsImport = true;
        // Get to the import with the full budget, then slice the import itself.
        $this->panel(0.0)->advance();
        $this->panel(0.0)->advance();
        $this->panel(0.0)->advance();
        $this->panel(0.0)->advance();
        self::assertSame('importing', $this->state->read()['phase']);

        $sliced = $this->panel(0.0)->advance();
        self::assertSame('progress', $sliced['outcome']);
        self::assertSame('time_budget', $sliced['stopped']);
        self::assertSame(['users'], $this->state->read()['completed_importers']);

        $this->panel(0.0)->advance();
        self::assertSame(['users', 'companies'], $this->state->read()['completed_importers']);
        $this->panel(0.0)->advance();
        self::assertSame(['users', 'companies', 'contacts'], $this->state->read()['completed_importers']);
    }

    public function test_two_runs_at_once_the_second_is_refused_and_nothing_changes(): void
    {
        $this->toBackedUp();
        $before = $this->state->read();

        // A command line run, or another browser tab, holds the lock.
        $result = (new MigrationLock($this->dir))->withLock('other run', fn (): array => $this->panel()->advance());

        self::assertSame('busy', $result['outcome']);
        self::assertStringContainsString('Another migration run is active', $result['message']);
        self::assertSame($before, $this->state->read());
        self::assertSame([], $this->phases->calls);

        // A lock left by a run that died is not held (the operating system released it), so nothing has to be deleted.
        self::assertSame('progress', $this->panel(0.0)->advance()['outcome']);
    }

    public function test_the_gates_apply_from_the_page_the_backup_confirmation(): void
    {
        $this->toBackedUp(confirmed: false);

        $result = $this->panel()->advance();

        self::assertSame('stopped', $result['outcome']);
        self::assertStringContainsString('Migration stopped. Fix the problem and run it again to continue.', $result['message']);
        self::assertStringContainsString('full backup', $result['message']);
        self::assertSame([], $this->phases->calls, 'not even the freeze ran');
        self::assertSame('backed_up', $this->state->read()['phase']);

        $this->panel()->confirmBackup();
        self::assertSame('progress', $this->panel(0.0)->advance()['outcome']);
    }

    public function test_the_page_runs_the_cleanup_right_after_verification_without_asking(): void
    {
        $this->toBackedUp();
        do {
            $result = $this->panel()->advance(new AdminCredentials(7, 'correct horse'));
        } while ($result['outcome'] === 'progress');

        self::assertSame('done', $result['outcome']);
        self::assertContains('cleanup', $this->phases->calls);
    }

    public function test_the_page_needs_a_legacy_installation_unless_a_migration_is_under_way(): void
    {
        self::assertFalse($this->panel()->legacyPresent(false), 'nothing detected and nothing begun');
        self::assertTrue($this->panel()->legacyPresent(true));

        $this->toBackedUp();
        $this->panel(0.0)->advance();
        self::assertTrue($this->panel()->legacyPresent(false), 'the legacy config may be gone once the migration began');
    }

    public function test_a_terminal_failure_offers_no_continue_and_shows_the_restore_message(): void
    {
        $this->toBackedUp();
        $this->phases->failAt = 'install';
        $this->phases->failureText = 'record 7 is corrupt';
        $result = ['outcome' => 'progress'];
        for ($i = 0; $i < 6 && $result['outcome'] === 'progress'; $i++) {
            $result = $this->panel()->advance();
        }

        self::assertSame('failed', $result['outcome']);
        self::assertStringContainsString('Migration failed and cannot continue.', $result['message']);
        self::assertStringContainsString('Reason: record 7 is corrupt (copy this before restoring)', $result['message']);
        self::assertStringContainsString('switch PHP back to 7.4', $result['message']);

        $view = $this->panel()->view();
        self::assertFalse($view['can_continue']);
        self::assertSame($result['message'], $view['failed_message']);
        self::assertSame('failed', $view['phase'], 'the status stays readable');

        $this->phases->failAt = null;
        $this->phases->calls = [];
        $again = $this->panel()->advance();
        self::assertSame('failed', $again['outcome']);
        self::assertSame([], $this->phases->calls, 'nothing runs after a terminal failure');
    }

    public function test_an_interruption_stops_with_the_message_and_continue_goes_on(): void
    {
        $this->toBackedUp();
        $this->phases->failAt = 'install';
        $this->phases->failureText = 'No space left on device';
        $result = ['outcome' => 'progress'];
        for ($i = 0; $i < 6 && $result['outcome'] === 'progress'; $i++) {
            $result = $this->panel()->advance();
        }

        self::assertSame('stopped', $result['outcome']);
        self::assertStringContainsString('Migration stopped. Fix the problem and run it again to continue.', $result['message']);
        self::assertStringContainsString('No space left on device', $result['message']);
        self::assertSame('tables_namespaced', $this->state->read()['phase']);
        self::assertTrue($this->panel()->view()['can_continue']);

        $this->phases->failAt = null;
        $next = $this->panel(0.0)->advance();
        self::assertSame('progress', $next['outcome']);
        self::assertSame('epesi_installed', $next['phase']);
    }

    public function test_the_page_exists_during_a_migration_and_after_a_failure_but_not_when_it_is_complete(): void
    {
        $panel = $this->panel();
        self::assertTrue($panel->available(false), 'before the migration, in a not yet installed application');
        self::assertFalse($panel->available(true), 'installed and no migration under way');

        $this->toBackedUp();
        foreach (['frozen', 'tables_namespaced', 'epesi_installed', 'importing', 'verified'] as $phase) {
            $this->state->update($this->state->read()['version'], ['phase' => $phase]);
            self::assertTrue($this->panel()->available(true), $phase);
        }
        $this->state->update($this->state->read()['version'], ['phase' => 'failed', 'failure' => ['reason' => 'x']]);
        self::assertTrue($this->panel()->available(true));
    }

    public function test_it_is_never_a_second_migration_path(): void
    {
        // The panel service holds a MigrationRunner and the state; it has no phase work of its own.
        $methods = array_map(static fn (ReflectionMethod $m): string => $m->getName(), (new ReflectionClass(PanelRun::class))->getMethods(ReflectionMethod::IS_PUBLIC));
        sort($methods);

        self::assertSame(['__construct', 'advance', 'available', 'confirmBackup', 'legacyPresent', 'view'], $methods);
    }
}
