<?php

namespace Epesi\Modules\MigrationWizard\Orchestration;

use App\Services\LegacyImport\TimeBudget;
use App\Services\LegacyImport\TimeBudgetReached;
use Epesi\Modules\MigrationWizard\Admin\AdminCredentials;
use Epesi\Modules\MigrationWizard\Cleanup\CleanupRefused;
use Epesi\Modules\MigrationWizard\Cleanup\LegacyJunk;
use Epesi\Modules\MigrationWizard\Database\LegacyModuleTables;
use Epesi\Modules\MigrationWizard\Database\LegacyTableNamespace;
use Epesi\Modules\MigrationWizard\Database\ObsoleteLegacyTables;
use Epesi\Modules\MigrationWizard\Database\UnownedLegacyData;
use Epesi\Modules\MigrationWizard\Import\ImportProgress;
use Epesi\Modules\MigrationWizard\Report\MigrationReport;
use Epesi\Modules\MigrationWizard\State\MigrationLock;
use Epesi\Modules\MigrationWizard\State\MigrationState;
use Epesi\Modules\MigrationWizard\Verification\MigrationVerifier;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Drives the migration state machine for the CLI (and later the wizard):
 *
 *   (detected | assessed) -> backed_up (the typed backup confirmation) -> frozen -> tables_namespaced -> epesi_installed -> importing
 *             -> verified -> cleaned -> complete | core_complete_with_preserved_data
 *
 * Every public run holds MigrationLock, so only one run is active. A phase is
 * recorded only after its work finished, and each piece of work is idempotent,
 * so resume() continues from the last completed phase. The exact table rename
 * map is persisted in the state BEFORE the first table is renamed.
 *
 * There is no way back inside the wizard: a failure is either a stop (an interruption or a
 * problem the administrator can fix; MigrationStopped; --resume continues) or an end (the
 * `failed` phase; MigrationFailed; the remedy is to restore the pre-upgrade backup). A failure is
 * a stop only when continuing is known to be safe, and an end otherwise.
 *
 * Fail closed: an unknown phase, a corrupt saved map or a refused transition
 * stops the run with an exception and changes nothing further.
 */
final class MigrationRunner
{
    public function __construct(
        private readonly MigrationState $state,
        private readonly MigrationLock $lock,
        private readonly LegacyTableNamespace $namespace,
        private readonly PDO $pdo,
        private readonly MigrationPhases $phases,
        private readonly ?TimeBudget $budget = null,
        // The legacy installation's root, for the unowned data/ folders; without it only their tables are removed.
        private readonly ?string $legacyRoot = null,
    ) {}

    /** Read-only summary; never takes the lock and never mutates anything. */
    public function status(): array
    {
        $state = $this->state->read();
        $phase = (string) ($state['phase'] ?? '');

        return [
            'phase' => $phase,
            'known_phase' => MigrationState::known($phase),
            'version' => $state['version'] ?? 0,
            'lock_held' => $this->lock->isHeld(),
            'rename_map_tables' => is_array($state['rename_map'] ?? null) ? count($state['rename_map']) : 0,
            'admin_handoff_done' => (bool) ($state['admin_handoff']['done'] ?? false),
            'import_started_at' => is_string($state['import_started_at'] ?? null) ? $state['import_started_at'] : null,
            'import_finished_at' => is_string($state['import_finished_at'] ?? null) ? $state['import_finished_at'] : null,
            'import_results' => is_array($state['import_results'] ?? null) ? $state['import_results'] : [],
            'table_certification' => is_array($state['verification']['table_certification'] ?? null) ? $state['verification']['table_certification'] : [],
            // True once the import has finished and only the administrator's new password is missing.
            'awaiting_admin_handoff' => (bool) ($state['awaiting_admin_handoff'] ?? false) && ! ($state['admin_handoff']['done'] ?? false),
            'preupgrade_backup_confirmed' => is_array($state['preupgrade_backup'] ?? null),
            'completed_importers' => $this->stringList($state['completed_importers'] ?? []),
            'operator_choices' => OperatorChoices::normalise($state['operator_choices'] ?? null),
            'verification_outcome' => $state['verification']['outcome'] ?? null,
            // What the migration kept but did not migrate (LeftBehindReport), once verification has run.
            'left_behind' => is_array($state['verification']['left_behind'] ?? null) ? $state['verification']['left_behind'] : null,
            // Built-in obsolete tables (MigrationVerifier::OBSOLETE_TABLES): found, and dropped once verification passed.
            'obsolete_tables' => $this->stringList($state['verification']['obsolete_tables'] ?? []),
            'obsolete_tables_dropped' => $this->stringList($state['obsolete_drop']['dropped'] ?? []),
            // Set when the migration failed and cannot continue: the reason and where it happened. Reading it needs no lock.
            'failure' => is_array($state['failure'] ?? null) ? $state['failure'] : null,
        ];
    }

