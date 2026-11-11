<?php

namespace Epesi\Modules\MigrationWizard\Cleanup;

use Epesi\Modules\MigrationWizard\Database\UnownedLegacyData;
use PDO;

/**
 * What the wizard's last step would remove, table by table and folder by folder, with the reason. Reads the database
 * and the disk and writes nothing (epesi:migrate-legacy --cleanup-preview).
 */
final class CleanupPreview
{
    public const DROP = 'drop';

    public const KEEP = 'keep';

    /**
     * @param  array<string, mixed>  $verification  MigrationVerifier::verify() output, measured now
     * @param  array<string, mixed>  $plan  LegacyCleanup::plan() output, with the unowned tables in its drop list
     * @param  array<string, string>  $renameMap  old => new
     * @return array<string, mixed>
     */
    public function build(PDO $pdo, string $root, array $verification, array $plan, array $renameMap): array
    {
        $original = array_flip($renameMap);
        $drops = array_fill_keys(array_map('strval', $plan['drop_tables']), true);
        $reasons = $this->dropReasons($verification);
        $tables = [];
        foreach ($this->legacyTables($pdo) as $table => $bytes) {
            $name = $original[$table] ?? $table;
            $drop = isset($drops[$table]);
            $tables[] = [
                'table' => $table,
                'rows' => (int) $pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn(),
                'bytes' => $bytes,
                'decision' => $drop ? self::DROP : self::KEEP,
                'why' => $drop ? ($reasons[$name] ?? 'released by verification') : $this->keepReason($name, $plan, $verification),
            ];
        }

        $data = $root.'/data';
        $junk = new LegacyJunk;
        $junkPlan = $junk->plan($data);
        $emptied = [];
        foreach (LegacyCleanup::emptiedAreas() as $area) {
            $emptied[$area] = $junk->emptyFolders($root.'/'.$area);
        }

        return [
            'cleanup_allowed' => (bool) ($verification['cleanup_allowed'] ?? false),
            'blocking' => $verification['blocking'] ?? [],
            'warnings' => $verification['warnings'] ?? [],
            'certification' => $verification['table_certification'] ?? [],
            'tables' => $tables,
            'files' => $this->fileSummary($root, array_map('strval', $plan['remove_paths']), array_map('strval', $plan['archive_paths'])),
            'files_left_in_data' => $this->filesLeftInData($data),
            'junk' => ['files' => $junkPlan['files'], 'bytes' => $junkPlan['bytes']],
            'empty_folders' => $emptied,
        ];
    }

