<?php

namespace Epesi\Modules\MigrationWizard\Cleanup;

use RuntimeException;

/**
 * The only list cleanup needs: the files the Epesi 3 release ships. Every release zip carries it as
 * `update/MANIFEST.txt` (`<sha256>  <path>` per line, written by `epesi:package`, checked by
 * App\Services\Update\CorePackage), and an overlay leaves it in place. There is no list of legacy
 * files: cleanup removes what is not part of the release, not part of Epesi 3's own runtime, not
 * hosting-owned and not preserved data (see LegacyFileClassifier).
 *
 * Fail closed. A missing, unreadable, malformed or implausible manifest throws, because a wrong list
 * would classify the running application itself as removable.
 */
final class CleanupManifests
{
    public const RELEASE_FILE = 'update/MANIFEST.txt';

    /** A release without these is not an Epesi 3 release; refuse before classifying anything. */
    public const MUST_SHIP = ['artisan', 'composer.json', 'public/index.php', 'bootstrap/app.php', 'vendor/autoload.php'];

    /** A real release lists thousands of files (its vendor/ alone is tens of thousands). */
    private const MINIMUM_FILES = 200;

    /** @return array{release: list<string>} */
    /**
     * The release's list when the installation has one (a ZIP install), or an empty list: cleanup then decides by
     * Epesi 3's top-level folders and root files and its modules' module.json alone. A list that is there but
     * unreadable, malformed or implausible still refuses (load()).
     *
     * @return array{release: list<string>}
     */
    public function loadIfPresent(string $root): array
    {
        $path = rtrim($root, '/\\').'/'.self::RELEASE_FILE;

        return file_exists($path) || is_link($path) ? $this->load($root) : ['release' => []];
    }

    public function load(string $root): array
    {
        $path = rtrim($root, '/\\').'/'.self::RELEASE_FILE;
        if (! is_file($path) || is_link($path) || ! is_readable($path)) {
            throw new RuntimeException('The list of the files this release ships ('.self::RELEASE_FILE.') is missing or unreadable; cleanup is unavailable.');
        }
        $release = [];
        foreach (preg_split('/\r?\n/', trim((string) file_get_contents($path))) ?: [] as $line) {
            if (preg_match('/^[0-9a-f]{64}  (.+)$/D', $line, $match) !== 1) {
                throw new RuntimeException('The release file list ('.self::RELEASE_FILE.') is malformed.');
            }
            $file = $match[1];
            if (str_contains($file, "\0") || str_starts_with($file, '/') || preg_match('#(^|/)\.\.?(/|$)#', $file) === 1 || preg_match('#^[A-Za-z]:#', $file) === 1) {
                throw new RuntimeException('The release file list names an invalid path.');
            }
            // A release never ships legacy data or configuration; a list that does is not this release's.
            if ($file === 'data' || str_starts_with($file, 'data/') || $file === '.env') {
                throw new RuntimeException("The release file list names {$file}, which is installation data and cannot be part of a release.");
            }
            $release[$file] = true;
        }
        $release = array_keys($release);
        foreach (self::MUST_SHIP as $required) {
            if (! in_array($required, $release, true)) {
                throw new RuntimeException("The release file list does not name {$required}; refusing to classify the installation with it.");
            }
        }
        if (count($release) < self::MINIMUM_FILES) {
            throw new RuntimeException('The release file list is implausibly short; refusing to classify the installation with it.');
        }

        return ['release' => $release];
    }
}
