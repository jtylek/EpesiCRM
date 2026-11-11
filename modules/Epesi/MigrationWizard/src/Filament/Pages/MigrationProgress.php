<?php

namespace Epesi\Modules\MigrationWizard\Filament\Pages;

use App\Services\LegacyImport\TimeBudget;
use App\Support\Demo;
use App\Support\Setup\SetupState;
use Epesi\Modules\MigrationWizard\Admin\AdminCredentials;
use Epesi\Modules\MigrationWizard\Admin\AdministratorHandoff;
use Epesi\Modules\MigrationWizard\Background\BackgroundRunner;
use Epesi\Modules\MigrationWizard\Detection\LegacyPresence;
use Epesi\Modules\MigrationWizard\Import\ImportProgress;
use Epesi\Modules\MigrationWizard\Orchestration\PanelRun;
use Epesi\Modules\MigrationWizard\Orchestration\RunnerFactory;
use Epesi\Modules\MigrationWizard\Report\MigrationReport;
use Filament\Facades\Filament;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The migration from the browser: the same state and status as `epesi:migrate-legacy --status`, and a Continue that
 * advances the very same state machine (PanelRun / MigrationRunner: same lock, ledger and gates) in bounded steps.
 * While `running` is on, the page calls `advance()` every few seconds; closing the tab only stops the polling.
 *
 * There is no setup code. The page is not available in demo mode, nor once the migration is complete, and nothing
 * runs unless a legacy installation is detected (data/config.php) or a migration of one is under way; every action
 * checks that again. The backup is the administrator's to make; the page asks to confirm it by typing.
 */
final class MigrationProgress extends SimplePage
{
    private const BACKGROUND_MESSAGE = 'The migration now runs on the server as a background process. You can close this page and come back.';

    protected Width|string|null $maxWidth = Width::FiveExtraLarge;

    public string $typed = '';

    public ?int $adminId = null;

    public string $password = '';

    public string $passwordAgain = '';

    /** What is wrong with the administrator form; shown right above its button. */
    public ?string $adminError = null;

    public bool $cleanup = false;

    public bool $running = false;

    /** The migration runs as a background process on the server; the page only watches it. */
    public bool $background = false;

    public int $backgroundStartedAt = 0;

    public ?string $message = null;

    public string $outcome = '';

    /** The panel is shared with the install wizard ("epesi setup"); these pages are the migration wizard. */
    public function boot(): void
    {
        Filament::getCurrentPanel()?->brandName('epesi migration wizard');
    }

    public function mount(): void
    {
        // Opened (or refreshed) after the migration finished: the page is over, the new Epesi's sign-in is next.
        if (! Demo::enabled() && in_array(RunnerFactory::state()->read()['phase'] ?? null, ['complete', 'core_complete_with_preserved_data'], true)) {
            $this->redirect(url('/'));

            return;
        }
        $this->ensureAvailable();
        $this->followBackground();
    }

    /**
     * A background process this page (or an earlier visit of it) started is still working: watch it, so a reopened
     * or refreshed page shows its progress instead of "another run is active".
     */
    private function followBackground(): bool
    {
        $started = BackgroundRunner::startedAt();
        if ($started === null) {
            return false;
        }
        if (! $this->run()->view()['lock_held']) {
            // Seen to have ended (give a just-started process a moment to take the lock).
            if (time() - $started >= 15) {
                BackgroundRunner::forget();
            }

            return false;
        }
        $this->running = true;
        $this->background = true;
        $this->backgroundStartedAt = $started;
        $this->message = self::BACKGROUND_MESSAGE;

        return true;
    }

