<?php

namespace Epesi\Modules\MigrationWizard\Database;

use PDO;
use RuntimeException;

/**
 * Moves every legacy table out of the way inside the same database
 * (`<name>` -> `legacy_<name>`) so Epesi 3 can create its own schema in the
 * unprefixed namespace.
 *
 * Exact-map discipline: plan() builds the complete old => new map from an
 * explicit table list and refuses ambiguity; the caller persists that map
 * before apply(); apply() touches only tables named in it, never
 * a wildcard prefix. After an interruption both inspect the real tables, so
 * running them again continues from where the previous run stopped.
 *
 * MySQL/MariaDB renames each batch with one atomic RENAME TABLE statement
 * (split by table count and statement size). SQLite has no multi-table rename,
 * so it renames one by one; that path exists for tests only.
 */
final class LegacyTableNamespace
{
    public const PREFIX = 'legacy_';

    /** MySQL identifier limit. */
    private const MAX_IDENTIFIER = 64;

    /** Keep every RENAME TABLE statement far below max_allowed_packet. */
    private const MAX_STATEMENT_BYTES = 16384;

    private const MAX_BATCH_TABLES = 100;

    /**
     * @param  list<string>  $tables  every legacy base table to move
     * @param  list<string>  $existing  every table currently in the database (legacy and anything else)
     * @return array<string, string> old => new, in a stable order
     */
    public function plan(array $tables, array $existing, string $prefix = self::PREFIX): array
    {
        if (! preg_match('/^[a-z][a-z0-9_]*_$/D', $prefix)) {
            throw new RuntimeException('Invalid legacy table prefix.');
        }
        $tables = array_values(array_unique(array_map('strval', $tables)));
        sort($tables, SORT_STRING);
        $existingSet = array_fill_keys(array_map('strval', $existing), true);
        $map = [];
        foreach ($tables as $name) {
            if ($name === '' || ! preg_match('/^[A-Za-z0-9_$]+$/D', $name)) {
                throw new RuntimeException("Legacy table name '{$name}' cannot be renamed safely.");
            }
            if (! isset($existingSet[$name])) {
                throw new RuntimeException("Legacy table '{$name}' does not exist.");
            }
            // A legacy table that already looks namespaced is ambiguous with an
            // interrupted earlier run, so the wizard stops instead of guessing.
            if (str_starts_with($name, $prefix)) {
                throw new RuntimeException("Legacy table '{$name}' already starts with the namespace prefix.");
            }
            $new = $prefix.$name;
            if (strlen($new) > self::MAX_IDENTIFIER) {
                throw new RuntimeException("Renamed table '{$new}' would exceed the 64 character identifier limit.");
            }
            if (isset($existingSet[$new])) {
                throw new RuntimeException("Table '{$new}' already exists; refusing to overwrite it.");
            }
            $map[$name] = $new;
        }

        return $map;
    }

    /**
     * Compare a saved map with the real tables.
     *
     * @param  array<string, string>  $map  old => new
     * @param  list<string>  $existing
     * @return array{pending: list<string>, renamed: list<string>, conflicts: list<string>, missing: list<string>}
     */
    public function reconcile(array $map, array $existing): array
    {
        $existingSet = array_fill_keys(array_map('strval', $existing), true);
        $result = ['pending' => [], 'renamed' => [], 'conflicts' => [], 'missing' => []];
        foreach ($map as $old => $new) {
            $hasOld = isset($existingSet[$old]);
            $hasNew = isset($existingSet[$new]);
            if ($hasOld && $hasNew) {
                $result['conflicts'][] = (string) $old;
            } elseif ($hasOld) {
                $result['pending'][] = (string) $old;
            } elseif ($hasNew) {
                $result['renamed'][] = (string) $old;
            } else {
                $result['missing'][] = (string) $old;
            }
        }

        return $result;
    }