    public const BACKUP_REQUIRED = 'Nothing was changed: the migration needs a full backup made BEFORE the upgrade, of the whole database and of the whole Epesi directory (all files, the old ones included). A failed migration is fixed by restoring that backup; there is no other complete way back. Make it yourself (Softaculous or another tool), then run: php artisan epesi:migrate-legacy --confirm-backup';

    /**
     * The operator's explicit statement that a full backup of the database and of the full Epesi
     * directory was made BEFORE the upgrade (the ZIP overlay). Never defaults to true; required before the
     * wizard changes anything (the freeze, the rename, the import, any file move); not needed by the
     * read-only commands. Not possible once the migration has started.
     */
    public function confirmPreUpgradeBackup(): array
    {
        return $this->lock->withLock('migrate-legacy confirm-backup', function (): array {
            $state = $this->state->read();
            $phase = (string) ($state['phase'] ?? '');
            if (! in_array($phase, MigrationState::EARLY, true)) {
                throw new RuntimeException('The migration has already begun; the pre-upgrade backup can only be confirmed before it.');
            }

            // The only backup gate: the phase moves to backed_up.
            return $this->state->update($state['version'], ['phase' => 'backed_up', 'preupgrade_backup' => ['confirmed_at' => gmdate('c')]]);
        });
    }

    /**
     * Store the operator's choices about legacy data no importer migrates. Allowed until
     * verification has passed: changing a choice afterwards would invalidate the report.
     *
     * @param  array{preserve: list<string>, discard: list<string>, core_only: bool}  $choices
     */
    public function setChoices(array $choices): array
    {
        return $this->lock->withLock('migrate-legacy choices', function () use ($choices): array {
            $state = $this->state->read();
            $phase = (string) ($state['phase'] ?? '');
            if (! in_array($phase, [...MigrationState::EARLY, 'frozen', 'tables_namespaced', 'epesi_installed', 'importing'], true)) {
                throw new RuntimeException('The choices can no longer be changed in the "'.$phase.'" phase.');
            }

            return $this->state->update($state['version'], ['operator_choices' => $choices]);
        });
    }

