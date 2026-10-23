<?php

namespace Epesi\Modules\Roundcube\Services;

use Closure;
use Composer\CaBundle\CaBundle;
use Epesi\Modules\Roundcube\Roundcube;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
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
    /** Marks the files writeEntry() writes, so it rewrites only its own. */
    public const ENTRY_MARKER = 'epesi-roundcube-entry';

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

        $this->configure($progress);
    }

    /**
     * Everything after the download: the web entry, the Epesi plugins, the
     * config and the tables. Safe to run at any time.
     *
     * @param  Closure(string): void  $progress
     */
    public function configure(Closure $progress): void
    {
        if (! is_dir(Roundcube::path('program'))) {
            throw new RuntimeException('Roundcube is not installed in '.Roundcube::path().'. Run `php artisan roundcube:install` first.');
        }

        $this->publish($progress);

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
     * any link among the plugins goes first: installs made before the plugins
     * were copied linked them to the module's own sources.
     */
    protected function deleteInstall(string $directory): void
    {
        foreach (glob($directory.DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR.'*') ?: [] as $entry) {
            if ($this->isLink($entry)) {
                $this->removeLink($entry);
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
     * Puts Roundcube where the web server runs it, without filesystem links:
     * web servers may refuse to follow one. Apache on Windows rejects a
     * junction made without administrator rights (ERROR_UNTRUSTED_MOUNT_POINT),
     * a Linux host may not allow FollowSymLinks, and some deploys drop links.
     *
     * @param  Closure(string): void  $progress
     */
    protected function publish(Closure $progress): void
    {
        $this->copyPlugins();
        $progress('Copied the Epesi plugins into '.Roundcube::path('plugins'));

        $this->writeEntry();
        $progress('Wrote the web entry '.Roundcube::publicPath());

        $this->removeOldEntry($progress);
    }

    /**
     * Copies the plugins again when the module's own ones changed. A module
     * update, a new release or a deploy replaces those without running the
     * installer, so the Mailbox page calls this before it opens Roundcube.
     *
     * @return bool whether they were copied
     */
    public function refreshPlugins(): bool
    {
        if (! is_dir(Roundcube::path('program'))) {
            return false;
        }

        if (@file_get_contents($this->pluginStamp()) === $this->pluginFingerprint()) {
            return false;
        }

        $this->copyPlugins();

        return true;
    }

    /** The module's Roundcube plugins, the source of the copies. */
    protected function pluginSources(): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'roundcube-plugins';
    }

    /** Holds pluginFingerprint() of the plugins last copied. */
    protected function pluginStamp(): string
    {
        return Roundcube::path('plugins'.DIRECTORY_SEPARATOR.'.epesi-plugins');
    }

    protected function pluginFingerprint(): string
    {
        $parts = [];

        foreach ($this->files->allFiles($this->pluginSources(), true) as $file) {
            $parts[] = str_replace('\\', '/', $file->getRelativePathname()).'|'.hash_file('xxh128', $file->getPathname());
        }

        sort($parts);

        return hash('xxh128', implode("\n", $parts));
    }

    /**
     * Roundcube loads plugins only from its own plugins/, so the module's
     * epesi_* plugins are copied there, replacing an earlier copy.
     */
    protected function copyPlugins(): void
    {
        $fingerprint = $this->pluginFingerprint();

        foreach ($this->files->directories($this->pluginSources()) as $source) {
            $target = Roundcube::path('plugins'.DIRECTORY_SEPARATOR.basename($source));

            if ($this->isLink($target)) {
                // Installs made before the plugins were copied linked them.
                $this->removeLink($target);
            } elseif (is_dir($target)) {
                $this->files->deleteDirectory($target);
                clearstatcache(true, $target);

                if (file_exists($target)) {
                    throw new RuntimeException("Couldn't remove the earlier copy of {$target}.");
                }
            }

            if (! $this->files->copyDirectory($source, $target)) {
                throw new RuntimeException("Couldn't copy {$source} to {$target}.");
            }
        }

        if (file_put_contents($this->pluginStamp(), $fingerprint) === false) {
            throw new RuntimeException("Couldn't write {$this->pluginStamp()}.");
        }
    }

    /**
     * public/epesi-webmail is a real directory with Roundcube's .htaccess and
     * two stubs that require its index.php and static.php. Both locate the
     * rest of Roundcube from their own __DIR__, and static.php serves assets
     * from the URL's path info, so they run as if served from public_html.
     * installer.php is left out: Roundcube's installer stays unreachable.
     */
    protected function writeEntry(): void
    {
        $public = Roundcube::publicPath();
        $webRoot = Roundcube::path('public_html');

        if ($this->isLink($public)) {
            // Installs made before the entry was written linked it.
            $this->removeLink($public);
        } elseif (file_exists($public) && ! $this->isEntry($public)) {
            throw new RuntimeException("{$public} exists but wasn't written by the Roundcube installer; move it away and run `php artisan roundcube:install` again.");
        }

        if (! is_dir($public) && ! @mkdir($public, 0755, true)) {
            $parent = dirname($public);

            throw new RuntimeException("Couldn't create {$public}: ".(error_get_last()['message'] ?? 'mkdir() failed').". The web server user must be able to write to {$parent} — make it writable, or run `php artisan roundcube:install` as the directory's owner.");
        }

        $files = [];

        if (is_file($htaccess = $webRoot.DIRECTORY_SEPARATOR.'.htaccess')) {
            $files['.htaccess'] = '# '.self::ENTRY_MARKER.": a copy of Roundcube's public_html/.htaccess, made by `php artisan roundcube:install`.\n\n"
                .file_get_contents($htaccess);
        }

        foreach (['index.php', 'static.php'] as $name) {
            $files[$name] = $this->stub($public, $webRoot.DIRECTORY_SEPARATOR.$name);
        }

        foreach ($files as $name => $content) {
            if (file_put_contents($public.DIRECTORY_SEPARATOR.$name, $content) === false) {
                throw new RuntimeException("Couldn't write {$public}".DIRECTORY_SEPARATOR."{$name}.");
            }
        }
    }

    /** An empty directory, or one whose index.php writeEntry() wrote. */
    protected function isEntry(string $directory): bool
    {
        if (! is_dir($directory)) {
            return false;
        }

        if (array_diff(scandir($directory) ?: [], ['.', '..']) === []) {
            return true;
        }

        $index = $directory.DIRECTORY_SEPARATOR.'index.php';

        return is_file($index) && str_contains((string) file_get_contents($index), self::ENTRY_MARKER);
    }

    /** A PHP file in $directory that runs $target, by a relative path where one exists. */
    protected function stub(string $directory, string $target): string
    {
        $relative = $this->relativePath($directory, $target);
        $path = $relative === null ? var_export($target, true) : '__DIR__.'.var_export('/'.$relative, true);

        return "<?php\n\n"
            .'// '.self::ENTRY_MARKER.": written by `php artisan roundcube:install`, which rewrites it.\n"
            ."// Roundcube is installed outside the web root; this hands the request to it,\n"
            ."// as a link would, but some web servers refuse to follow links.\n"
            ."require {$path};\n";
    }

    /**
     * $target as a path relative to the directory $from, so the stubs survive
     * moving the whole checkout. Null when there is none: both must exist, and
     * on Windows they must be on the same drive.
     */
    protected function relativePath(string $from, string $target): ?string
    {
        $from = realpath($from);
        $target = realpath($target);

        if ($from === false || $target === false) {
            return null;
        }

        $split = fn (string $path): array => array_values(array_filter(preg_split('~[\\\\/]~', $path) ?: [], fn (string $part): bool => $part !== ''));
        $from = $split($from);
        $to = $split($target);

        if (windows_os() && ($from === [] || $to === [] || ! $this->samePath($from[0], $to[0]))) {
            return null;
        }

        while ($from && $to && $this->samePath($from[0], $to[0])) {
            array_shift($from);
            array_shift($to);
        }

        return implode('/', [...array_fill(0, count($from), '..'), ...$to]);
    }

    /**
     * The entry's name before it was renamed to epesi-webmail (hosting panels
     * alias /roundcube to their own Roundcube). A link there to this
     * Roundcube's public_html is a leftover.
     *
     * @param  Closure(string): void  $progress
     */
    protected function removeOldEntry(Closure $progress): void
    {
        $old = dirname(Roundcube::publicPath()).DIRECTORY_SEPARATOR.'roundcube';

        if ($this->samePath($old, Roundcube::publicPath()) || ! $this->isLink($old)) {
            return;
        }

        $target = realpath($old) ?: @readlink($old);
        $webRoot = realpath(Roundcube::path('public_html'));

        if ($target === false || $webRoot === false || ! $this->samePath(rtrim($target, '\\/'), $webRoot)) {
            return;
        }

        $this->removeLink($old);
        $progress("Removed the old link {$old}");
    }

    /**
     * Whether $path is a link rather than a real file or directory. PHP on
     * Windows doesn't report a junction as one: is_link() is false and
     * filetype() is "unknown" (for a dangling junction too), while realpath()
     * resolves it to its target. readlink() can't tell: it returns an
     * ordinary directory's own path.
     */
    protected function isLink(string $path): bool
    {
        clearstatcache(true, $path);

        if (is_link($path)) {
            return true;
        }

        if (@lstat($path) === false) {
            return false;
        }

        if (windows_os() && @filetype($path) === 'unknown') {
            return true;
        }

        $real = realpath($path);
        $parent = realpath(dirname($path));

        return $real !== false && $parent !== false
            && ! $this->samePath($real, $parent.DIRECTORY_SEPARATOR.basename($path));
    }

    /**
     * Removes only the link, never what it points at: rmdir() removes a
     * Windows junction or directory link, unlink() a Unix symlink. Both fail
     * on a real directory that isn't empty. Never Filesystem::deleteDirectory():
     * on Windows it follows a junction and empties its target.
     */
    protected function removeLink(string $link): void
    {
        $removed = windows_os() ? (@rmdir($link) || @unlink($link)) : @unlink($link);
        clearstatcache(true, $link);

        if (! $removed || @lstat($link) !== false) {
            throw new RuntimeException("Couldn't remove the link {$link}. Remove only the link by hand (`rmdir` on Windows, `rm` elsewhere, never a recursive delete) and run `php artisan roundcube:install` again.");
        }
    }

    protected function samePath(string $a, string $b): bool
    {
        return windows_os() ? strcasecmp($a, $b) === 0 : $a === $b;
    }
}
