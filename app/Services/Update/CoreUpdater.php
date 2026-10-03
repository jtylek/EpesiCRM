<?php

namespace App\Services\Update;

use App\Support\Modules\ModuleRegistry;
use App\Support\Version;
use FilesystemIterator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Puts a core release (see CorePackage) over the running installation.
 *
 * Only files that differ are touched: the manifest's checksums are compared
 * with what is on disk, changed files are extracted to a staging folder, and
 * each old file is moved into a backup folder before the new one takes its
 * place, so a failure part-way puts everything back. Files the release no
 * longer ships are removed (and backed up) inside the trees that belong to
 * the release — app/, vendor/, routes/, config/, resources/views/,
 * public/build/ and the module folders it carries — never anywhere else.
 *
 * Never touched, even if the zip has them: .env, .htaccess, storage/ and
 * bootstrap/cache/. Modules installed from the Store live in their own
 * folders and are left alone.
 *
 * VERSION is written last, so an interrupted update never reads as finished.
 * The database is not migrated here: the new code finds pending migrations on
 * the next request and sends administrators to Administration → Database
 * update (or `php artisan epesi:update`), which runs them with the new code.
 */
class CoreUpdater
{
    /** Folders where a file the release doesn't list is deleted. */
    protected const PRUNED = ['app', 'vendor', 'routes', 'config', 'resources/views', 'public/build'];

    protected string $base;

    /**
     * @param  bool  $live  also do what only the real installation needs: maintenance mode, clearing caches
     */
    public function __construct(?string $base = null, protected ?string $current = null, protected bool $live = true)
    {
        $this->base = rtrim($base ?? base_path(), '/\\');
        $this->current ??= Version::current();
    }

    /**
     * @return array{from: string, to: string, added: int, replaced: int, removed: int, backup: string}
     */
    public function apply(string $zipPath): array
    {
        $package = new CorePackage($zipPath);

        try {
            return $this->applyPackage($package);
        } finally {
            $package->close();
        }
    }

