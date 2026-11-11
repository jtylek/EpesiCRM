<?php

namespace Epesi\Modules\MigrationWizard\Cleanup;

use Epesi\Modules\MigrationWizard\Files\LegacyAreaCheck;
use RuntimeException;

/**
 * Classification of the overlaid installation directory for cleanup. Cleanup removes what is not
 * part of Epesi 3 and not preserved: there is no list of legacy files, so the classes say what
 * STAYS, and what is left is what goes.
 *
 *  keep       Epesi 3's top-level folders and root files (DEFAULT_KEEP, EPESI_ROOT_FILES), every modules/ folder
 *             with a module.json (an Epesi 3 module), and, when the release's list (update/MANIFEST.txt) is
 *             there, every file it names: the list is a safety net, not required
 *  hosting    paths the hosting owns (.well-known/, cgi-bin/, .user.ini, php.ini, ...): deleting
 *             them can take a site down, so they stay and are reported
 *  preserved  data that was not migrated and legacy modules/ directories whose module was not
 *             migrated by a completed importer (Premium, Custom and every other module without one),
 *             and every file still in a protected data area (CRM_Mail, Utils_FileStorage): they stay
 *  legacy_data  a data/ area the verifier released (migrated, or discarded by name): removed
 *  legacy_code  legacy code: a legacy modules/ directory whose module was migrated, a stock legacy
 *             root file or folder, and anything in vendor/ that the release does not list: removed
 *  unknown    any other path (user-added, or left by another tool): archived first, then removed
 *
 * Symbolic links are listed as entries but never followed, so a link can never make the scan or a
 * later removal leave the directory.
 */
final class LegacyFileClassifier
{
    public const KEEP = 'keep';

    public const HOSTING = 'hosting';

    public const LEGACY_DATA = 'legacy_data';

    public const LEGACY_CODE = 'legacy_code';

    public const PRESERVED = 'preserved';

    public const UNKNOWN = 'unknown';

    /** Migrated business data: its files are moved out by the file importers; whatever is still in it stays (class preserved), never removed. */
    public const PROTECTED_AREAS = ['data/CRM_Mail', 'data/Utils_FileStorage'];

    /** Epesi 3's runtime and its own folders (a legacy installation has none of these names). A trailing slash means a directory. */
    public const DEFAULT_KEEP = ['.env', 'app/', 'bootstrap/', 'config/', 'database/', 'lang/', 'legacy-modules/', 'public/', 'resources/', 'routes/', 'storage/', 'update/'];

    /**
     * The files at the top of an Epesi 3 release (the repository's root, less PackageRelease::EXCLUDE). Kept by name,
     * so cleanup needs no list of files: a legacy file of the same name (cron.php, index.php) was overwritten by it.
     */
    public const EPESI_ROOT_FILES = ['.env.example', '.htaccess', 'INSTALL.md', 'LICENSE', 'VERSION', 'artisan', 'composer.json', 'cron.php', 'index.php'];

    /** Owned by the hosting or its installer: kept, whatever else is true, and reported. */
    public const HOSTING_KEEP = [
        '.well-known/', 'cgi-bin/', '.user.ini', 'php.ini', '.htpasswd', '.htpasswds/', 'error_log', '.ftpquota', '.cpanel/', '.private/',
        'softperms.txt', 'softsql.sql', 'softver.txt', 'stats/', '.htaccess.cpanel.bak',
    ];

    /** The top-level files and folders of the stock legacy 1.9.1 distribution that Epesi 3 does not ship: removed without an archive (the pre-upgrade backup holds them). */
    public const STOCK_LEGACY_ROOT = [
        'PEAR.php', 'README.md', 'admin', 'ajax.php', 'check.php', 'codeception.yml', 'composer.lock', 'console', 'console.php',
        'debug.php', 'docs', 'fonts', 'htaccess.txt', 'images', 'include', 'include.php', 'init_js.php', 'install.txt', 'libs',
        'mobile.css', 'mobile.php', 'monitoring.php', 'playbook.yml', 'process.php', 'serve.php', 'setup.css', 'setup.php', 'tests',
        'update.php',
    ];

    /** A user's backups are never archived and never removed: files with these endings (case-insensitive) anywhere outside data/. */
    public const BACKUP_ENDINGS = ['.zip', '.tar', '.tar.gz', '.tgz', '.gz', '.bz2', '.7z', '.rar', '.sql', '.sql.gz', '.bak'];

    /** Data areas that are never archived and never counted toward the archive cap (they are logs and caches); otherwise the data/ rules apply. */
    public const UNARCHIVED_AREAS = ['data/logs', 'data/cache'];

