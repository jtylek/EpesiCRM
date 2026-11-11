<?php

namespace Epesi\Modules\MigrationWizard\Checks;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * How much free disk space a migration needs. The business files of the two
 * moved areas (data/CRM_Mail, data/Utils_FileStorage) are MOVED into the Epesi 3
 * file storage one at a time, so they need at most one file's worth of room (the
 * copy of a file on another filesystem before its source goes); everything else
 * that is copied needs 1x its size. A small margin covers the ledger and the like.
 * The database (mail bodies are copied into its tables) is not part of this.
 */
final class DiskSpaceEstimator
{
    /** Areas whose files are moved, not copied (relative to the legacy root). */
    public const MOVED_AREAS = ['data/CRM_Mail', 'data/Utils_FileStorage'];

    public const MARGIN_BYTES = 67108864;

    public function inspect(string $directory): array
    {
        $real = realpath($directory);
        if ($real === false || ! is_dir($real)) {
            throw new RuntimeException('Invalid directory.');
        }
        $bytes = 0;
        $copied = 0;
        $moved = 0;
        $largestMoved = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isLink() || ! $file->isFile()) {
                continue;
            }
            $size = $file->getSize();
            $bytes += $size;
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($real) + 1));
            if ($this->inMovedArea($relative)) {
                $moved += $size;
                $largestMoved = max($largestMoved, $size);
            } else {
                $copied += $size;
            }
        }
        $free = disk_free_space($real);
        $recommended = $copied + $largestMoved + self::MARGIN_BYTES;

        return [
            'source_bytes' => $bytes,
            'copied_bytes' => $copied,
            'moved_bytes' => $moved,
            'largest_moved_file_bytes' => $largestMoved,
            'available_bytes' => $free === false ? null : (int) $free,
            'recommended_bytes' => $recommended,
            'sufficient' => $free !== false && $free >= $recommended,
        ];
    }

    private function inMovedArea(string $relative): bool
    {
        foreach (self::MOVED_AREAS as $area) {
            if (str_starts_with($relative, $area.'/')) {
                return true;
            }
        }

        return false;
    }
}
