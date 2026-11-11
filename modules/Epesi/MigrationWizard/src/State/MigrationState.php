<?php

namespace Epesi\Modules\MigrationWizard\State;

use RuntimeException;

/**
 * Atomic, filesystem-backed migration state. No database writes.
 *
 * Fail closed: update() accepts only a known current phase and a known next
 * phase that follows the state machine (see allowed()); anything else, an
 * unknown phase included, is refused. A null value in $changes removes that key.
 */
final class MigrationState
{
    public const EARLY = ['detected', 'assessed', 'backed_up'];

    /** The forward ladder after backup; each phase may only advance to the next one. */
    public const LADDER = ['backed_up', 'frozen', 'tables_namespaced', 'epesi_installed', 'importing', 'verified', 'cleaned'];

    public const FINAL = ['complete', 'core_complete_with_preserved_data'];

    /** Terminal: the migration failed and cannot continue; the remedy is the pre-upgrade backup. Nothing leaves this phase. */
    public const FAILED = 'failed';

    public static function known(string $phase): bool
    {
        return in_array($phase, [...self::EARLY, ...self::LADDER, ...self::FINAL, self::FAILED], true);
    }

    /** Is moving from $from to $to a legal transition? Same-phase updates are legal except in a final phase. */
    public static function allowed(string $from, string $to): bool
    {
        if (! self::known($from) || ! self::known($to) || in_array($from, [...self::FINAL, self::FAILED], true)) {
            return false;
        }
        if ($from === $to) {
            return true;
        }
        if (in_array($from, self::EARLY, true) && in_array($to, self::EARLY, true)) {
            return true;
        }
        if ($to === self::FAILED) {
            // Only after the wizard began to change things; a failure before that is a stop, not an end.
            return in_array($from, array_slice(self::LADDER, 1), true);
        }
        if ($from === 'verified' && $to === 'importing') {
            return true; // importers may be rerun safely
        }
        if ($from === 'cleaned') {
            return in_array($to, self::FINAL, true);
        }
        $index = array_search($from, self::LADDER, true);

        return $index !== false && (self::LADDER[$index + 1] ?? null) === $to;
    }

    public function __construct(private readonly string $directory) {}

    public function read(): array
    {
        $path = $this->directory.'/state.json';
        if (! is_file($path)) {
            return ['phase' => 'detected', 'version' => 0];
        }
        $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($value)) {
            throw new RuntimeException('Invalid migration state.');
        }

        return $value;
    }

    public function update(int $expectedVersion, array $changes): array
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0700, true) && ! is_dir($this->directory)) {
            throw new RuntimeException('Cannot create migration state directory.');
        }
        $lock = fopen($this->directory.'/state.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Cannot lock migration state.');
        }
        try {
            $current = $this->read();
            if (($current['version'] ?? 0) !== $expectedVersion) {
                throw new RuntimeException('Migration state changed in another session.');
            }
            $next = array_merge($current, $changes);
            foreach ($changes as $key => $value) {
                if ($value === null) {
                    unset($next[$key]);
                }
            }
            $from = $current['phase'] ?? '';
            $to = $next['phase'] ?? '';
            if (! is_string($from) || ! is_string($to) || ! self::allowed($from, $to)) {
                throw new RuntimeException('Migration phase transition refused.');
            }
            $next['version'] = $expectedVersion + 1;
            $temp = tempnam($this->directory, '.state-');
            if ($temp === false) {
                throw new RuntimeException('Cannot create temporary migration state.');
            }
            try {
                if (file_put_contents($temp, json_encode($next, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), LOCK_EX) === false || ! rename($temp, $this->directory.'/state.json')) {
                    throw new RuntimeException('Cannot persist migration state.');
                }
            } finally {
                if (is_file($temp)) {
                    unlink($temp);
                }
            }

            return $next;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
