<?php

namespace App\Services\Update;

use ZipArchive;

/**
 * A core release zip (what `php artisan epesi:package` builds), validated
 * before anything is written to disk.
 *
 * Applying it overwrites the application's own code, so every entry is checked
 * first: no path escapes (`..`, absolute paths, drive letters, backslashes), no
 * symlinks, only the folders and files a release contains, and a manifest
 * (update/MANIFEST.txt: "<sha256>  <path>" per file) that lists exactly the
 * files in the archive. The manifest is also what lets an update skip unchanged
 * files and delete the ones a release no longer ships.
 */
class CorePackage
{
    public const MANIFEST = 'update/MANIFEST.txt';

    public const MAX_ENTRIES = 100000;

    public const MAX_UNCOMPRESSED_BYTES = 600 * 1048576;

    /** Top-level folders a release holds. */
    public const FOLDERS = ['app', 'bootstrap', 'config', 'database', 'lang', 'modules', 'public', 'resources', 'routes', 'storage', 'update', 'vendor'];

    /**
     * Top-level files a release holds. Frozen as of 2.0.0RC2: every installed
     * updater refuses a zip with a top-level file or folder it doesn't list,
     * so a new file goes into one of FOLDERS (config/nginx.conf.example), never
     * at the top. epesi:package checks its zip against this before writing it.
     */
    public const FILES = ['.env.example', '.htaccess', 'INSTALL.md', 'LICENSE', 'VERSION', 'artisan', 'composer.json', 'cron.php', 'index.php'];

    protected ZipArchive $zip;

    /** @var array<string, string> path => sha256, from the manifest */
    protected array $manifest = [];

    protected string $version = '';

    public function __construct(protected string $path)
    {
        $this->zip = new ZipArchive;

        if (! is_file($path) || $this->zip->open($path) !== true) {
            throw new UpdateException('The update is not a readable zip archive.');
        }

        try {
            $this->validate();
        } catch (UpdateException $exception) {
            $this->zip->close();

            throw $exception;
        }
    }

    public function version(): string
    {
        return $this->version;
    }

    /**
     * @return array<string, string> path => sha256
     */
    public function files(): array
    {
        return $this->manifest;
    }

    /**
     * @param  list<string>  $paths
     */
    public function extract(string $directory, array $paths): void
    {
        if ($paths !== [] && ! $this->zip->extractTo($directory, $paths)) {
            throw new UpdateException('Could not extract the update.');
        }
    }

    public function close(): void
    {
        $this->zip->close();
    }

    protected function validate(): void
    {
        if ($this->zip->numFiles > self::MAX_ENTRIES) {
            throw new UpdateException('The update has too many entries.');
        }

        $entries = [];
        $size = 0;

        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $stat = $this->zip->statIndex($i);
            $name = (string) $stat['name'];

            if (str_ends_with($name, '/')) {
                $this->assertSafe(rtrim($name, '/'));

                continue;
            }

            $this->assertSafe($name);
            $this->assertNotSymlink($i, $name);

            $size += (int) $stat['size'];
            $entries[$name] = true;
        }

        if ($size > self::MAX_UNCOMPRESSED_BYTES) {
            throw new UpdateException('The update is larger than the allowed size once unpacked.');
        }

        $this->manifest = $this->readManifest();

        unset($entries[self::MANIFEST]);

        $missing = array_diff_key($this->manifest, $entries);
        $unlisted = array_diff_key($entries, $this->manifest);

        if ($missing !== [] || $unlisted !== []) {
            throw new UpdateException('The update does not match its manifest ('.(count($missing) + count($unlisted)).' files differ).');
        }

        foreach (['VERSION', 'artisan', 'composer.json', 'vendor/autoload.php', 'public/index.php'] as $required) {
            if (! isset($this->manifest[$required])) {
                throw new UpdateException("This is not an epesi release: {$required} is missing.");
            }
        }

        $this->version = trim((string) $this->zip->getFromName('VERSION'));

        if (! preg_match('/^\d+\.\d+\.\d+[A-Za-z0-9.\-]*$/', $this->version)) {
            throw new UpdateException('The update does not state a valid version.');
        }
    }

    protected function assertSafe(string $name): void
    {
        $segments = explode('/', $name);

        if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\') || str_contains($name, ':') || str_starts_with($name, '/')) {
            throw new UpdateException("The update has an unsafe path: {$name}.");
        }

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || $segment === '.git') {
                throw new UpdateException("The update has an unsafe path: {$name}.");
            }
        }

        $allowed = count($segments) === 1
            ? in_array($name, [...self::FILES, ...self::FOLDERS], true)
            : in_array($segments[0], self::FOLDERS, true);

        if (! $allowed) {
            throw new UpdateException("The update contains something a release does not: {$name}.");
        }
    }

    protected function assertNotSymlink(int $index, string $name): void
    {
        $opsys = 0;
        $attributes = 0;

        $this->zip->getExternalAttributesIndex($index, $opsys, $attributes);

        if ($opsys === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000) {
            throw new UpdateException("The update contains a symbolic link: {$name}.");
        }
    }

    /**
     * @return array<string, string>
     */
    protected function readManifest(): array
    {
        $text = $this->zip->getFromName(self::MANIFEST);

        if ($text === false) {
            throw new UpdateException('The update has no '.self::MANIFEST.'; build it with the current epesi:package.');
        }

        $files = [];

        foreach (preg_split('/\r?\n/', trim($text)) as $line) {
            if (! preg_match('/^([0-9a-f]{64})  (.+)$/', $line, $match)) {
                throw new UpdateException('The update manifest is malformed.');
            }

            $files[$match[2]] = $match[1];
        }

        return $files;
    }
}
