<?php

namespace Epesi\Modules\MigrationWizard\Cleanup;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The junk in a legacy data/ folder (user decision, measured on a real installation): caches, temporary files,
 * sessions, logs and old config copies. Nothing else in data/ is ever removed as junk: business files, translations,
 * logos, the company logo for PDFs, data/config.php and every folder not named here stay.
 *
 * Files only, never a whole folder: a listed folder keeps its own index.html and .htaccess (they stop the web server
 * from listing or serving it), and the sub-folders it empties go. Links are never followed; a link is removed as a
 * link only when it sits where junk is.
 */
final class LegacyJunk
{
    /** Folders under data/ whose whole contents are junk (their own index.html and .htaccess stay). */
    public const FOLDERS = [
        'cache',
        'logs',
        'tmp-sessions',
        'CRM_Roundcube/tmp',
        'CRM_Roundcube/log',
        'Base_Theme/compiled',
        'Base_Theme/cache',
        'Utils_FileUpload',
    ];

    /** Files directly in a folder under data/ ('' is data/ itself), matched by glob on the file name. */
    public const FILES = [
        'Base_Error' => ['*.txt'],
        'Libs_TCPDF' => ['__tcpdf_*'],
        '' => ['*.log', 'config.php.old', 'config.php.memcache_kt'],
    ];

    /**
     * Folders under data/ that hold no junk but whose empty sub-folders go. Their files are never touched: legacy moved
     * Utils_Attachment's files to Utils_FileStorage long ago and left the folders behind, thousands of them.
     */
    public const PRUNE_ONLY = ['Utils_Attachment'];

    /** What a listed folder keeps at its top. */
    public const KEEP = ['index.html', '.htaccess'];

    /**
     * What would go, without removing anything.
     *
     * @return array{files: int, bytes: int, paths: list<string>} paths relative to data/
     */
    public function plan(string $dataDir): array
    {
        $files = 0;
        $bytes = 0;
        $paths = [];
        foreach ($this->candidates($dataDir) as $relative => $info) {
            $files++;
            $bytes += $info->isLink() ? 0 : (int) $info->getSize();
            $paths[] = $relative;
        }

        return ['files' => $files, 'bytes' => $bytes, 'paths' => $paths];
    }

    /**
     * Remove the junk. A file that cannot be removed is skipped (junk is never worth stopping a migration for).
     *
     * @param  (callable(int, int): void)|null  $progress  (files removed so far, files in all)
     * @return array{files: int, bytes: int, skipped: int}
     */
    public function purge(string $dataDir, ?callable $progress = null): array
    {
        $candidates = iterator_to_array($this->candidates($dataDir));
        $total = count($candidates);
        $files = 0;
        $bytes = 0;
        $skipped = 0;
        foreach ($candidates as $info) {
            $size = $info->isLink() ? 0 : (int) $info->getSize();
            if (@unlink($info->getPathname())) {
                $files++;
                $bytes += $size;
            } else {
                $skipped++;
            }
            if ($progress !== null) {
                $progress($files + $skipped, $total);
            }
        }
        $root = realpath($dataDir);
        if ($root !== false) {
            foreach ([...self::FOLDERS, ...self::PRUNE_ONLY] as $folder) {
                $this->pruneEmpty($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $folder));
            }
        }

        return ['files' => $files, 'bytes' => $bytes, 'skipped' => $skipped];
    }

    /**
     * How many folders below a data/ folder hold no file at any depth: what removing its empty sub-folders takes out.
     * Removes nothing. Links count as content and are not followed.
     */
    public function emptyFolders(string $path): int
    {
        return is_link($path) || ! is_dir($path) ? 0 : $this->countEmptyBelow($path)[0];
    }

    /** @return array{0: int, 1: bool} empty folders below it, and whether it is empty itself */
    private function countEmptyBelow(string $path): array
    {
        $count = 0;
        $empty = true;
        foreach (@scandir($path) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $full = $path.DIRECTORY_SEPARATOR.$name;
            if (is_link($full) || ! is_dir($full)) {
                $empty = false;

                continue;
            }
            [$below, $isEmpty] = $this->countEmptyBelow($full);
            $count += $below + ($isEmpty ? 1 : 0);
            $empty = $empty && $isEmpty;
        }

        return [$count, $empty];
    }

    /** @return \Generator<string, SplFileInfo> path relative to data/ => file */
    private function candidates(string $dataDir): \Generator
    {
        $root = realpath($dataDir);
        if ($root === false || ! is_dir($root)) {
            return;
        }
        foreach (self::FOLDERS as $folder) {
            $path = $this->inside($root, $folder);
            if ($path === null) {
                continue;
            }
            $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
            foreach ($items as $item) {
                /** @var SplFileInfo $item */
                if ($item->isDir() && ! $item->isLink()) {
                    continue;
                }
                $relative = $folder.'/'.str_replace('\\', '/', substr($item->getPathname(), strlen($path) + 1));
                if (in_array($relative, array_map(static fn (string $keep): string => $folder.'/'.$keep, self::KEEP), true)) {
                    continue;
                }
                yield $relative => $item;
            }
        }
        foreach (self::FILES as $folder => $patterns) {
            $path = $folder === '' ? $root : $this->inside($root, $folder);
            if ($path === null) {
                continue;
            }
            foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $item) {
                /** @var SplFileInfo $item */
                if ($item->isDir() && ! $item->isLink()) {
                    continue;
                }
                foreach ($patterns as $pattern) {
                    if (fnmatch($pattern, $item->getFilename())) {
                        yield ($folder === '' ? '' : $folder.'/').$item->getFilename() => $item;

                        break;
                    }
                }
            }
        }
    }

    /** The real path of a listed folder when it is a real folder inside data/ (never a link, never outside). */
    private function inside(string $root, string $folder): ?string
    {
        $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $folder);
        if (is_link($path) || ! is_dir($path)) {
            return null;
        }
        $real = realpath($path);

        return $real !== false && str_starts_with($real, $root.DIRECTORY_SEPARATOR) ? $real : null;
    }

    /** Remove the empty sub-folders of a listed folder (never the folder itself, never through a link). */
    private function pruneEmpty(string $path): void
    {
        if (is_link($path) || ! is_dir($path)) {
            return;
        }
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            /** @var SplFileInfo $item */
            if ($item->isDir() && ! $item->isLink() && (@scandir($item->getPathname()) ?: []) === ['.', '..']) {
                @rmdir($item->getPathname());
            }
        }
    }
}