    /**
     * @return array{from: string, to: string, added: int, replaced: int, removed: int, backup: string}
     */
    protected function applyPackage(CorePackage $package): array
    {
        $to = $package->version();

        if (version_compare($to, $this->current, '<=')) {
            throw new UpdateException("This installation is already at {$this->current}; the update is {$to}.");
        }

        $files = array_filter($package->files(), fn (string $hash, string $path): bool => ! $this->isPreserved($path), ARRAY_FILTER_USE_BOTH);

        $this->assertWritable();

        $changed = [];
        $added = [];

        foreach ($files as $path => $hash) {
            $local = $this->path($path);

            if (! is_file($local)) {
                $added[] = $path;
            } elseif (hash_file('sha256', $local) !== $hash) {
                $changed[] = $path;
            }
        }

        $stale = $this->stale($files);
        $work = $this->workFolder();
        $staging = $work.DIRECTORY_SEPARATOR.'staging';
        $backup = $work.DIRECTORY_SEPARATOR.'backup-'.$this->current.'-'.date('YmdHis');
        $journal = ['added' => [], 'stashed' => []];

        @set_time_limit(0);
        ignore_user_abort(true);

        $this->down();

        try {
            File::ensureDirectoryExists($staging);

            $toExtract = [...$added, ...$changed];
            $package->extract($staging, $toExtract);

            foreach ($toExtract as $path) {
                $staged = $staging.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);

                if (! is_file($staged) || hash_file('sha256', $staged) !== $files[$path]) {
                    throw new UpdateException("{$path} did not unpack correctly; nothing was changed.");
                }
            }

            File::ensureDirectoryExists($backup);

            // VERSION last: the update only reads as finished once it's all in.
            usort($toExtract, fn (string $a, string $b): int => ($a === 'VERSION') <=> ($b === 'VERSION'));

            foreach ($stale as $path) {
                $this->stash($path, $backup, $journal);
            }

            foreach ($toExtract as $path) {
                if (in_array($path, $changed, true)) {
                    $this->stash($path, $backup, $journal);
                } else {
                    $journal['added'][] = $path;
                }

                $this->place($staging.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path), $this->path($path));
            }
        } catch (Throwable $exception) {
            $failed = $this->rollback($journal, $backup);

            File::deleteDirectory($staging);
            $this->up();

            throw new UpdateException(
                'The update failed and was rolled back: '.$exception->getMessage()
                .($failed ? " Some files could not be put back; they are in {$backup}." : ''),
                previous: $exception,
            );
        }

        File::deleteDirectory($staging);
        $this->pruneBackups($work, $backup);
        $this->finish();

        return [
            'from' => $this->current,
            'to' => $to,
            'added' => count($added),
            'replaced' => count($changed),
            'removed' => count($stale),
            'backup' => $backup,
        ];
    }

    /** Where the zip's path lands on disk. */
    protected function path(string $relative): string
    {
        return $this->base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    protected function isPreserved(string $path): bool
    {
        return $path === '.env'
            || $path === '.htaccess'
            || str_starts_with($path, 'storage/')
            || str_starts_with($path, 'bootstrap/cache/');
    }

    protected function workFolder(): string
    {
        return $this->base.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'core-update';
    }

    protected function assertWritable(): void
    {
        $work = $this->workFolder();
        File::ensureDirectoryExists($work);

        foreach ([$this->base, $this->base.DIRECTORY_SEPARATOR.'app', $this->base.DIRECTORY_SEPARATOR.'vendor', $work] as $folder) {
            if (! is_dir($folder) || ! is_writable($folder)) {
                throw new UpdateException("The web server cannot write to {$folder}. Unpack the release by hand instead (see INSTALL.md).");
            }
        }
    }

    /**
     * Files in the release-owned folders that the new release no longer ships.
     *
     * @param  array<string, string>  $files  the release's files (path => sha256)
     * @return list<string>
     */
    protected function stale(array $files): array
    {
        $trees = self::PRUNED;

        // Each module the release carries owns its folder; modules installed
        // from the Store sit in other folders and are not listed here.
        foreach (array_keys($files) as $path) {
            if (str_starts_with($path, 'modules/') && str_ends_with($path, '/module.json')) {
                $trees[] = substr($path, 0, -strlen('/module.json'));
            }
        }

        $stale = [];

        foreach ($trees as $tree) {
            $root = $this->path($tree);

            if (! is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->isLink()) {
                    continue;
                }

                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($this->base) + 1));

                if (! isset($files[$relative])) {
                    $stale[] = $relative;
                }
            }
        }

        return $stale;
    }

    /**
     * @param  array{added: list<string>, stashed: list<string>}  $journal
     */
    protected function stash(string $path, string $backup, array &$journal): void
    {
        $to = $backup.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);

        File::ensureDirectoryExists(dirname($to));
        $this->move($this->path($path), $to);

        $journal['stashed'][] = $path;
    }

    /**
     * @param  array{added: list<string>, stashed: list<string>}  $journal
     * @return bool whether something could not be put back
     */
    protected function rollback(array $journal, string $backup): bool
    {
        $failed = false;

        foreach (array_reverse($journal['added']) as $path) {
            if (is_file($this->path($path)) && ! @unlink($this->path($path))) {
                $failed = true;
            }
        }

        foreach (array_reverse($journal['stashed']) as $path) {
            $from = $backup.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);

            try {
                File::ensureDirectoryExists(dirname($this->path($path)));
                $this->move($from, $this->path($path));
            } catch (Throwable) {
                $failed = true;
            }
        }

        return $failed;
    }

    protected function place(string $from, string $to): void
    {
        File::ensureDirectoryExists(dirname($to));
        $this->move($from, $to);
        @chmod($to, 0644);
    }

    protected function move(string $from, string $to): void
    {
        if (@rename($from, $to)) {
            return;
        }

        if (! @copy($from, $to)) {
            throw new UpdateException("Could not write {$to}.");
        }

        @unlink($from);
    }

    /** Keeps the latest backup (the one just made) and drops older ones. */
    protected function pruneBackups(string $work, string $keep): void
    {
        foreach (glob($work.DIRECTORY_SEPARATOR.'backup-*', GLOB_ONLYDIR) ?: [] as $old) {
            if ($old !== $keep) {
                File::deleteDirectory($old);
            }
        }
    }

    protected function down(): void
    {
        if ($this->live) {
            try {
                Artisan::call('down', ['--retry' => 60, '--secret' => Str::random(24)]);
            } catch (Throwable) {
                // Maintenance mode is a courtesy to other visitors, not a requirement.
            }
        }
    }

    protected function up(): void
    {
        if ($this->live) {
            try {
                Artisan::call('up');
            } catch (Throwable) {
            }
        }
    }

    /**
     * What a changed release invalidates: compiled caches, the module list,
     * opcode caches. Then maintenance mode ends.
     */
    protected function finish(): void
    {
        // Package discovery is cached; a release can add or drop packages.
        foreach (['packages.php', 'services.php'] as $cache) {
            @unlink($this->base.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.$cache);
        }

        if (! $this->live) {
            return;
        }

        try {
            ModuleRegistry::refresh();
        } catch (Throwable) {
        }

        foreach (['config:clear', 'route:clear', 'view:clear', 'filament:optimize-clear'] as $command) {
            try {
                Artisan::call($command);
            } catch (Throwable) {
            }
        }

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        $this->up();
    }
}
