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

/**
 * Downloads and installs Roundcube next to the app — Epesi committed it under
 * modules/Libs/RoundCube instead. It can't ship inside the module: it is GPL
 * while this repo is MIT, and a module archive may hold neither its file
 * types nor its file count.
 *
 * Installing again is safe and is how Roundcube is upgraded: the new release
 * replaces the old one, and Roundcube's own bin/initdb.sh --update brings its
 * tables up to date.
 */
class RoundcubeInstaller
{
    public function __construct(
        protected Filesystem $files,
        protected RoundcubeConfigWriter $config,
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
        $result = Process::path(Roundcube::path())
            ->timeout(300)
            ->run([$this->php(), 'bin/initdb.sh', '--dir=SQL', '--update']);

        if ($result->failed()) {
            throw new RuntimeException("Roundcube's database setup failed:\n".trim($result->output()."\n".$result->errorOutput()));
        }

        $progress(trim($result->output()));
    }

    /**
     * The PHP command line, to run Roundcube's own scripts. From a web page
     * PHP_BINARY is the web server itself (httpd.exe under XAMPP's mod_php),
     * so look next to PHP's install and its php.ini instead.
     */
    public function php(): string
    {
        if (PHP_SAPI === 'cli' && PHP_BINARY !== '') {
            return PHP_BINARY;
        }

        $name = windows_os() ? 'php.exe' : 'php';
        $candidates = [PHP_BINDIR.DIRECTORY_SEPARATOR.$name];

        if ($ini = php_ini_loaded_file()) {
            $candidates[] = dirname($ini).DIRECTORY_SEPARATOR.$name;
        }

        if (PHP_BINARY !== '' && preg_match('/php[\d.]*(\.exe)?$/i', basename(PHP_BINARY))) {
            array_unshift($candidates, PHP_BINARY);
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        // Left to the PATH.
        return $name;
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
            if (is_link($entry) || @readlink($entry) !== false) {
                windows_os() ? rmdir($entry) : unlink($entry);
            }
        }

        $this->files->deleteDirectory($directory);
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
