<?php

namespace Epesi\Modules\MigrationWizard\Checks;

use Epesi\Modules\MigrationWizard\Detection\ScratchAreas;
use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/** Advisory sizing only. Walks all of data/ except the regenerable data/cache and data/logs. */
final class MigrationExecutionAdvisor
{
    public function assess(array $assessment, string $legacyRoot, int $largeFileCount = 10000): array
    {
        $bytes = 0;
        $rows = 0;
        foreach ($assessment['table_statistics'] ?? [] as $table) {
            $bytes += max(0, (int) ($table['estimated_bytes'] ?? 0));
            $rows += max(0, (int) ($table['estimated_rows'] ?? 0));
        }
        $files = 0;
        $fileBytes = 0;
        $incomplete = false;
        $dataDir = realpath($legacyRoot.'/data');
        if ($dataDir === false || ! is_dir($dataDir)) {
            $incomplete = true;
        } else {
            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveCallbackFilterIterator(
                        new RecursiveDirectoryIterator($dataDir, FilesystemIterator::SKIP_DOTS),
                        static fn ($current): bool => ! ($current->isDir() && ScratchAreas::skips($dataDir, $current->getPathname()))
                    ),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($iterator as $file) {
                    if ($file->isLink() || ! $file->isFile()) {
                        continue;
                    }
                    $files++;
                    $fileBytes += max(0, $file->getSize());
                }
            } catch (\Throwable) {
                $incomplete = true;
            }
        }
        $large = $bytes >= 100000000 || $rows >= 100000 || $files >= $largeFileCount
            || $fileBytes >= 1073741824 || $incomplete;

        return [
            'recommended_execution' => $large ? 'cli' : 'browser_possible',
            'cli_recommended' => $large,
            'database_estimated_bytes' => $bytes,
            'database_estimated_rows' => $rows,
            'files_scanned' => $files,
            'file_bytes_scanned' => $fileBytes,
            'file_scan_incomplete' => $incomplete,
            'thresholds' => ['database_bytes' => 100000000, 'database_rows' => 100000,
                'file_bytes' => 1073741824, 'files_scanned' => $largeFileCount],
            'warning' => $large
                ? 'Large or incompletely measured installation: browser requests may time out. Use the CLI migration workflow once available; do not rely on an open browser tab.'
                : 'Browser execution may still time out on slow hosting. Prefer CLI for long-running migrations.',
            'cli_instructions' => 'SSH into the EPESI 3 installation, confirm PHP CLI >= 8.3, enable legacy maintenance mode and verify backups. Run php artisan epesi:migrate-legacy --status, then php artisan epesi:migrate-legacy --dry-run to inspect the installation. These commands are assessment-only; production migration execution and resume are not yet implemented.',
        ];
    }
}
