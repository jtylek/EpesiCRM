<?php

namespace App\Services\Modules;

use App\Support\Modules\ModuleManifest;
use ZipArchive;

/**
 * A module zip, validated before anything is written to disk.
 *
 * Extracting an uploaded archive into the application tree is arbitrary file
 * write, and then arbitrary code execution, so every entry is checked *before*
 * ZipArchive::extractTo() is allowed to run.
 */
class ModuleArchive
{
    protected ?ZipArchive $zip = null;

    protected ?ModuleManifest $manifest = null;

    /** Top-level "Vendor/Name" every entry must live under. */
    protected ?string $root = null;

    public function __construct(protected string $path) {}

    public function manifest(): ModuleManifest
    {
        return $this->manifest ??= $this->validate();
    }

    /**
     * Extracts the validated archive; the module directory ends up at
     * <$directory>/<Vendor>/<Name>.
     */
    public function extractTo(string $directory): string
    {
        $manifest = $this->manifest();

        if (! $this->zip->extractTo($directory)) {
            throw new ModuleException('Could not extract the module archive.');
        }

        return rtrim($directory, '/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $manifest->path);
    }

    public function close(): void
    {
        $this->zip?->close();
        $this->zip = null;
    }

    protected function validate(): ModuleManifest
    {
        $this->assertFileSize();
        $this->open();

        $config = config('modules.zip');

        if ($this->zip->numFiles > $config['max_entries']) {
            throw new ModuleException("The archive has too many entries (limit {$config['max_entries']}).");
        }

        $this->root = $this->findRoot();

        $this->assertEntriesAreSafe($config);

        $manifest = ModuleManifest::fromJson(
            $this->zip->getFromName($this->root.'/module.json') ?: '',
        );

        if ($manifest->path !== $this->root) {
            throw new ModuleException("module.json declares path \"{$manifest->path}\" but the archive contains \"{$this->root}\".");
        }

        $this->assertMigrationsAreNamespaced($manifest);

        return $manifest;
    }

    protected function assertFileSize(): void
    {
        if (! is_file($this->path)) {
            throw new ModuleException('The module archive could not be read.');
        }

        $max = (int) config('modules.zip.max_size');

        if (filesize($this->path) > $max) {
            throw new ModuleException('The module archive is larger than '.round($max / 1048576).' MB.');
        }
    }

    protected function open(): void
    {
        $zip = new ZipArchive;
        $opened = $zip->open($this->path, ZipArchive::RDONLY);

        if ($opened !== true) {
            throw new ModuleException('The uploaded file is not a readable zip archive.');
        }

        $this->zip = $zip;
    }

    /**
     * Exactly one <Vendor>/<Name>/module.json — that pins the whole archive to a
     * single module and gives every other entry a required prefix. The path may
     * be nested ("Epesi/CRM/Contacts"), matching ModuleManifest's path rule.
     *
     * Still exactly *one*: an archive bundling a parent and its children would
     * have to be installed shallowest-first, and nothing here orders entries.
     * Ship the children as their own zips and let `requires:` tie them together.
     */
    protected function findRoot(): string
    {
        $roots = [];

        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $name = $this->zip->getNameIndex($i);

            if (preg_match('#^([A-Za-z][A-Za-z0-9]*(?:/[A-Za-z][A-Za-z0-9]*)+)/module\.json$#', (string) $name, $matches)) {
                $roots[] = $matches[1];
            }
        }

        if (count($roots) !== 1) {
            throw new ModuleException(
                $roots === []
                    ? 'No module.json found — the archive must contain one <Vendor>/<Name>/module.json.'
                    : 'The archive contains more than one module.json; install one module at a time.',
            );
        }

        return $roots[0];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function assertEntriesAreSafe(array $config): void
    {
        $uncompressed = 0;

        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $stat = $this->zip->statIndex($i);
            $name = (string) $stat['name'];

            $this->assertPathIsContained($name);

            if (str_ends_with($name, '/')) {
                continue;
            }

            $this->assertNotSymlink($i, $name);
            $this->assertExtensionAllowed($name, $config);

            $uncompressed += (int) $stat['size'];
        }

        if ($uncompressed > (int) $config['max_uncompressed_size']) {
            throw new ModuleException('The archive expands to more than '.round($config['max_uncompressed_size'] / 1048576).' MB.');
        }
    }

    /**
     * Zip-slip: an entry whose path escapes the archive root would be written
     * anywhere the web user can write. Backslashes and drive letters are
     * rejected too, since this app also runs on Windows.
     */
    protected function assertPathIsContained(string $name): void
    {
        if ($name === '' || str_contains($name, '\\') || str_contains($name, ':') || str_starts_with($name, '/')) {
            throw new ModuleException("Refusing archive entry with an unsafe path: {$name}");
        }

        foreach (explode('/', $name) as $segment) {
            if ($segment === '..' || $segment === '.') {
                throw new ModuleException("Refusing archive entry with a relative path segment: {$name}");
            }
        }

        if (! str_starts_with($name, $this->root.'/')) {
            throw new ModuleException("Archive entry outside the module directory: {$name}");
        }
    }

    protected function assertNotSymlink(int $index, string $name): void
    {
        $attributes = 0;
        $system = 0;

        if (! $this->zip->getExternalAttributesIndex($index, $system, $attributes)) {
            return;
        }

        if ($system !== ZipArchive::OPSYS_UNIX) {
            return;
        }

        if ((($attributes >> 16) & 0xF000) === 0xA000) {
            throw new ModuleException("Refusing symlink in archive: {$name}");
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function assertExtensionAllowed(string $name, array $config): void
    {
        $basename = basename($name);
        $extension = strtolower((string) pathinfo($basename, PATHINFO_EXTENSION));

        if ($extension === '') {
            if (! in_array($basename, $config['allowed_filenames'], true)) {
                throw new ModuleException("Refusing archive entry with no file extension: {$name}");
            }

            return;
        }

        if (! in_array($extension, $config['allowed_extensions'], true)) {
            throw new ModuleException("Refusing archive entry of disallowed type .{$extension}: {$name}");
        }
    }

    /**
     * Laravel's `migrations` table records a migration by basename alone, so two
     * modules shipping the same filename would silently leave the second one
     * unapplied. Requiring the module's own slug in the name makes that
     * collision impossible instead of merely unlikely.
     */
    protected function assertMigrationsAreNamespaced(ModuleManifest $manifest): void
    {
        $prefix = $this->root.'/database/migrations/';
        $slug = $manifest->migrationSlug();

        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $name = (string) $this->zip->getNameIndex($i);

            if (! str_starts_with($name, $prefix) || str_ends_with($name, '/')) {
                continue;
            }

            $basename = basename($name);

            if (! preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_/', $basename)) {
                throw new ModuleException("Migration {$basename} does not start with a timestamp.");
            }

            if (! str_contains($basename, $slug)) {
                throw new ModuleException("Migration {$basename} must include the module slug \"{$slug}\" in its filename.");
            }
        }
    }
}