    /**
     * Continue from the last completed phase until the run is complete or needs input.
     *
     * @param  AdminCredentials|null  $admin  needed once, after the import, to hand the installation to a legacy administrator
     * @param  bool  $cleanup  also run the final cleanup once verification passed
     * @return array{phase: string, steps: list<string>, stopped: string|null}
     */
    public function resume(?AdminCredentials $admin = null, bool $cleanup = false): array
    {
        return $this->lock->withLock('migrate-legacy resume', function () use ($admin, $cleanup): array {
            $steps = [];
            while (true) {
                $state = $this->state->read();
                $phase = $state['phase'] ?? null;
                if (! is_string($phase) || ! MigrationState::known($phase)) {
                    throw new RuntimeException('Unknown migration state; refusing to continue.');
                }
                if ($phase === MigrationState::FAILED) {
                    throw new MigrationFailed((string) ($state['failure']['reason'] ?? 'unknown'));
                }
                $stopped = null;
                try {
                    $stopped = $this->step($phase, $state, $admin, $cleanup, $steps);
                } catch (MigrationStopped|MigrationFailed $e) {
                    throw $e;
                } catch (TimeBudgetReached) {
                    // Not an error: the slice's time is used up and the work saved its place. Run again to go on.
                    return ['phase' => (string) $this->state->read()['phase'], 'steps' => $steps, 'stopped' => 'time_budget'];
                } catch (Throwable $e) {
                    throw $this->classifyFailure($phase, $e);
                }
                if ($stopped === self::DONE) {
                    return ['phase' => (string) $this->state->read()['phase'], 'steps' => $steps, 'stopped' => null];
                }
                if ($stopped !== null) {
                    return ['phase' => (string) $this->state->read()['phase'], 'steps' => $steps, 'stopped' => $stopped];
                }
                if ($this->budget?->exceeded()) {
                    return ['phase' => (string) $this->state->read()['phase'], 'steps' => $steps, 'stopped' => 'time_budget'];
                }
            }
        });
    }

    private const DONE = "\0done";

    /**
     * One state-machine step. Returns a stop reason, self::DONE for a final
     * phase, or null to continue with the next phase.
     *
     * @param  list<string>  $steps
     */
    private function step(string $phase, array $state, ?AdminCredentials $admin, bool $cleanup, array &$steps): ?string
    {
        $stopped = null;
        switch ($phase) {
            case 'detected':
            case 'assessed':
                throw new RuntimeException(self::BACKUP_REQUIRED);
            case 'backed_up':
                if (! is_array($state['preupgrade_backup'] ?? null)) {
                    throw new RuntimeException(self::BACKUP_REQUIRED);
                }
                $state = $this->purgeJunkBeforeFreeze($state);
                $this->phases->freeze($state);
                $this->state->update($state['version'], ['phase' => 'frozen']);
                $steps[] = 'freeze';
                break;
            case 'frozen':
                $this->namespaceTables($state);
                $steps[] = 'namespace_tables';
                break;
            case 'tables_namespaced':
                $modules = $this->phases->install($this->map($state));
                $this->state->update($state['version'], ['phase' => 'epesi_installed', 'installed_modules' => $modules]);
                $steps[] = 'install';
                break;
            case 'epesi_installed':
                $this->state->update($state['version'], ['phase' => 'importing'] + (isset($state['import_started_at']) ? [] : ['import_started_at' => gmdate('c')]));
                $steps[] = 'start_import';
                break;
            case 'importing':
                $stopped = $this->importAndVerify($state, $admin, $steps);
                break;
            case 'verified':
                try {
                    // Success: verification passed. The obsolete legacy tables go now, whatever the cleanup switch says.
                    if ($this->dropObsoleteTables($state)) {
                        $steps[] = 'drop_obsolete_tables';
                        $state = $this->state->read();
                    }
                    // ...and so does the data nobody owns, without asking.
                    if ($this->removeUnowned($state)) {
                        $steps[] = 'remove_unowned';
                        $state = $this->state->read();
                    }
                    $stopped = $this->afterVerified($state, $cleanup, $steps);
                } finally {
                    ImportProgress::clear();
                }
                break;
            case 'cleaned':
                $outcome = $state['verification']['outcome'] ?? null;
                $this->state->update($state['version'], [
                    'phase' => $outcome === 'core_complete_with_preserved_data' ? 'core_complete_with_preserved_data' : 'complete',
                ]);
                $steps[] = 'complete';
                break;
            default: // complete, core_complete_with_preserved_data
                return self::DONE;
        }

        return $stopped;
    }