    private function ensureAvailable(): void
    {
        $run = $this->run();
        abort_if(Demo::enabled() || ! $run->available(SetupState::isInstalled()) || ! $run->legacyPresent(LegacyPresence::detected()), 404);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Legacy Epesi migration';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('migration-wizard::progress')->viewData(fn (): array => $this->data()),
        ]);
    }

    public function confirmBackup(): void
    {
        $this->act(function (): void {
            $this->requireTyped('backed up');
            $this->run()->confirmBackup();
            $this->message = 'Your backup before the upgrade is confirmed.';
        });
    }

    /** The administrator step: check what was typed, then go on with the next step (verification). */
    public function saveAdministratorAndContinue(): void
    {
        $this->ensureAvailable();
        $this->adminError = $this->message = null;
        if ($this->adminId === null) {
            $this->adminError = 'Choose the legacy administrator.';

            return;
        }
        if (mb_strlen($this->password) < 8) {
            $this->adminError = 'The new password needs 8 characters or more.';

            return;
        }
        if ($this->password !== $this->passwordAgain) {
            $this->adminError = 'The passwords do not match.';

            return;
        }
        // The password is never handed to a background process: this step and the rest run in short browser steps.
        // Like Continue, the step starts with the next poll, so the status lines show while it runs.
        $this->running = true;
    }

    /**
     * Start (or go on). When the server allows it, the import runs as a background process (no browser or web-server
     * time limit can cut it short); otherwise one bounded step now, and more every few seconds until it is done or
     * needs you.
     */
    public function start(): void
    {
        $this->running = true;
        if ($this->leaveWhenFinished()) {
            return;
        }
        if ($this->mayRunInBackground() && BackgroundRunner::start()) {
            $this->background = true;
            $this->backgroundStartedAt = time();
            $this->message = self::BACKGROUND_MESSAGE;

            return;
        }
        // The first step starts with the page's next poll (in about two seconds), not in this request: this answer
        // comes back at once, so the status lines (filled from progress.json) are on the screen while the step runs.
        $this->message = null;
    }

    /** Only the long part (up to the end of the import) and only when nothing else is working on it. */
    private function mayRunInBackground(): bool
    {
        $view = $this->run()->view();

        return $view['can_continue']
            && ! $view['lock_held']
            && ! $view['awaiting_admin_handoff']
            && in_array($view['phase'], ['backed_up', 'frozen', 'tables_namespaced', 'epesi_installed', 'importing'], true)
            && BackgroundRunner::capability()['available'];
    }

    /** While the background process works the page only looks; it ends when the process no longer holds the lock. */
    private function watchBackground(): void
    {
        $view = $this->run()->view();
        if ($view['lock_held'] || time() - $this->backgroundStartedAt < 15) {
            return;
        }
        $this->background = false;
        $this->running = false;
        $this->message = null;
        BackgroundRunner::forget();
        if ($view['awaiting_admin_handoff']) {
            $this->message = 'The records are imported. Choose the administrator below.';
        } elseif ($view['failure'] === null && ! $view['finished']) {
            $log = is_file(BackgroundRunner::logPath()) ? trim(implode(PHP_EOL, array_slice(file(BackgroundRunner::logPath(), FILE_IGNORE_NEW_LINES) ?: [], -6))) : '';
            $this->message = 'The background process ended before the migration finished. Press Continue to go on.'.($log === '' ? '' : PHP_EOL.$log);
        }
    }

    public function advance(): void
    {
        if ($this->leaveWhenFinished()) {
            return;
        }
        $this->ensureAvailable();
        if ($this->background || $this->followBackground()) {
            $this->watchBackground();

            return;
        }
        // A step is a unit of work that must not be cut off by a closed tab or the PHP time limit.
        ignore_user_abort(true);
        @set_time_limit(0);

        $admin = null;
        if ($this->adminId !== null && $this->password !== '') {
            if ($this->password !== $this->passwordAgain) {
                $this->running = false;
                $this->message = 'The passwords do not match.';

                return;
            }
            $admin = new AdminCredentials($this->adminId, $this->password);
        }
        $result = $this->run()->advance($admin, $this->cleanup);
        if ($this->leaveWhenFinished()) {
            return;
        }
        $this->outcome = $result['outcome'];
        $this->message = $result['message'];
        // Keep going only while there is progress; everything else needs a look.
        $this->running = $this->running && $result['outcome'] === 'progress';
        if ($result['outcome'] === 'busy') {
            $this->running = true; // another run is working: look again in a moment
        }
        if ($result['steps'] !== [] || $result['outcome'] !== 'waiting') {
            $this->password = $this->passwordAgain = '';
        }
    }

    /**
     * The migration is finished: this page is gone (it would answer 404, also to the step that just finished it), so
     * a page that is still running goes to the sign-in of the new Epesi, without rendering itself again. It stays
     * `running`: without a render its poll goes on until the browser has left, and each poll must come back here.
     */
    private function leaveWhenFinished(): bool
    {
        if (! $this->running || ! in_array(RunnerFactory::state()->read()['phase'] ?? null, ['complete', 'core_complete_with_preserved_data'], true)) {
            return false;
        }
        $this->skipRender();
        $this->redirect(url('/'));

        return true;
    }

    /** @param  callable(): void  $action */
    private function act(callable $action): void
    {
        $this->ensureAvailable();
        try {
            $action();
        } catch (Throwable $e) {
            $this->message = $e->getMessage();
        } finally {
            $this->typed = '';
        }
    }

    private function requireTyped(string $word): void
    {
        if (trim($this->typed) !== $word) {
            throw new \RuntimeException("Type \"{$word}\" to confirm.");
        }
    }

    private function run(): PanelRun
    {
        return new PanelRun(RunnerFactory::make(), RunnerFactory::state(), app(TimeBudget::class));
    }

    /** @return array<string, mixed> */
    private function data(): array
    {
        $this->ensureAvailable();
        $view = $this->run()->view();

        return [
            'status' => $view + ['has_report' => MigrationReport::exists()],
            'administrators' => $this->administrators($view),
            'running' => $this->running,
            'background' => property_exists($this, 'background') && $this->background,
            'reportUrl' => route('filament.setup.migrate.summary'),
            'logUrl' => route('filament.setup.migrate.summary.file', ['format' => 'txt']),
            'logDownloadUrl' => route('filament.setup.migrate.summary.file', ['format' => 'txt', 'download' => 1]),
            'progressLine' => ImportProgress::line(),
            'progressSpeed' => ImportProgress::speed(),
            'progressEstimate' => ImportProgress::estimate(),
            'progressJsonUrl' => route('filament.setup.migrate.progress.json'),
            'backgroundOffer' => $this->running || ! ($view['can_continue'] ?? false) || ($view['awaiting_admin_handoff'] ?? false) || ! in_array($view['phase'], ['backed_up', 'frozen', 'tables_namespaced', 'epesi_installed', 'importing'], true) ? null : BackgroundRunner::capability(),
            'message' => $this->message,
            'outcome' => $this->outcome,
        ];
    }

    /** @return list<array{id: int, login: string, level: int}> */
    private function administrators(array $view): array
    {
        $map = RunnerFactory::state()->read()['rename_map'] ?? null;
        $table = is_array($map) ? ($map['user_login'] ?? null) : null;
        if (! is_string($table) || ($view['admin_handoff_done'] ?? false)) {
            return [];
        }
        try {
            return array_values((new AdministratorHandoff)->candidates(DB::connection()->getPdo(), $table));
        } catch (Throwable) {
            return [];
        }
    }
}