    private const EXECUTABLE = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'inc', 'pl', 'cgi', 'sh', 'py', 'htaccess'];

    /**
     * @param  list<string>  $releaseManifest  every file path the Epesi 3 release ships (relative, '/' separated)
     * @param  array{keep?: list<string>, hosting?: list<string>, installed_modules?: list<string>, exclude?: list<string>, releasable_areas?: list<string>, migrated_modules?: list<string>}  $options
     *                                                                                                                                                                                                   keep: extra runtime paths; hosting: extra hosting-owned paths; installed_modules: relative directories of registered Epesi 3 modules;
     *                                                                                                                                                                                                   exclude: absolute paths (backup destination, migration state) skipped entirely;
     *                                                                                                                                                                                                   releasable_areas: data/ areas ("data/<dir>", "data/*") the verifier released (every other data file stays);
     *                                                                                                                                                                                                   migrated_modules: legacy modules (e.g. CRM_Contacts) a completed importer migrated (their directories may go)
     * @return array{
     *     entries: array<string, array{class: string, action: string, executable: bool, link: bool}>,
     *     counts: array<string, int>,
     *     executable_outside_manifest: list<string>,
     *     preserved_executable: list<string>,
     *     hosting_kept: list<string>,
     *     backups_left_in_place: list<string>
     * }
     */
    public function classify(string $root, array $releaseManifest, array $options = []): array
    {
        $realRoot = realpath($root);
        if ($realRoot === false || ! is_dir($realRoot)) {
            throw new RuntimeException('The installation directory does not exist.');
        }
        $context = [
            'release' => array_fill_keys(array_map($this->relative(...), $releaseManifest), true),
            'keep' => array_map($this->relative(...), [...self::DEFAULT_KEEP, ...self::EPESI_ROOT_FILES, ...($options['keep'] ?? [])]),
            'hosting' => array_map($this->relative(...), [...self::HOSTING_KEEP, ...($options['hosting'] ?? [])]),
            'modules' => array_map(fn (string $path): string => rtrim($this->relative($path), '/').'/', $options['installed_modules'] ?? []),
            'exclude' => [],
            'releasable' => array_map('strval', $options['releasable_areas'] ?? []),
            'migrated' => array_fill_keys(array_map('strval', $options['migrated_modules'] ?? []), true),
        ];
        foreach ($options['exclude'] ?? [] as $path) {
            $real = realpath($path);
            if ($real !== false) {
                $context['exclude'][] = str_replace('\\', '/', $real); // compared with forward slashes (Windows)
            }
        }

        $entries = [];
        $hosting = [];
        $this->walk($realRoot, '', null, $context, $entries, $hosting, false);
        ksort($entries, SORT_STRING);

        $counts = [self::KEEP => 0, self::HOSTING => 0, self::LEGACY_DATA => 0, self::LEGACY_CODE => 0, self::PRESERVED => 0, self::UNKNOWN => 0];
        $executable = [];
        $preserved = [];
        foreach ($entries as $path => $entry) {
            $counts[$entry['class']]++;
            if ($entry['executable'] && in_array($entry['class'], [self::PRESERVED, self::HOSTING], true)) {
                $preserved[] = $path;
            } elseif ($entry['executable'] && $entry['class'] !== self::KEEP) {
                $executable[] = $path;
            }
        }
        $backups = array_keys(array_filter($entries, static fn (array $entry): bool => $entry['action'] === 'backup_left_in_place'));
        $hosting = array_values(array_unique($hosting));
        sort($hosting, SORT_STRING);

        return ['entries' => $entries, 'counts' => $counts, 'executable_outside_manifest' => $executable, 'preserved_executable' => $preserved, 'hosting_kept' => $hosting, 'backups_left_in_place' => $backups];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, array<string, mixed>>  $entries
     * @param  list<string>  $hosting
     */
    private function walk(string $absolute, string $relative, ?string $module, array $context, array &$entries, array &$hosting, bool $epesiModule): void
    {
        $names = @scandir($absolute);
        if ($names === false) {
            // An unreadable directory can hide code; it is kept and listed rather than guessed at.
            $entries[$relative === '' ? '.' : $relative.'/'] = ['class' => self::PRESERVED, 'action' => 'unreadable_directory_left_alone', 'executable' => true, 'link' => false];

            return;
        }
        foreach ($names as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $relative === '' ? $name : $relative.'/'.$name;
            $full = $absolute.'/'.$name;
            if (in_array(str_replace('\\', '/', $full), $context['exclude'], true)) {
                continue;
            }
            $isDirectory = is_dir($full) && ! is_link($full);
            if ($this->matches($path, $isDirectory, $context['hosting'])) {
                $hosting[] = $path;

                // Hosting-owned: kept whole, nothing below it is classified.
                continue;
            }
            if ($isDirectory) {
                // An Epesi 3 module (it has a module.json) is kept whole, registered or not.
                $inModule = $epesiModule || (str_starts_with($path, 'modules/') && is_file($full.'/module.json'));
                $this->walk($full, $path, $this->moduleOf($path, $full, $module), $context, $entries, $hosting, $inModule);

                continue;
            }
            $entries[$path] = $epesiModule
                ? ['class' => self::KEEP, 'action' => 'keep', 'executable' => $this->isExecutable($path), 'link' => is_link($full)]
                : $this->entry($path, is_link($full), $module, $context);
        }
    }

    /**
     * The legacy module a directory below modules/ belongs to (modules/CRM/Contacts is CRM_Contacts; a nested
     * directory that holds its own module files is that module), or the enclosing one.
     */
    private function moduleOf(string $path, string $full, ?string $enclosing): ?string
    {
        $segments = explode('/', $path);
        if ($segments[0] !== 'modules' || in_array($segments[1] ?? '', ['Epesi', 'Premium', 'Custom'], true)) {
            return null;
        }
        if (count($segments) === 3) {
            return $segments[1].'_'.$segments[2];
        }
        if (count($segments) > 3 && $enclosing !== null) {
            $base = end($segments);
            foreach (['Install.php', '_0.php', 'Common_0.php'] as $suffix) {
                if (is_file($full.'/'.$base.$suffix)) {
                    return $enclosing.'_'.$base;
                }
            }
        }

        return $enclosing;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{class: string, action: string, executable: bool, link: bool}
     */
    private function entry(string $path, bool $link, ?string $module, array $context): array
    {
        $executable = $this->isExecutable($path);
        $make = static fn (string $class, string $action): array => ['class' => $class, 'action' => $action, 'executable' => $executable, 'link' => $link];

        if (isset($context['release'][$path]) || $this->matches($path, false, $context['keep'])) {
            return $make(self::KEEP, 'keep');
        }
        foreach ($context['modules'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $make(self::KEEP, 'keep');
            }
        }
        if (! str_starts_with($path, 'data/') && self::isBackupFile($path)) {
            return $make(self::PRESERVED, 'backup_left_in_place');
        }
        if (str_starts_with($path, 'data/')) {
            $area = self::dataArea($path);
            if (! in_array($area, self::PROTECTED_AREAS, true) && in_array($area, $context['releasable'], true)) {
                return $make(self::LEGACY_DATA, 'remove_after_file_verification');
            }
            // A business-file area the verifier released (its importer completed with a clean file ledger) holds,
            // by now, only files no imported record uses (orphans: an archived mail's files whose mail was deleted,
            // a never-finished archive); they go too, except what stays by design (the legacy mail key).
            if (in_array($area, self::PROTECTED_AREAS, true) && in_array($area, $context['releasable'], true)
                && ! LegacyAreaCheck::staysByDesign($path)) {
                return $make(self::LEGACY_DATA, 'remove_unreferenced_business_file');
            }

            return $make(self::PRESERVED, in_array($area, self::PROTECTED_AREAS, true) ? 'leave_in_place' : 'data_not_migrated');
        }
        if (str_starts_with($path, 'modules/')) {
            if ($module !== null && isset($context['migrated'][$module])) {
                return $make(self::LEGACY_CODE, 'remove_migrated_module');
            }

            // Premium, Custom, other legacy modules, anything with no completed importer: not loaded, harmless, kept.
            return $make(self::PRESERVED, 'module_not_migrated');
        }
        $top = explode('/', $path)[0];
        if ($top === 'vendor') {
            // With the release's list, a vendor/ file it does not name is a legacy leftover; without one, vendor/ is
            // Epesi 3's (the Upgrade Guide has the administrator delete the legacy vendor/ before uploading).
            return $context['release'] === [] ? $make(self::KEEP, 'keep_vendor_without_release_list') : $make(self::LEGACY_CODE, 'remove_stale_vendor_file');
        }
        if (in_array($top, self::STOCK_LEGACY_ROOT, true)) {
            return $make(self::LEGACY_CODE, 'remove_legacy_file');
        }

        return $make(self::UNKNOWN, 'archive_then_remove');
    }

    public static function isBackupFile(string $path): bool
    {
        $name = strtolower(basename($path));
        foreach (self::BACKUP_ENDINGS as $ending) {
            if (str_ends_with($name, $ending)) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<string>  $rules */
    private function matches(string $path, bool $isDirectory, array $rules): bool
    {
        foreach ($rules as $rule) {
            if (str_ends_with($rule, '/')) {
                if (($isDirectory && $path.'/' === $rule) || str_starts_with($path.'/', $rule)) {
                    return true;
                }
            } elseif ($path === $rule && ! $isDirectory) {
                return true;
            }
        }

        return false;
    }

    /** The data/ file area of a path: "data/<dir>" for files below a directory, "data/*" for loose files. */
    public static function dataArea(string $path): string
    {
        $segments = explode('/', $path);

        return count($segments) > 2 ? $segments[0].'/'.$segments[1] : 'data/*';
    }

    private function isExecutable(string $path): bool
    {
        $base = strtolower(basename($path));
        if ($base === '.htaccess' || $base === '.user.ini') {
            return true;
        }

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXECUTABLE, true);
    }

    private function relative(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, "\0") || preg_match('#(^|/)\.\.?(/|$)#', $path) === 1) {
            throw new RuntimeException('Invalid path in a cleanup list.');
        }

        return $path;
    }
}
