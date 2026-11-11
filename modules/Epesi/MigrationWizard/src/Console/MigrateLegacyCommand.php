<?php

namespace Epesi\Modules\MigrationWizard\Console;

use Epesi\Modules\MigrationWizard\Admin\AdminCredentials;
use Epesi\Modules\MigrationWizard\Admin\AdministratorHandoff;
use Epesi\Modules\MigrationWizard\Checks\AssessmentSummary;
use Epesi\Modules\MigrationWizard\Checks\MigrationDryRun;
use Epesi\Modules\MigrationWizard\Checks\MigrationExecutionAdvisor;
use Epesi\Modules\MigrationWizard\Cleanup\CleanupPreview;
use Epesi\Modules\MigrationWizard\Detection\LegacyInstallation;
use Epesi\Modules\MigrationWizard\Orchestration\MigrationFailed;
use Epesi\Modules\MigrationWizard\Orchestration\MigrationRunner;
use Epesi\Modules\MigrationWizard\Orchestration\MigrationStopped;
use Epesi\Modules\MigrationWizard\Orchestration\OperatorChoices;
use Epesi\Modules\MigrationWizard\Orchestration\RunnerFactory;
use Epesi\Modules\MigrationWizard\State\MigrationState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Command-line entry point: read-only assessment (--status, --dry-run) and the
 * state-machine driver --resume (see MigrationRunner), plus
 * --confirm-backup and the operator's choices (--preserve, --discard, --core-only). The
 * administrator password is read from a hidden prompt or stdin, never from an
 * option, and is never printed or stored.
 */
final class MigrateLegacyCommand extends Command
{
    protected $signature = 'epesi:migrate-legacy {--status : Show migration capability status} {--cleanup-preview : Show what the last step would remove, table by table and folder by folder, and why; changes nothing} {--dry-run : Assess legacy schema and summarise the installed modules with data and their record counts} {--full : With --dry-run, print the full report including every table (default: short summary; the full report is also saved as JSON)} {--resume : Continue the migration from the last completed phase} {--admin-id= : Legacy user_login id of the administrator who will own the installation} {--admin-email= : With --admin-id, set this e-mail address on the administrator account} {--admin-password-stdin : Read the new administrator password from the first line of stdin} {--cleanup : With --resume, also run the final cleanup once verification passed} {--yes : Do not ask for confirmation before --discard (or when confirming the backup)} {--confirm-backup : Confirm that you made a full backup of the database and of the full Epesi directory (Softaculous or another tool) BEFORE the upgrade (required before the migration changes anything, and before --cleanup)} {--preserve= : Comma-separated exact legacy tables / file areas (data/<dir>) to keep, or @unknown for everything unclassified} {--discard= : Comma-separated EXACT legacy tables / file areas approved for removal at cleanup (never a default)} {--core-only : Continue with Epesi Core only: known-but-not-migrated data is preserved}';

    protected $description = 'Inspect legacy EPESI migration readiness, resume a migration';

