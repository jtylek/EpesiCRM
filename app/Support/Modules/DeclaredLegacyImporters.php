<?php

namespace App\Support\Modules;

use App\Models\Module;
use App\Services\LegacyImport\ImporterRegistry;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * The `import:legacy` importers a module declares in its module.json:
 *
 *     "legacy_importers": [{"tab": "shoutbox", "class": "Epesi\\Modules\\Shoutbox\\LegacyImport\\ShoutboxImporter", "first": true}]
 *
 * A declaration, not a provider's boot(), because the Migration Wizard has to know a module's
 * importers before the module is installed: on a still-legacy site the shipped core modules'
 * providers are not loaded (the `modules` table is the legacy one), yet the scan must list what
 * the migration is about to install, and the browser run must see the same importers as the CLI.
 * Reading a manifest takes no database access and loads no provider, so it is safe in register().
 *
 * `first` / `last` mean what they do in ImporterRegistry::register().
 */
final class DeclaredLegacyImporters
{
    /**
     * @param  list<array<string, mixed>>  $modules  registry-style entries (at least `path` and `namespace`)
     */
    public static function register(ImporterRegistry $registry, array $modules): void
    {
        foreach ($modules as $module) {
            $namespace = (string) ($module['namespace'] ?? '');

            foreach (self::declared((string) ($module['path'] ?? '')) as $entry) {
                $class = ltrim((string) ($entry['class'] ?? ''), '\\');
                $tab = (string) ($entry['tab'] ?? '');

                // Same rule as provider_class: only a class inside the module's own namespace.
                if ($tab === '' || $namespace === '' || ! str_starts_with($class, $namespace)) {
                    continue;
                }

                $registry->register($tab, $class, first: (bool) ($entry['first'] ?? false), last: (bool) ($entry['last'] ?? false));
            }
        }
    }

    /**
     * Registry-style entries for every shipped core module (`"core": true`, the ones the migration
     * installs) that declares legacy importers, read from the manifests on disk.
     *
     * @return list<array{id: string, path: string, namespace: string}>
     */
    public static function shippedCoreModules(): array
    {
        $directory = (string) config('modules.path');
        $found = [];

        foreach (is_dir($directory) ? Finder::create()->files()->in($directory)->name('module.json')->sortByName() : [] as $file) {
            try {
                $manifest = ModuleManifest::fromJson($file->getContents());
            } catch (Throwable) {
                continue;
            }

            if ($manifest->core && is_array($manifest->raw['legacy_importers'] ?? null)) {
                $found[$manifest->id] = ['id' => $manifest->id, 'path' => $manifest->path, 'namespace' => $manifest->namespace];
            }
        }

        ksort($found);

        return array_values($found);
    }

    /** @return list<array<string, mixed>> */
    private static function declared(string $path): array
    {
        $file = Module::directoryFor($path).DIRECTORY_SEPARATOR.'module.json';

        if ($path === '' || ! is_file($file)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($file), true);
        $declared = is_array($data) ? ($data['legacy_importers'] ?? []) : [];

        return is_array($declared) ? array_values(array_filter($declared, 'is_array')) : [];
    }
}
