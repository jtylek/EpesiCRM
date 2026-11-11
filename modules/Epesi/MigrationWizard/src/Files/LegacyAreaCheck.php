<?php

namespace Epesi\Modules\MigrationWizard\Files;

use Epesi\Modules\MigrationWizard\Verification\MigrationVerifier;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Decides which protected business-file areas (data/CRM_Mail, data/Utils_FileStorage)
 * count as migrated, and lists what is left in them.
 *
 * Files are moved out one by one by the file importers, each with a ledger entry. An area
 * is migrated when its importer completed and the ledger has no problem: a file an
 * imported record needs but that could not be found or moved is a ledger problem, so it
 * blocks. Files that no imported record references (orphans: a deleted mail's files, a
 * never-finished archive) are still there after the import: they do not block, they are
 * listed in the report, and cleanup removes them once verification passed
 * (LegacyFileClassifier), except what stays by design (the legacy mail key).
 */
final class LegacyAreaCheck
{
    /** The importer whose completion makes an area's remaining files "unreferenced" rather than "not yet imported". */
    private const AREA_IMPORTERS = ['data/CRM_Mail' => 'mail', 'data/Utils_FileStorage' => 'attachments'];

    /** Files that stay in an area by design and are not reported as unreferenced: the legacy mail key. */
    private const ALLOWED = ['data/CRM_Mail' => ['encryption.key']];

    /** Whether a path (relative to the installation, e.g. data/CRM_Mail/encryption.key) is a file that stays by design. */
    public static function staysByDesign(string $path): bool
    {
        $path = str_replace('\\', '/', $path);
        foreach (self::ALLOWED as $area => $files) {
            foreach ($files as $file) {
                if ($path === $area.'/'.$file) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $completed  importer tabs that finished
     * @param  array<string, mixed>|null  $ledger  FileLedger::verify() output
     * @return list<string> areas that count as migrated
     */
    public function migratedAreas(string $dataDir, array $completed, ?array $ledger): array
    {
        if ($ledger === null || ($ledger['problems'] ?? []) !== []) {
            return [];
        }
        $migrated = [];
        foreach (MigrationVerifier::PROTECTED_AREAS as $area) {
            $base = rtrim($dataDir, '/\\').'/'.substr($area, strlen('data/'));
            // An area that is a link or cannot be read completely is never called migrated.
            if (in_array(self::AREA_IMPORTERS[$area] ?? null, $completed, true) && ! is_link($base) && ! $this->unreadable($dataDir, $area)) {
                $migrated[] = $area;
            }
        }

        return $migrated;
    }

    private function unreadable(string $dataDir, string $area): bool
    {
        return ! $this->walk($dataDir, $area, static function (): void {});
    }

    /**
     * What is still in the given areas (files nobody referenced), per area.
     *
     * @param  list<string>  $areas
     * @return array<string, array{count: int, bytes: int, sample: list<string>}> only areas with something left
     */
    public function leftovers(string $dataDir, array $areas): array
    {
        $found = [];
        foreach ($areas as $area) {
            $remaining = $this->remaining($dataDir, $area);
            if ($remaining['count'] > 0) {
                $found[$area] = $remaining;
            }
        }

        return $found;
    }

    /**
     * Every file left in an area, as paths relative to the installation (data/...), other than the ones that stay by design.
     *
     * @return list<string>
     */
    public function listRemaining(string $dataDir, string $area): array
    {
        $paths = [];
        $this->walk($dataDir, $area, static function (string $relative, int $size) use (&$paths, $area): void {
            $paths[] = $area.'/'.$relative;
        });
        sort($paths, SORT_STRING);

        return $paths;
    }

    /**
     * Files still in an area, other than the ones that stay by design.
     *
     * @return array{count: int, bytes: int, sample: list<string>}
     */
    public function remaining(string $dataDir, string $area, int $sample = 10): array
    {
        $count = 0;
        $bytes = 0;
        $found = [];
        $readable = $this->walk($dataDir, $area, static function (string $relative, int $size) use (&$count, &$bytes, &$found, $sample, $area): void {
            $count++;
            $bytes += $size;
            if (count($found) < $sample) {
                $found[] = $area.'/'.$relative;
            }
        });
        if (! $readable) {
            return ['count' => 1, 'bytes' => 0, 'sample' => ['(the directory could not be read)']];
        }

        return ['count' => $count, 'bytes' => $bytes, 'sample' => $found];
    }

    /**
     * @param  callable(string, int): void  $each  relative path inside the area, size
     * @return bool false when the area could not be read completely
     */
    private function walk(string $dataDir, string $area, callable $each): bool
    {
        $base = rtrim($dataDir, '/\\').'/'.substr($area, strlen('data/'));
        if (is_link($base)) {
            $each('(the area is a link)', 0);

            return true;
        }
        if (! is_dir($base)) {
            return true;
        }
        $allowed = array_map(static fn (string $path): string => str_replace('\\', '/', $path), self::ALLOWED[$area] ?? []);
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
            );
            foreach ($iterator as $file) {
                $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($base))), '/');
                if (in_array($relative, $allowed, true) && $file->isFile() && ! $file->isLink()) {
                    continue;
                }
                // An empty directory is nothing left; anything else (a file, a link, a socket) is.
                if ($file->isDir() && ! $file->isLink()) {
                    continue;
                }
                $each($relative, $file->isFile() && ! $file->isLink() ? (int) $file->getSize() : 0);
            }
        } catch (Throwable) {
            return false;
        }

        return true;
    }
}