    public function handle(): int
    {
        if ($this->option('cleanup-preview')) {
            return $this->cleanupPreview();
        }
        if ($this->option('confirm-backup')) {
            return $this->confirmBackup();
        }
        if ($this->hasChoiceOptions() && ! $this->storeChoices()) {
            return self::FAILURE;
        }
        if ($this->option('status')) {
            return $this->status();
        }
        if ($this->option('resume')) {
            return $this->resume();
        }
        if ($this->hasChoiceOptions()) {
            return $this->status(); // choices stored; show them
        }
        if (! $this->option('dry-run')) {
            $this->error('Use --status, --dry-run or --resume.');

            return self::FAILURE;
        }
        try {
            $root = base_path();
            $report = app(LegacyInstallation::class)->assess($root.'/data/config.php');
            $preview = app(MigrationDryRun::class)->preview($report);
            $advice = app(MigrationExecutionAdvisor::class)->assess($report, $root);
            $head = [
                'mode' => 'read_only',
                'legacy_signature' => $report['legacy_signature'],
                'schema_fingerprint' => $report['fingerprint'],
                'version' => $report['version'],
                'version_approved' => $report['compatible'],
                'blocked_modules' => $report['blocked_modules'],
            ];
            if ($this->option('full')) {
                $this->line(json_encode($head + ['preview' => $preview, 'execution_advice' => $advice], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }
            // Default: a short summary (modules and record counts, no per-table list).
            $summaries = app(AssessmentSummary::class);
            $full = $report + ['dry_run_preview' => $preview, 'execution_advice' => $advice];
            $summary = $summaries->summarize($full);
            $summary['full_report'] = $summaries->save($full);
            $this->line(json_encode($head + ['summary' => $summary, 'execution_advice' => $advice], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Legacy assessment failed. Check configuration and server logs; no migration was started.');

            return self::FAILURE;
        }
    }

    private function cleanupPreview(): int
    {
        try {
            $preview = RunnerFactory::phases()->cleanupPreview($this->state()->read() ?? []);
            foreach ((new CleanupPreview)->toLines($preview) as $line) {
                $this->line($line);
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('The cleanup preview failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function confirmBackup(): int
    {
        try {
            $confirmed = $this->option('yes') || ($this->input->isInteractive() && $this->ask('Type "backed up" to confirm that you made a full backup of the database and of the full Epesi directory BEFORE the upgrade') === 'backed up');
            if (! $confirmed) {
                throw new RuntimeException('The backup was not confirmed (type "backed up", or pass --yes).');
            }
            $state = $this->runner()->confirmPreUpgradeBackup();
            $this->line(json_encode(['preupgrade_backup_confirmed' => true, 'confirmed_at' => $state['preupgrade_backup']['confirmed_at']], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Backup not confirmed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function hasChoiceOptions(): bool
    {
        return $this->option('preserve') !== null || $this->option('discard') !== null || (bool) $this->option('core-only');
    }

    /** Validate and store --preserve / --discard / --core-only. Discarding needs an explicit confirmation. */
    private function storeChoices(): bool
    {
        try {
            $choices = OperatorChoices::merge($this->runner()->status()['operator_choices'], [
                'preserve' => $this->stringOption('preserve'),
                'discard' => $this->stringOption('discard'),
                'core_only' => (bool) $this->option('core-only'),
            ]);
            $discard = $this->stringOption('discard');
            if ($discard !== null && $choices['discard'] !== []) {
                $this->warn('These legacy tables / file areas will be REMOVED at cleanup: '.implode(', ', $choices['discard']));
                $confirmed = $this->option('yes') || ($this->input->isInteractive() && $this->ask('Type "discard" to approve exactly these') === 'discard');
                if (! $confirmed) {
                    throw new RuntimeException('Discarding was not confirmed (type "discard", or pass --yes).');
                }
            }
            $this->runner()->setChoices($choices);

            return true;
        } catch (Throwable $e) {
            $this->error('Choices not stored: '.$e->getMessage());

            return false;
        }
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) ? $value : null;
    }

    private function status(): int
    {
        try {
            $status = $this->runner()->status();
            $this->line(json_encode(['migration' => $status], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            if (is_array($status['failure'])) {
                $this->error(MigrationFailed::message((string) ($status['failure']['reason'] ?? 'unknown')));
            }

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('The migration state could not be read; treat it as unknown and do not continue.');

            return self::FAILURE;
        }
    }

    private function resume(): int
    {
        try {
            $admin = $this->credentials();
            $result = $this->runner()->resume($admin, (bool) $this->option('cleanup'));
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $leftBehind = $this->runner()->status()['left_behind'];
            if ($leftBehind !== null) {
                $this->line(json_encode(['left_behind' => $leftBehind], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            }
            if ($result['stopped'] === 'awaiting_admin_handoff') {
                $this->listAdministrators();
                $this->warn('Run again with --admin-id=<id> and a password (hidden prompt or --admin-password-stdin).');
            }

            return $result['stopped'] === 'verification_blocked' ? self::FAILURE : self::SUCCESS;
        } catch (MigrationFailed $e) {
            // Failed for good: the reason is in the state, and every later run shows this again.
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (MigrationStopped $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            // Messages here are written by this module and never contain secrets.
            $this->error(MigrationStopped::message($e->getMessage()));

            return self::FAILURE;
        }
    }

    /** The administrator choice, only when --admin-id is given. The password is never an option. */
    private function credentials(): ?AdminCredentials
    {
        $id = $this->option('admin-id');
        if ($id === null || $id === '') {
            return null;
        }
        if (! ctype_digit((string) $id)) {
            throw new RuntimeException('--admin-id must be a legacy user id.');
        }
        if ($this->option('admin-password-stdin')) {
            $line = fgets(STDIN);
            $password = $line === false ? '' : rtrim($line, "\r\n");
        } elseif ($this->input->isInteractive()) {
            $password = (string) $this->secret('New administrator password');
            if ($password !== (string) $this->secret('Repeat the password')) {
                throw new RuntimeException('The passwords do not match.');
            }
        } else {
            throw new RuntimeException('No password source: use the hidden prompt or --admin-password-stdin.');
        }

        $email = $this->option('admin-email');

        return new AdminCredentials((int) $id, $password, is_string($email) && $email !== '' ? $email : null);
    }

    private function listAdministrators(): void
    {
        $map = $this->state()->read()['rename_map'] ?? null;
        $table = is_array($map) ? ($map['user_login'] ?? null) : null;
        if (! is_string($table)) {
            return;
        }
        foreach ((new AdministratorHandoff)->candidates(DB::connection()->getPdo(), $table) as $candidate) {
            $this->line("  --admin-id={$candidate['id']}  {$candidate['login']}  (level {$candidate['level']})");
        }
    }

    private function state(): MigrationState
    {
        return RunnerFactory::state();
    }

    private function runner(): MigrationRunner
    {
        return RunnerFactory::make();
    }
}
