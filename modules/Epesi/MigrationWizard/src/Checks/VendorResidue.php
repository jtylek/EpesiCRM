<?php

namespace Epesi\Modules\MigrationWizard\Checks;

/**
 * Detects legacy-only package folders left in vendor/ because the administrator unpacked the Epesi 3 release
 * zip over the legacy installation without first deleting its own vendor/ (Composer dependencies the legacy's
 * own code pulled in, which Epesi 3 does not ship). Unpacking only overwrites the paths the zip names, so
 * anything else it doesn't recognise is left exactly where it was: harmless to Epesi 3 itself (nothing in it
 * loads them), but untested ground for the migration's later cleanup pass, which does not expect vendor/ to
 * hold anything but the release's own packages.
 *
 * No hardcoded package list: the installation's own vendor/composer/installed.json (written by `composer
 * install` for this exact release, so already on disk once the zip is unpacked) names every package Epesi 3
 * ships; any other top-level organisation folder physically inside vendor/ is legacy residue. Read-only.
 */
final class VendorResidue
{
    /** Top-level vendor/ entries that are never a package organisation folder. */
    private const IGNORE = [
        'composer', 'bin', 'autoload.php', 'autoload_classmap.php', 'autoload_files.php',
        'autoload_namespaces.php', 'autoload_psr4.php', 'autoload_real.php', 'autoload_static.php',
        'installed.json', 'installed.php', 'ClassLoader.php', 'InstalledVersions.php', '.htaccess',
    ];

    /** @return list<string> legacy-only top-level folders found directly inside vendor/, sorted; empty when none or when there is nothing to compare against */
    public function detect(string $root): array
    {
        $vendor = rtrim($root, '/\\').'/vendor';
        $installedPath = $vendor.'/composer/installed.json';
        if (! is_dir($vendor) || ! is_file($installedPath)) {
            return [];
        }
        $known = $this->knownOrganisations($installedPath);
        if ($known === []) {
            return [];
        }

        $found = [];
        foreach (scandir($vendor) ?: [] as $entry) {
            $path = $vendor.'/'.$entry;
            if ($entry === '.' || $entry === '..' || in_array($entry, self::IGNORE, true) || ! is_dir($path) || is_link($path)) {
                continue;
            }
            if (! isset($known[$entry])) {
                $found[] = $entry;
            }
        }
        sort($found, SORT_STRING);

        return $found;
    }

    /** @return array<string, true> organisation name => true, from every package's "vendor/package" name */
    private function knownOrganisations(string $installedPath): array
    {
        $data = json_decode((string) @file_get_contents($installedPath), true);
        $packages = is_array($data) ? ($data['packages'] ?? $data) : [];
        if (! is_array($packages)) {
            return [];
        }
        $known = [];
        foreach ($packages as $package) {
            $name = is_array($package) ? ($package['name'] ?? null) : null;
            if (is_string($name) && str_contains($name, '/')) {
                $known[explode('/', $name, 2)[0]] = true;
            }
        }

        return $known;
    }
}
