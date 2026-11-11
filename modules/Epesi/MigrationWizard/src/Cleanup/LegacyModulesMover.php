<?php

namespace Epesi\Modules\MigrationWizard\Cleanup;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * After cleanup the legacy module folders that stay (a Premium or Custom module with no importer, and any other
 * legacy module the cleanup kept) leave modules/, so that it holds Epesi 3's modules only: each one is moved, never
 * deleted, to legacy-modules/ under the same relative path (modules/Premium/ExchangeRate becomes
 * legacy-modules/Premium/ExchangeRate).
 *
 * A folder is Epesi 3's when it, or anything below it, has a module.json (nested Premium modules included); every
 * other folder below modules/ is legacy. A move whose target already exists is skipped and reported, never merged or
 * overwritten; a link is never followed.
 */
final class LegacyModulesMover
{
    public const TARGET = 'legacy-modules';

    /** @return array{moved: list<string>, skipped: list<string>} paths relative to the installation */
    public function move(string $root): array
    {
        $moved = [];
        $skipped = [];
        $modules = rtrim($root, '/\\').'/modules';
        if (! is_dir($modules) || is_link($modules)) {
            return ['moved' => $moved, 'skipped' => $skipped];
        }
        foreach ($this->legacyFolders($modules, 'modules') as $relative) {
            $source = rtrim($root, '/\\').'/'.$relative;
            $target = rtrim($root, '/\\').'/'.self::TARGET.'/'.substr($relative, strlen('modules/'));
            if (file_exists($target) || is_link($target)) {
                $skipped[] = $relative;

                continue;
            }
            if (! is_dir(dirname($target)) && ! @mkdir(dirname($target), 0775, true) && ! is_dir(dirname($target))) {
                $skipped[] = $relative;

                continue;
            }
            if (@rename($source, $target)) {
                $moved[] = $relative;
            } else {
                $skipped[] = $relative;
            }
        }

        return ['moved' => $moved, 'skipped' => $skipped];
    }

    /**
     * The topmost legacy folders below $directory: a folder with no module.json anywhere in it is legacy as a whole;
     * one that holds an Epesi 3 module somewhere is looked into, folder by folder.
     *
     * @return list<string>
     */
    public function legacyFolders(string $directory, string $relative): array
    {
        $found = [];
        foreach (@scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $directory.'/'.$name;
            if (! is_dir($path) || is_link($path)) {
                continue;
            }
            if (is_file($path.'/module.json')) {
                continue; // an Epesi 3 module: stays, with everything in it
            }
            if (! $this->holdsModule($path)) {
                $found[] = $relative.'/'.$name;

                continue;
            }
            array_push($found, ...$this->legacyFolders($path, $relative.'/'.$name));
        }

        return $found;
    }

    private function holdsModule(string $directory): bool
    {
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD);
        foreach ($items as $item) {
            if (! $item->isLink() && $item->isFile() && $item->getFilename() === 'module.json') {
                return true;
            }
        }

        return false;
    }
}