    /**
     * @param  array<string, mixed>  $preview  build() output
     * @return list<string>
     */
    public function toLines(array $preview): array
    {
        $lines = ['CLEANUP PREVIEW (nothing was changed)', 'Cleanup allowed: '.($preview['cleanup_allowed'] ? 'yes' : 'NO')];
        foreach ($preview['blocking'] as $item) {
            $lines[] = '  BLOCKED '.$item['label'].': '.$item['detail'];
        }

        $lines[] = '';
        $lines[] = 'COUNT CHECK (source = imported + skipped)';
        foreach ($preview['certification'] as $entry) {
            $skip = $entry['skipped'] > 0 ? ' + '.$entry['skipped'].' '.($entry['skip_label'] ?? 'skipped') : '';
            $missing = $entry['missing_ids'] > 0 ? ', '.$entry['missing_ids'].' UNEXPLAINED' : '';
            $pending = $entry['pending'] === [] ? '' : ' | '.implode('; ', $entry['pending']);
            $lines[] = '  '.str_pad($entry['status'] === 'certified' ? 'match' : 'KEPT', 6).str_pad($entry['table'], 36).$entry['source'].' = '.$entry['imported'].' imported'.$skip.$missing.$pending;
        }

        foreach ([self::DROP => 'TABLES THAT WOULD BE DROPPED', self::KEEP => 'TABLES THAT WOULD STAY'] as $decision => $title) {
            $tables = array_values(array_filter($preview['tables'], static fn (array $row): bool => $row['decision'] === $decision));
            $lines[] = '';
            $lines[] = $title.': '.count($tables).' tables, '.number_format(array_sum(array_column($tables, 'rows'))).' rows, '.self::size((int) array_sum(array_column($tables, 'bytes')));
            usort($tables, static fn (array $a, array $b): int => [$b['rows'], $a['table']] <=> [$a['rows'], $b['table']]);
            foreach ($tables as $row) {
                $lines[] = '  '.str_pad($row['table'], 58).str_pad(number_format($row['rows']), 9, ' ', STR_PAD_LEFT).str_pad(self::size($row['bytes']), 10, ' ', STR_PAD_LEFT).'  '.$row['why'];
            }
        }

        $lines[] = '';
        $lines[] = 'FILES THAT WOULD BE REMOVED: '.$preview['files']['count'].', '.self::size($preview['files']['bytes']).' (of them '.$preview['files']['archived'].' archived first)';
        foreach ($preview['files']['by_top'] as $top => $count) {
            $lines[] = '  '.str_pad((string) $top, 40).$count;
        }
        $lines[] = 'Junk in data/ (caches, logs): '.$preview['junk']['files'].' files, '.self::size($preview['junk']['bytes']);
        $lines[] = '';
        $lines[] = 'EMPTY FOLDERS THAT WOULD BE REMOVED';
        foreach ($preview['empty_folders'] as $area => $count) {
            $lines[] = '  '.str_pad($area, 40).$count;
        }
        $lines[] = '';
        $lines[] = 'FILES THAT STAY IN data/Utils_Attachment: '.$preview['files_left_in_data'];

        if ($preview['warnings'] !== []) {
            $lines[] = '';
            $lines[] = 'WARNINGS';
            foreach ($preview['warnings'] as $item) {
                $lines[] = '  '.$item['label'].': '.$item['detail'];
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $verification
     * @return array<string, string> original table name => why it goes
     */
    private function dropReasons(array $verification): array
    {
        $reasons = [];
        foreach ((array) ($verification['unowned_tables'] ?? []) as $table) {
            $reasons[(string) $table] = 'no importer and no Premium or Custom module owns it';
        }
        foreach ((array) ($verification['obsolete_tables'] ?? []) as $table) {
            $reasons[(string) $table] = 'not used by Epesi 3';
        }
        foreach ((array) ($verification['table_groups'] ?? []) as $parent => $sides) {
            foreach ((array) $sides as $side) {
                $reasons[(string) $side] = $parent === 'watchdog' ? 'legacy watchdog table' : "goes with {$parent}";
            }
        }
        foreach ((array) ($verification['table_certification'] ?? []) as $entry) {
            if ($entry['status'] === 'certified') {
                $reasons[$entry['table']] = 'counts match';
            }
        }

        return $reasons;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $verification
     */
    private function keepReason(string $name, array $plan, array $verification): string
    {
        if (isset($plan['kept_tables'][$name])) {
            return (string) $plan['kept_tables'][$name];
        }
        foreach ((array) ($verification['table_certification'] ?? []) as $entry) {
            if ($entry['table'] === $name && $entry['status'] !== 'certified') {
                return $entry['pending'] !== [] ? implode('; ', $entry['pending']) : "counts differ: source {$entry['source']}, imported {$entry['imported']}, skipped {$entry['skipped']}, unexplained {$entry['missing_ids']}";
            }
        }
        foreach ((array) ($verification['table_groups'] ?? []) as $parent => $sides) {
            if (in_array($name, (array) $sides, true)) {
                return "stays with {$parent}";
            }
        }
        foreach (UnownedLegacyData::KEPT_PREFIXES as $prefix) {
            if (str_starts_with(strtolower($name), $prefix)) {
                return 'a Premium or Custom module: no importer yet';
            }
        }

        return in_array($name, (array) ($verification['preserved_tables'] ?? []), true) ? 'named by a module that stays, or kept by choice' : 'not released by verification';
    }

    /** @return array<string, int> namespaced legacy table => bytes on disk (0 where the database cannot say) */
    private function legacyTables(PDO $pdo): array
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $names = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE 'legacy\\_%' ESCAPE '\\' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

            return array_fill_keys(array_map('strval', $names), 0);
        }
        $tables = [];
        foreach ($pdo->query("SELECT TABLE_NAME, COALESCE(DATA_LENGTH, 0) + COALESCE(INDEX_LENGTH, 0) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'legacy\\_%' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_NUM) as [$table, $bytes]) {
            $tables[(string) $table] = (int) $bytes;
        }

        return $tables;
    }

    /**
     * @param  list<string>  $remove
     * @param  list<string>  $archive
     * @return array{count: int, bytes: int, archived: int, by_top: array<string, int>}
     */
    private function fileSummary(string $root, array $remove, array $archive): array
    {
        $bytes = 0;
        $tops = [];
        foreach ($remove as $path) {
            $bytes += (int) @filesize($root.'/'.$path);
            $segments = explode('/', $path);
            $top = $segments[0] === 'data' && isset($segments[2]) ? 'data/'.$segments[1] : $segments[0];
            $tops[$top] = ($tops[$top] ?? 0) + 1;
        }
        arsort($tops);

        return ['count' => count($remove), 'bytes' => $bytes, 'archived' => count($archive), 'by_top' => $tops];
    }

    private function filesLeftInData(string $data): int
    {
        $path = $data.'/Utils_Attachment';
        if (is_link($path) || ! is_dir($path)) {
            return 0;
        }
        $count = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $item) {
            $count += $item->isDir() ? 0 : 1;
        }

        return $count;
    }

    private static function size(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1).' MB' : round($bytes / 1024, 1).' KB';
    }
}