    /**
     * Sort a failure into a stop or an end. A stop (MigrationStopped, resume continues) only when continuing
     * is known to be safe: anything before the wizard changed a thing (the confirmation, the freeze), a
     * failure that looks like an interruption (disk space, a timeout, a lost connection, another run holding
     * the lock), the administrator step (a wrong account or password), and anything after verification
     * passed (drops, removal of unowned data, cleanup: all safe to repeat). Everything else after the
     * first change (a failed install, an importer or data problem) is an end:
     * the reason is recorded in the state and the run is `failed` for good.
     */
    private function classifyFailure(string $phase, Throwable $failure): Throwable
    {
        // Frozen with no rename map saved yet: not one table was renamed, so nothing has been changed either.
        $untouched = $phase === 'frozen' && ! is_array($this->state->read()['rename_map'] ?? null);
        // After verification everything left (dropping obsolete tables, removing unowned data, cleanup) is safe to repeat.
        $repeatable = in_array($phase, ['verified', 'cleaned'], true);
        if ($untouched || $repeatable || in_array($phase, MigrationState::EARLY, true) || $failure instanceof CleanupRefused || MigrationStopped::isInterruption($failure)) {
            return new MigrationStopped($failure->getMessage(), $failure);
        }
        $reason = $failure->getMessage();
        try {
            $current = $this->state->read();
            $this->state->update($current['version'], ['phase' => MigrationState::FAILED, 'failure' => [
                'reason' => $reason,
                'failed_in' => $phase,
                'failed_at' => gmdate('c'),
            ]]);
        } catch (Throwable) {
            // The state could not record it; the run still ends here and the message says so.
        }

        return new MigrationFailed($reason, $failure);
    }

    private function namespaceTables(array $state): void
    {
        if (! array_key_exists('rename_map', $state)) {
            $existing = $this->namespace->tables($this->pdo);
            $map = $this->namespace->plan($this->phases->legacyTables(), $existing);
            // Persist the exact map (and what else lives in the database) BEFORE the first rename.
            $state = $this->state->update($state['version'], ['rename_map' => $map]);
        }
        $map = $this->map($state);
        $this->namespace->apply($this->pdo, $map);
        $this->state->update($state['version'], ['phase' => 'tables_namespaced']);
    }

    /** @param  list<string>  $steps */
    private function importAndVerify(array $state, ?AdminCredentials $admin, array &$steps): ?string
    {
        $map = $this->map($state);
        $this->phases->import($this->stringList($state['completed_importers'] ?? []), function (string $tab, array $result): void {
            // Recorded the moment an importer finishes, so a resume skips it.
            $current = $this->state->read();
            $done = $this->stringList($current['completed_importers'] ?? []);
            $done[] = $tab;
            $this->state->update($current['version'], [
                'completed_importers' => array_values(array_unique($done)),
                'import_results' => [...(is_array($current['import_results'] ?? null) ? $current['import_results'] : []), $tab => $result],
            ]);
        }, $map);
        $state = $this->state->read();
        $steps[] = 'import';
        // Every importer is done (a slice that is cut short ends in TimeBudgetReached, before this line).
        if (! isset($state['import_finished_at'])) {
            $state = $this->state->update($state['version'], ['import_finished_at' => gmdate('c')]);
        }

        if (! ($state['admin_handoff']['done'] ?? false)) {
            if ($admin === null) {
                // The records are in: the page now asks for the administrator (it shows that form only from here on).
                if (! ($state['awaiting_admin_handoff'] ?? false)) {
                    $this->state->update($state['version'], ['awaiting_admin_handoff' => true]);
                }

                return 'awaiting_admin_handoff';
            }
            try {
                $result = $this->phases->handOffAdministrator($admin, $map);
            } catch (Throwable $e) {
                // A wrong account or password: nothing was imported or changed by it, so the administrator can try again.
                throw new MigrationStopped($e->getMessage(), $e);
            }
            // Only the legacy id and the account are recorded, never the password.
            $state = $this->state->update($state['version'], ['admin_handoff' => [
                'done' => true,
                'legacy_id' => $result['legacy_id'],
                'user_id' => $result['user_id'],
            ]]);
            $steps[] = 'admin_handoff';
        }

        try {
            $report = $this->phases->verify($map, $state);
        } finally {
            ImportProgress::clear(); // the page's status line is about a step that is over
        }
        $steps[] = 'verify';
        if (($report['cleanup_allowed'] ?? false) !== true || ($report['status'] ?? null) === 'block') {
            $state = $this->state->update($state['version'], ['verification' => $report]);
            // Only what the operator can still decide (keep or discard some data) leaves the run open: re-checking
            // is read-only. A file or data problem, or an importer that did not complete, ends it.
            $blocking = array_values(array_unique(array_map(static fn (array $row): string => (string) ($row['check'] ?? ''), (array) ($report['blocking'] ?? []))));
            if ($blocking !== [] && array_diff($blocking, ['unclassified', 'unknown_choice']) === []) {
                return 'verification_blocked';
            }
            $details = array_map(static fn (array $row): string => (string) ($row['label'] ?? '').': '.(string) ($row['detail'] ?? ''), array_slice((array) ($report['blocking'] ?? []), 0, 5));
            throw new RuntimeException('Verification failed: '.implode('; ', $details ?: ['no reason recorded']));
        }
        $this->state->update($state['version'], ['phase' => 'verified', 'verification' => $report]);
        // The migration's work is over and verified: the summary page is written now, once.
        (new MigrationReport)->write($this->status());

        return null;
    }

