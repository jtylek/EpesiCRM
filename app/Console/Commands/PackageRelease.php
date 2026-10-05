<?php

namespace App\Console\Commands;

use App\Services\Update\CorePackage;
use App\Services\Update\UpdateException;
use App\Support\Version;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Builds the zip someone unpacks on a server (or into XAMPP's htdocs) and
 * sets up entirely in the browser — Epesi's downloadable package. It holds
 * everything a git checkout doesn't: the Composer packages (vendor/) and the
 * built frontend assets (public/build/), so the server needs neither Composer
 * nor Node. The first request writes .env (App\Support\Setup\FirstBoot) and
 * the setup wizard does the rest.
 *
 * Takes the application's files from git (what is committed, plus uncommitted
 * changes to them), so nothing local — .env, logs, sessions, uploads — ends up
 * in it. Build the release from a checkout prepared with
 * `composer install --no-dev --optimize-autoloader` and `npm run build`.
 */
class PackageRelease extends Command
{
    protected $signature = 'epesi:package
        {--out= : output directory (default: storage/app/private/releases)}
        {--folder= : a folder to put everything in inside the zip (default: none, the files unpack where the zip is extracted)}
        {--translate : first fill every offered language (old epesi\'s translations, then DeepL) and run the strict translation tests}
        {--legacy= : with --translate, an old epesi checkout to import community translations from first}
        {--test : first run the whole test suite}';

    protected $description = 'Build a release zip (application, vendor/ and built assets) that installs from the browser';

    /**
     * Left out of a release: only a developer uses them. composer.json stays,
     * because Laravel reads the application's namespace from it. So do
     * database/factories/ and DatabaseSeeder, dead weight without Faker, since
     * the optimized autoloader lists them and would include a missing file.
     */
    protected const EXCLUDE = [
        // Notes for developers and their AI assistants.
        'AI-shared/', '.claude/', 'AGENTS.md', 'CLAUDE.md', 'PORTING.md', 'README.md',
        // Git and editor settings.
        '.editorconfig', '.gitattributes', '.gitignore', 'database/.gitignore',
        'tests/', 'phpunit.xml',
        // The frontend's sources: the release has them built, in public/build/.
        'resources/css/', 'resources/js/', 'package.json', 'package-lock.json', 'vite.config.js',
        // vendor/ is packed as installed, and nothing reads the lock file.
        'composer.lock',
    ];

    /** Folders at the top of a package in vendor/ that nothing runs. */
    protected const PACKAGE_CLUTTER_DIRECTORIES = ['.github', '.vscode', 'art', 'docs', 'tests'];

    public function handle(): int
    {
        if (! is_file(base_path('vendor/autoload.php'))) {
            $this->components->error('There is no vendor/ folder. Run "composer install --no-dev --optimize-autoloader" first.');

            return self::FAILURE;
        }

        if (! is_file(public_path('build/manifest.json'))) {
            $this->components->error('The frontend assets aren\'t built. Run "npm run build" first.');

            return self::FAILURE;
        }

        if (is_dir(base_path('vendor/phpunit'))) {
            $this->components->warn('vendor/ includes the development packages (PHPUnit and others). For a smaller release, build from a checkout installed with "composer install --no-dev".');
        }

        // Composer falls back to cloning a package when it can't download its
        // release archive (no GitHub token, a proxy). A clone has the tests,
        // docs and fixtures the archive leaves out, tripling the zip.
        $clones = glob(base_path('vendor/*/*/.git'), GLOB_ONLYDIR) ?: [];

        if ($clones !== []) {
            $this->components->warn(count($clones).' packages in vendor/ are git clones, with their tests and docs (a zip about three times bigger). See "Building the package" in AI-shared/Epesi-Laravel-distro.md to replace them with their release contents.');
        }

        if (($this->option('translate') || $this->option('test')) && ! is_dir(base_path('vendor/phpunit'))) {
            $this->components->error('--translate and --test run the tests, which need the development packages. Run them before "composer install --no-dev", or package without them.');

            return self::FAILURE;
        }

        if ($this->option('translate') && ! $this->translate()) {
            return self::FAILURE;
        }

        if ($this->option('test') && ! $this->runTests(null, 'The test suite failed: no package was written.')) {
            return self::FAILURE;
        }

        $files = $this->applicationFiles();

        if ($files === null) {
            $this->components->error('Could not list the application\'s files with git. Build the release from a git checkout.');

            return self::FAILURE;
        }

        if ($this->option('translate')) {
            // A language file the pass created isn't in git yet, but belongs
            // in this release.
            $new = $this->untrackedTranslationFiles();
            $files = array_values(array_unique([...$files, ...$new]));

            if ($new !== []) {
                $this->components->warn(count($new).' new translation files are packed but not committed yet: commit them, so the next release has them too.');
            }
        }

        $out = rtrim($this->option('out') ?: storage_path('app/private/releases'), '/\\');
        File::ensureDirectoryExists($out);

        $name = $this->releaseName();
        $file = $out.DIRECTORY_SEPARATOR.'epesi-'.$name.'.zip';
        $folder = trim((string) $this->option('folder'), '/\\');
        $prefix = $folder === '' ? '' : $folder.'/';

        $zip = new ZipArchive;

        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->components->error("Could not write {$file}.");

            return self::FAILURE;
        }

        $count = 0;
        $manifest = [];

        foreach ($files as $relative) {
            $zip->addFile(base_path($relative), $prefix.$relative);
            $manifest[$relative] = hash_file('sha256', base_path($relative));
            $count++;
        }

        foreach (['vendor', 'public/build'] as $directory) {
            $finder = Finder::create()->files()->in(base_path($directory))->ignoreDotFiles(false)->ignoreVCS(true);

            foreach ($finder as $entry) {
                $relative = str_replace('\\', '/', $entry->getRelativePathname());

                if ($directory === 'vendor' && $this->isPackageClutter($relative)) {
                    continue;
                }

                $zip->addFile($entry->getPathname(), $prefix.$directory.'/'.$relative);
                $manifest[$directory.'/'.$relative] = hash_file('sha256', $entry->getPathname());
                $count++;
            }
        }

        // A different one in every zip: unpacked over an installation, it
        // tells FrameworkCaches::guard() that the cached config and routes
        // belong to the files it replaced.
        $releaseId = $name.'-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(4))."\n";
        $zip->addFromString($prefix.'bootstrap/release-id', $releaseId);
        $manifest['bootstrap/release-id'] = hash('sha256', $releaseId);
        $count++;

        // What an installation applying this release as an update compares with
        // (App\Services\Update\CorePackage): a checksum per file.
        ksort($manifest);
        $lines = array_map(fn (string $path, string $hash): string => $hash.'  '.$path, array_keys($manifest), $manifest);
        $zip->addFromString($prefix.CorePackage::MANIFEST, implode("\n", $lines)."\n");

        $this->components->task("Writing {$count} files", fn () => $zip->close());

        // The zip must pass the check an installation runs before updating
        // from it (Epesi Store, epesi:update-core): a top-level file that
        // CorePackage::FILES doesn't list would make every installed updater
        // refuse it. A --folder zip is for unpacking by hand, never an update.
        if ($folder === '') {
            try {
                (new CorePackage($file))->close();
            } catch (UpdateException $exception) {
                File::delete($file);
                $this->components->error('The release would be refused as an update: '.$exception->getMessage().' Move the file into one of '.implode(', ', CorePackage::FOLDERS).'/.');

                return self::FAILURE;
            }
        }

        // `sha256sum -c epesi-2.0.zip.sha256` checks a download against it.
        // "\n", not PHP_EOL: built on Windows, a "\r" would become part of
        // the file name sha256sum looks for.
        File::put($file.'.sha256', hash_file('sha256', $file).'  '.basename($file)."\n");

        $this->components->info('Release written to '.$file.' ('.round(filesize($file) / 1048576, 1).' MB), with its checksum in '.basename($file).'.sha256.');

        if (str_contains($name, '-dev.')) {
            $this->components->warn('This commit isn\'t tagged v'.Version::short().', so this is a development build. To build the release itself, tag the commit (git tag v'.Version::short().') and run this again.');
        }

        return self::SUCCESS;
    }

    /**
     * The translations skill, for every offered language but English: old
     * epesi's community translations into the reviewed <code>.json (with
     * --legacy), then the DeepL pass into <code>.machine.json, then the
     * translation tests with TRANSLATIONS_STRICT=1. See
     * AI-shared/Epesi-Laravel-Translations.md.
     */
    protected function translate(): bool
    {
        $script = (string) config('app.translations_script');

        if ($script === '' || ! is_file($script)) {
            $this->components->error('Set TRANSLATIONS_SCRIPT in .env to the translations skill\'s epesi_translation_coverage.py.');

            return false;
        }

        // The DeepL key stays with the script, in the private notes; the
        // script stops with its own message when it has none.
        $locales = array_values(array_diff(array_keys((array) config('app.available_locales')), ['en']));
        $legacy = (string) $this->option('legacy');

        foreach ($locales as $locale) {
            $this->components->info("Translating {$locale}");

            // A language epesi didn't have fails the import; DeepL still covers it.
            if ($legacy !== '' && $this->call('lang:import-epesi', ['locale' => $locale, 'path' => $legacy]) !== self::SUCCESS) {
                $this->components->warn("No community translations imported for {$locale}.");
            }

            $machine = storage_path("framework/testing/deepl-{$locale}.json");

            if (! $this->runStep(['python', $script, 'translate', $locale]) || ! $this->runStep(['python', $script, 'merge', $locale, $machine])) {
                $this->components->error("Translating {$locale} failed: no package was written.");

                return false;
            }
        }

        return $this->runTests('TranslationsTest', 'The strict translation tests failed: no package was written.');
    }

    /**
     * The tests, with TRANSLATIONS_STRICT=1 so an untranslated string fails.
     */
    protected function runTests(?string $filter, string $failure): bool
    {
        $command = [PHP_BINARY, 'artisan', 'test', ...($filter === null ? [] : ["--filter={$filter}"])];

        if (! $this->runStep($command, ['TRANSLATIONS_STRICT' => '1'])) {
            $this->components->error($failure);

            return false;
        }

        return true;
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $env
     */
    protected function runStep(array $command, array $env = []): bool
    {
        $process = new Process($command, base_path(), $env === [] ? null : [...getenv(), ...$env], null, null);
        $process->run(fn (string $type, string $buffer) => $this->output->write($buffer));

        return $process->isSuccessful();
    }

    /**
     * Language files under any lang/ that git doesn't track yet, but ignores
     * neither: a language the translation pass just started.
     *
     * @return list<string>
     */
    protected function untrackedTranslationFiles(): array
    {
        $process = new Process(['git', 'ls-files', '-z', '--others', '--exclude-standard'], base_path());
        $process->run();

        return array_values(array_filter(
            explode("\0", $process->getOutput()),
            fn (string $path): bool => preg_match('#(^|/)lang/[^/]+\.json$#', $path) === 1,
        ));
    }

    /**
     * The files git tracks, as they are on disk now.
     *
     * @return list<string>|null
     */
    protected function applicationFiles(): ?array
    {
        $process = new Process(['git', 'ls-files', '-z'], base_path());
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $files = array_filter(explode("\0", $process->getOutput()), function (string $path): bool {
            foreach (self::EXCLUDE as $excluded) {
                if ($path === $excluded || str_starts_with($path, $excluded)) {
                    return false;
                }
            }

            // Deleted but not yet committed.
            return $path !== '' && is_file(base_path($path));
        });

        return array_values($files);
    }

    /**
     * Whether a file in vendor/ (the path inside it, "livewire/livewire/dist/…")
     * is something its package ships but no request or command uses: docs,
     * tests, CI and tool settings, source maps. About a quarter of the zip.
     * License files always stay, since the licenses require them.
     */
    protected function isPackageClutter(string $path): bool
    {
        $segments = explode('/', $path);
        $name = end($segments);

        if (preg_match('/^(licen[cs]e|copying|notice)/i', $name)) {
            return false;
        }

        // Command-line shortcuts to packages' tools (psysh, carbon).
        if ($segments[0] === 'bin') {
            return true;
        }

        if (count($segments) > 3 && in_array($segments[2], self::PACKAGE_CLUTTER_DIRECTORIES, true)) {
            return true;
        }

        // Filament's scripts, styles and fonts, which the release already has
        // in public/: `filament:assets` copied them there, and only it reads these.
        if ($segments[0] === 'filament' && ($segments[2] ?? null) === 'dist') {
            return true;
        }

        // Livewire serves livewire(.csp)(.min).js itself; the .esm builds are for
        // bundlers, and the images are uploads for its own browser tests.
        if ($segments[0] === 'livewire' && preg_match('/^(livewire(\.csp)?\.esm\.js|browser_test_image)/', $name)) {
            return true;
        }

        return str_ends_with(strtolower($name), '.md')
            || str_ends_with($name, '.map')
            || preg_match('/^(phpunit\.xml|phpstan.*\.neon|psalm\.xml|\.php[-_]cs|rector\.php$|\.editorconfig$|\.gitattributes$|\.gitignore$)/', $name) === 1;
    }

    /**
     * The version from the VERSION file ("2.0"), when this commit is tagged
     * as that release (v2.0 or v2.0.0); otherwise a development build of it,
     * named after its commit ("2.0-dev.20260925.1a2b3c4"), so a test build is
     * never mistaken for the release.
     */
    protected function releaseName(): string
    {
        $tags = new Process(['git', 'tag', '--points-at', 'HEAD'], base_path());
        $tags->run();
        $onHead = preg_split('/\R/', trim($tags->getOutput())) ?: [];

        foreach ([Version::short(), Version::current()] as $version) {
            if (array_intersect(['v'.$version, $version], $onHead) !== []) {
                return Version::short();
            }
        }

        $commit = new Process(['git', 'log', '-1', '--format=%cd.%h', '--date=format:%Y%m%d'], base_path());
        $commit->run();
        $suffix = trim($commit->getOutput());

        return Version::short().'-dev'.($commit->isSuccessful() && $suffix !== '' ? '.'.$suffix : '');
    }
}