    /**
     * Rename every pending table. Safe to call again after an interruption.
     *
     * @param  array<string, string>  $map
     * @param  (callable(int $done, int $total): void)|null  $afterBatch  checkpoint hook, called after each completed batch
     * @return array{renamed_now: int, already_renamed: int}
     */
    public function apply(PDO $pdo, array $map, ?callable $afterBatch = null): array
    {
        $this->assertMap($map);

        return $this->move($pdo, $map, $afterBatch);
    }

    /**
     * Reverse the exact map: only tables that were renamed and whose original
     * name is free again. Dropping Epesi 3 tables first is the caller's job.
     *
     * @param  array<string, string>  $map  the same old => new map that was applied
     * @return array{renamed_now: int, already_renamed: int}
     */
    /** @return list<string> */
    public function tables(PDO $pdo): array
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $rows = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $rows = $pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\'')->fetchAll(PDO::FETCH_COLUMN);
        }

        return array_map('strval', $rows);
    }

    /**
     * @param  array<string, string>  $map  from => to
     * @return array{renamed_now: int, already_renamed: int}
     */
    private function move(PDO $pdo, array $map, ?callable $afterBatch): array
    {
        $state = $this->reconcile($map, $this->tables($pdo));
        if ($state['conflicts'] !== [] || $state['missing'] !== []) {
            throw new RuntimeException('Table namespace is in an ambiguous state: '
                .($state['conflicts'] !== [] ? count($state['conflicts']).' table(s) exist under both names. ' : '')
                .($state['missing'] !== [] ? count($state['missing']).' table(s) exist under neither name.' : ''));
        }
        $pending = array_intersect_key($map, array_flip($state['pending']));
        $total = count($pending);
        $done = 0;
        foreach ($this->batches($pending) as $batch) {
            $this->renameBatch($pdo, $batch);
            $done += count($batch);
            if ($afterBatch !== null) {
                $afterBatch($done, $total);
            }
        }

        return ['renamed_now' => $total, 'already_renamed' => count($state['renamed'])];
    }

    /**
     * @param  array<string, string>  $pending
     * @return list<array<string, string>>
     */
    private function batches(array $pending): array
    {
        $batches = [];
        $current = [];
        $bytes = 0;
        foreach ($pending as $from => $to) {
            $length = strlen($from) + strlen($to) + 16;
            if ($current !== [] && ($bytes + $length > self::MAX_STATEMENT_BYTES || count($current) >= self::MAX_BATCH_TABLES)) {
                $batches[] = $current;
                $current = [];
                $bytes = 0;
            }
            $current[$from] = $to;
            $bytes += $length;
        }
        if ($current !== []) {
            $batches[] = $current;
        }

        return $batches;
    }

    /** @param  array<string, string>  $batch */
    private function renameBatch(PDO $pdo, array $batch): void
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            foreach ($batch as $from => $to) {
                $pdo->exec('ALTER TABLE '.$this->quote($from).' RENAME TO '.$this->quote($to));
            }

            return;
        }
        $pairs = [];
        foreach ($batch as $from => $to) {
            $pairs[] = $this->quote($from).' TO '.$this->quote($to);
        }
        $pdo->exec('RENAME TABLE '.implode(', ', $pairs));
    }

    private function quote(string $identifier): string
    {
        if (! preg_match('/^[A-Za-z0-9_$]+$/D', $identifier)) {
            throw new RuntimeException('Unsafe table identifier.');
        }

        return '`'.$identifier.'`';
    }

    /** @param  array<string, string>  $map */
    private function assertMap(array $map): void
    {
        if ($map === []) {
            throw new RuntimeException('The table rename map is empty.');
        }
        $targets = [];
        foreach ($map as $old => $new) {
            if (! is_string($new) || (string) $old === $new || isset($targets[$new])
                || strlen($new) > self::MAX_IDENTIFIER || isset($map[$new])) {
                throw new RuntimeException('The table rename map is invalid or ambiguous.');
            }
            $targets[$new] = true;
        }
    }
}