    /**
     * Junk in data/ (caches, temporary files, sessions, logs) goes right after the backup is confirmed, before
     * anything is frozen or counted, so it is not moved, scanned or verified. Once; the backup holds it.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed> the state afterwards
     */
    private function purgeJunkBeforeFreeze(array $state): array
    {
        if ($this->legacyRoot === null || ($state['junk_purged'] ?? null) !== null) {
            return $state;
        }
        $junk = (new LegacyJunk)->purge($this->legacyRoot.'/data');

        return $this->state->update($state['version'], ['junk_purged' => $junk + ['at' => gmdate('c')]]);
    }

    /**
     * Remove the legacy data nobody owns once verification passed: every table but Premium and Custom modules'
     * (MigrationVerifier `unowned_tables`, checked again against UnownedLegacyData::removable, so an older
     * verification can never widen it), through the exact rename map; then the junk files in data/ (LegacyJunk:
     * caches, temporary files, sessions, logs), never a whole folder. Each part is recorded as it goes, so a resume
     * continues with what is left.
     *
     * @param  array<string, mixed>  $state
     * @return bool whether anything was removed now
     */
    private function removeUnowned(array $state): bool
    {
        $verification = $state['verification'] ?? null;
        if (! is_array($verification) || ($verification['cleanup_allowed'] ?? false) !== true || ($verification['status'] ?? null) === MigrationVerifier::BLOCK) {
            return false;
        }
        $done = is_array($state['unowned_removed'] ?? null) ? $state['unowned_removed'] : [];
        $doneTables = $this->stringList($done['tables'] ?? []);
        $junkDone = ($done['junk'] ?? null) !== null;
        $moduleTables = $this->legacyRoot === null ? [] : (new LegacyModuleTables)->declared($this->legacyRoot);
        $tables = array_values(array_filter(
            array_diff($this->stringList($verification['unowned_tables'] ?? []), $doneTables),
            static fn (string $table): bool => UnownedLegacyData::removable($table, $moduleTables),
        ));
        $purgeJunk = ! $junkDone && $this->legacyRoot !== null;
        if ($tables === [] && ! $purgeJunk) {
            return false;
        }
        $map = $this->map($state);
        $helper = new UnownedLegacyData;
        $total = count($tables);
        foreach (array_values($tables) as $i => $table) {
            ImportProgress::stage('Data cleanup: removing old tables', $i + 1, $total, 'tables');
            $legacy = $map[$table] ?? null;
            if ($legacy !== null) {
                $helper->dropTables($this->pdo, [$legacy]);
            }
            $doneTables[] = $table;
            $state = $this->state->update($state['version'], ['unowned_removed' => ['tables' => $doneTables] + $done]);
            $done = $state['unowned_removed'];
        }
        if ($purgeJunk) {
            ImportProgress::stage('Data cleanup: removing cache, temporary files and logs');
            $junk = (new LegacyJunk)->purge($this->legacyRoot.'/data', static function (int $doneFiles, int $all): void {
                ImportProgress::stage('Data cleanup: removing cache, temporary files and logs', $doneFiles, $all);
            });
            $this->state->update($state['version'], ['unowned_removed' => ['tables' => $doneTables, 'junk' => $junk] + $done]);
        }

        return true;
    }

