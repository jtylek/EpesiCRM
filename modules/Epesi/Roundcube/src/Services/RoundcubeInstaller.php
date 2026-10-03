<?php

namespace Epesi\Modules\Roundcube\Services;

use Closure;
use Composer\CaBundle\CaBundle;
use Epesi\Modules\Roundcube\Roundcube;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use PharData;
use RuntimeException;
use Throwable;

/**
 * Downloads and installs Roundcube next to the app — Epesi committed it under
 * modules/Libs/RoundCube instead. It can't ship inside the module: it is GPL
 * while this repo is MIT, and a module archive may hold neither its file
 * types nor its file count.
 *
 * Installing again is safe and is how Roundcube is upgraded: the new release
 * replaces the old one, and RoundcubeSchema brings its tables up to date.
 */
class RoundcubeInstaller
{
    public function __construct(
        protected Filesystem $files,
        protected RoundcubeConfigWriter $config,
        protected RoundcubeSchema $schema,
    ) {}

    /**
     * @param  Closure(string): void  $progress
     */
    public function install(string $version, string $sha256, Closure $progress): void
    {
        $progress("Downloading Roundcube {$version}...");
        $archive = $this->download($version, $sha256);

        try {
            $progress('Unpacking...');
            $this->unpack($archive, $version);
        } finally {
            $this->files->delete($archive);
        }

        $this->link($progress);
        $this->configure($progress);
    }

    /**
     * @param  Closure(string): void  $progress
     */
    public function configure(Closure $progress): void
    {
        if (! is_dir(Roundcube::path('program'))) {
            throw new RuntimeException('Roundcube is not installed in '.Roundcube::path().'. Run `php artisan roundcube:install` first.');
        }

        $progress('Writing '.$this->config->write());

        foreach ([storage_path('logs/roundcube'), storage_path('framework/roundcube')] as $directory) {
            $this->files->ensureDirectoryExists($directory);
        }

        $progress('Creating or updating Roundcube\'s database tables...');
        $progress($this->schema->migrate(Roundcube::path('SQL'), (string) config('epesi-roundcube.table_prefix')));
    }

    /** @return string the verified tarball */
    protected function download(string $version, string $sha256): string
    {
        $url = str_replace('{version}', $version, (string) config('epesi-roundcube.release.url'));
        // A PHP without CA certificates configured (XAMPP's, out of the box)
        // can't check any HTTPS certificate; CaBundle falls back to Mozilla's.
        $response = Http::timeout(300)
            ->withOptions(['verify' => CaBundle::getSystemCaRootBundlePath()])
            ->get($url);

        if ($response->failed()) {
            throw new RuntimeException("Downloading {$url} failed (HTTP {$response->status()}).");
        }

        if (! hash_equals(strtolower($sha256), hash('sha256', $response->body()))) {
            throw new RuntimeException("The download's checksum doesn't match {$sha256}; nothing was installed.");
        }

        // PharData picks the format from the file name.
        $archive = storage_path('framework/roundcubemail-'.$version.'-'.bin2hex(random_bytes(4)).'.tar.gz');
        file_put_contents($archive, $response->body());

        return $archive;
    }

    protected function unpack(string $archive, string $version): void
    {
        $staging = Roundcube::path().'.new';
        $previous = Roundcube::path().'.previous';

        $this->files->deleteDirectory($staging);
        (new PharData($archive))->extractTo($staging, null, true);

        $release = $staging.DIRECTORY_SEPARATOR.'roundcubemail-'.$version;

        if (! is_dir($release.DIRECTORY_SEPARATOR.'program')) {
            $this->files->deleteDirectory($staging);

            throw new RuntimeException("The archive has no roundcubemail-{$version} directory.");
        }

        $this->deleteInstall($previous);

        if (is_dir(Roundcube::path())) {
            $this->move(Roundcube::path(), $previous);
        }

        try {
            $this->move($release, Roundcube::path());
        } catch (RuntimeException $e) {
            if (is_dir($previous)) {
                @rename($previous, Roundcube::path());
            }

            throw $e;
        }

        $this->files->deleteDirectory($staging);
        $this->deleteInstall($previous);
    }

    /**
     * On Windows a directory that was just written can't be renamed for a
     * moment ("Access is denied") while a file watcher or virus scanner
     * still has it open, so keep trying for a while.
     */
    protected function move(string $from, string $to): void
    {
        for ($attempt = 1; ! @rename($from, $to); $attempt++) {
            if ($attempt === 30) {
                throw new RuntimeException("Couldn't move {$from} to {$to}: ".(error_get_last()['message'] ?? 'unknown error'));
            }

            sleep(1);
        }
    }

    /**
     * deleteDirectory() follows a Windows junction and empties its target, so
     * the plugin links into the module go first, or the module's own plugin
     * sources would be deleted with the old install.
     */
    protected function deleteInstall(string $directory): void
    {
        foreach (glob($directory.DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR.'*') ?: [] as $entry) {
            $source = realpath(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'roundcube-plugins'.DIRECTORY_SEPARATOR.basename($entry));
            $target = realpath($entry);

            if ($source === false || $target === false || (windows_os() ? strcasecmp($source, $target) !== 0 : $source !== $target)) {
                continue;
            }

            $removed = windows_os() ? @rmdir($entry) : @unlink($entry);

            if (! $removed) {
                throw new RuntimeException("Couldn't remove Roundcube's Epesi plugin link at {$entry}.");
            }
        }

        $lastException = null;

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            try {
                $this->files->deleteDirectory($directory);
            } catch (Throwable $exception) {
                $lastException = $exception;
            }

            clearstatcache(true, $directory);

            if (! is_dir($directory)) {
                return;
            }

            if ($attempt < 30) {
                usleep(250_000);
            }
        }

        if ($lastException !== null) {
            throw $lastException;
        }

        throw new RuntimeException("Couldn't remove the Roundcube installation at {$directory}.");
    }

    /**
     * Roundcube is served straight from public/ (only its public_html), and
     * loads plugins only from its own plugins/ — the epesi_* ones are linked
     * there from the module, so they stay in step with its code.
     *
     * @param  Closure(string): void  $progress
     */
    protected function link(Closure $progress): void
    {
        foreach ($this->files->directories(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'roundcube-plugins') as $plugin) {
            $target = Roundcube::path('plugins'.DIRECTORY_SEPARATOR.basename($plugin));

            if (! file_exists($target)) {
                $this->makeLink($plugin, $target);
            }
        }

        $public = Roundcube::publicPath();
        $webRoot = Roundcube::path('public_html');

        if (! file_exists($public)) {
            $this->makeLink($webRoot, $public);
            $progress("Linked {$public} to {$webRoot}");
        } elseif (realpath($public) !== realpath($webRoot)) {
            throw new RuntimeException("{$public} exists but isn't a link to {$webRoot}; remove it and run the command again.");
        }
    }

    /** Filesystem::link() doesn't report a failed `mklink`, so check. */
    protected function makeLink(string $target, string $link): void
    {
        $this->files->link($target, $link);
        clearstatcache();

        if (realpath($link) !== realpath($target)) {
            throw new RuntimeException("Couldn't link {$link} to {$target}.");
        }
    }
}
