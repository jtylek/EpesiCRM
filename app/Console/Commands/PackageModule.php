<?php

namespace App\Console\Commands;

use App\Services\Modules\ModuleException;
use App\Support\Modules\ModuleManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use SplFileInfo;
use ZipArchive;

/**
 * Builds the distributable zip for a module developed in place under modules/ —
 * the release step for a module author, and how the store's own artifacts are
 * produced. Entry paths are written as <Vendor>/<Name>/... so the archive is
 * exactly what ModuleArchive expects to validate.
 */
class PackageModule extends Command
{
    protected $signature = 'module:package
        {path : module directory relative to modules/, e.g. Epesi/Notes}
        {--out= : output directory (default: storage/app/private/module-packages)}';

    protected $description = 'Build a distributable zip from a module directory';

    public function handle(): int
    {
        $relative = trim(str_replace('\\', '/', $this->argument('path')), '/');
        $directory = rtrim((string) config('modules.path'), '/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if (! is_dir($directory)) {
            $this->components->error("No module directory at modules/{$relative}.");

            return self::FAILURE;
        }

        try {
            $manifest = ModuleManifest::fromJson((string) @file_get_contents($directory.DIRECTORY_SEPARATOR.'module.json'));
        } catch (ModuleException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($manifest->path !== $relative) {
            $this->components->error("module.json declares path \"{$manifest->path}\" but the module is at \"{$relative}\".");

            return self::FAILURE;
        }

        $out = $this->option('out') ?: storage_path('app/private/module-packages');
        File::ensureDirectoryExists($out);

        $file = rtrim($out, '/\\').DIRECTORY_SEPARATOR.str_replace('/', '-', strtolower($manifest->id)).'-'.$manifest->version.'.zip';

        $zip = new ZipArchive;

        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->components->error("Could not write {$file}.");

            return self::FAILURE;
        }

        $count = 0;

        foreach (File::allFiles($directory, hidden: false) as $item) {
            /** @var SplFileInfo $item */
            $zip->addFile(
                $item->getPathname(),
                $manifest->path.'/'.str_replace('\\', '/', $item->getRelativePathname()),
            );
            $count++;
        }

        $zip->close();

        $this->components->info("Packaged {$manifest->id} {$manifest->version} ({$count} files)");
        $this->line('  '.$file.'  sha256: '.hash_file('sha256', $file));

        return self::SUCCESS;
    }
}
