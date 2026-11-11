<?php

namespace Epesi\Modules\MigrationWizard\Orchestration;

use App\Services\LegacyImport\TimeBudget;
use Epesi\Modules\MigrationWizard\Admin\AdminCredentials;
use Epesi\Modules\MigrationWizard\Report\MigrationTimes;
use Epesi\Modules\MigrationWizard\State\MigrationState;
use RuntimeException;
use Throwable;

/**
 * What the browser page does with the migration, and nothing more: it reads the same status as `--status` and advances
 * the SAME MigrationRunner (same state file, lock, ledger, gates) in bounded steps. A step runs the runner for a time
 * budget (default 45 seconds, below the usual 60-second web-server and proxy limits), which saves its place and returns; the page calls again until
 * the migration is done, stopped or failed. Closing the tab loses nothing: the next call (here or `--resume` on the
 * command line) continues from the state and the checkpoints.
 *
 * Outcomes of advance(): `progress` (call again), `waiting` (needs something from the administrator), `stopped` (an
 * interruption or a fixable problem: "Migration stopped..."; Continue again), `busy` (another run holds the lock, a
 * CLI run or another tab), `done`, `failed` (terminal: only the restore message is shown).
 */
final class PanelRun
{
    public const SECONDS = 45.0;

    public function __construct(
        private readonly MigrationRunner $runner,
        private readonly MigrationState $state,
        private readonly TimeBudget $budget,
        private readonly float $seconds = self::SECONDS,
    ) {}

    /** The page exists while a migration is under way or has failed; once it is complete it is gone. */
    public function available(bool $installed): bool
    {
        if (! $installed) {
            return true;
        }
        $phase = (string) ($this->state->read()['phase'] ?? 'detected');

        // Once the import created users the application counts as installed, but the migration is not over.
        return in_array($phase, [...array_slice(MigrationState::LADDER, 0, -1), MigrationState::FAILED, 'cleaned'], true);
    }

    /**
     * Nothing runs unless a legacy installation is detected, or a migration of one is already under way (the state
     * has left the early phases; the legacy config may be gone by then).
     */
    public function legacyPresent(bool $detected): bool
    {
        $phase = (string) ($this->state->read()['phase'] ?? 'detected');

        return $detected || ! in_array($phase, MigrationState::EARLY, true);
    }

    /** @return array<string, mixed> everything the page shows */
    public function view(): array
    {
        $status = $this->runner->status();
        $failure = $status['failure'];

        return $status + [
            'failed_message' => $failure === null ? null : MigrationFailed::message((string) ($failure['reason'] ?? 'unknown')),
            'import_started_text' => MigrationTimes::local($status['import_started_at'] ?? null),
            'import_finished_text' => MigrationTimes::local($status['import_finished_at'] ?? null),
            'import_duration_text' => MigrationTimes::elapsed($status['import_started_at'] ?? null, $status['import_finished_at'] ?? null),
            'finished' => in_array($status['phase'], MigrationState::FINAL, true),
            'can_continue' => $failure === null && ! in_array($status['phase'], MigrationState::FINAL, true),
        ];
    }

    /**
     * One bounded step.
     *
     * @return array{outcome: string, message: ?string, phase: string, steps: list<string>, stopped: ?string}
     */
    public function advance(?AdminCredentials $admin = null, bool $cleanup = false): array
    {
        $outcome = static fn (string $outcome, ?string $message, string $phase, array $steps = [], ?string $stopped = null): array => compact('outcome', 'message', 'phase', 'steps', 'stopped');
        $state = $this->state->read();
        $phase = (string) ($state['phase'] ?? '');

        if ($phase === MigrationState::FAILED) {
            return $outcome('failed', MigrationFailed::message((string) ($state['failure']['reason'] ?? 'unknown')), $phase);
        }

        $this->budget->start($this->seconds);
        try {
            $result = $this->runner->resume($admin, $cleanup);
        } catch (MigrationFailed $e) {
            return $outcome('failed', $e->getMessage(), MigrationState::FAILED);
        } catch (MigrationStopped $e) {
            return $outcome('stopped', $e->getMessage(), (string) ($this->state->read()['phase'] ?? ''));
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'Another migration run is active.') {
                return $outcome('busy', 'Another migration run is active (the command line or another browser tab). Wait for it to finish; this page goes on by itself.', $phase);
            }

            return $outcome('stopped', MigrationStopped::message($e->getMessage()), $phase);
        } catch (Throwable $e) {
            return $outcome('stopped', MigrationStopped::message($e->getMessage()), $phase);
        } finally {
            $this->budget->clear();
        }

        $stopped = $result['stopped'];
        if ($stopped === null) {
            return $outcome(in_array($result['phase'], MigrationState::FINAL, true) ? 'done' : 'progress', null, $result['phase'], $result['steps']);
        }
        if ($stopped === 'time_budget') {
            return $outcome('progress', null, $result['phase'], $result['steps'], $stopped);
        }

        return $outcome('waiting', $this->waitingText($stopped), $result['phase'], $result['steps'], $stopped);
    }

    public function confirmBackup(): array
    {
        return $this->runner->confirmPreUpgradeBackup();
    }

    private function waitingText(string $stopped): string
    {
        return match ($stopped) {
            'awaiting_admin_handoff' => 'Choose which legacy administrator will own the installation and set a new password.',
            'verification_blocked' => 'Verification found a problem that needs a look; see the checks above.',
            default => $stopped,
        };
    }
}