    /**
     * Drop the built-in obsolete legacy tables once verification has passed (success), independent of the
     * cleanup switch. Idempotent and resumable: each drop is recorded as it happens, and a resume only
     * continues with what is left. Tables are named through the exact rename map (never guessed) and
     * dropped children first. The pre-upgrade backup holds them.
     *
     * @param  array<string, mixed>  $state
     * @return bool whether anything was dropped now
     */
    private function dropObsoleteTables(array $state): bool
    {
        $verification = $state['verification'] ?? null;
        if (! is_array($verification) || ($verification['cleanup_allowed'] ?? false) !== true || ($verification['status'] ?? null) === MigrationVerifier::BLOCK
            || ! in_array($verification['outcome'] ?? null, ['verified', 'core_complete_with_preserved_data'], true)) {
            return false;
        }
        $names = ObsoleteLegacyTables::ordered($this->stringList($verification['obsolete_tables'] ?? []));
        if ($names === []) {
            return false;
        }
        $map = $this->map($state);
        $helper = new ObsoleteLegacyTables;
        $dropped = $this->stringList($state['obsolete_drop']['dropped'] ?? []);

        $any = false;
        foreach (array_values($names) as $i => $name) {
            ImportProgress::stage('Data cleanup: removing obsolete tables', $i + 1, count($names), 'tables');
            if (! isset($map[$name])) {
                continue;
            }
            if ($helper->exists($this->pdo, $map[$name])) {
                $helper->drop($this->pdo, $map[$name]);
                $any = true;
            }
            if (! in_array($name, $dropped, true)) {
                $dropped[] = $name;
                $state = $this->state->update($state['version'], ['obsolete_drop' => ['dropped' => $dropped]]);
            }
        }

        return $any;
    }

    /** @param  list<string>  $steps */
    private function afterVerified(array $state, bool $cleanup, array &$steps): ?string
    {
        // Cleanup always runs once verification passed (the --cleanup option and the page's box are gone).
        $verification = $state['verification'] ?? null;
        if (! is_array($verification)) {
            throw new MigrationStopped('No verification report is recorded; refusing cleanup.');
        }
        if (! is_array($state['preupgrade_backup'] ?? null)) {
            throw new MigrationStopped('Cleanup refused: the pre-upgrade backup is not confirmed.');
        }
        $result = $this->phases->cleanup($verification, $this->map($state), $state);
        $this->state->update($state['version'], ['phase' => 'cleaned', 'cleanup' => [
            'dropped_tables' => count((array) ($result['dropped_tables'] ?? [])),
            'removed_files' => $result['removed_files'] ?? null,
        ]]);
        $steps[] = 'cleanup';

        return null;
    }

    /** @return array<string, string> */
    private function map(array $state): array
    {
        $map = $state['rename_map'] ?? null;
        if (! is_array($map) || $map === []) {
            throw new RuntimeException('The saved table rename map is missing; refusing to continue.');
        }
        foreach ($map as $new) {
            if (! is_string($new)) {
                throw new RuntimeException('The saved table rename map is corrupt.');
            }
        }

        return $map;
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        return is_array($value) ? array_values(array_map('strval', $value)) : [];
    }
}
