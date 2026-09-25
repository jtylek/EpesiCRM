<?php

namespace App\Console\Commands;

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
        {--folder= : a folder to put everything in inside the zip (default: none, the files unpack where the zip is extracted)}';

    protected $description = 'Build a release zip (application, vendor/ and built assets) that installs from the browser';

    /**
     * Left out of a release: only a developer uses them. composer.json stays,
     * because Laravel reads the application's namespace from it. So do
     * database/factories/ and DatabaseSeeder, dead weight without Faker, since
     * the optimized autoloader lists them and would include a missing file.
     */
    protected const EXCLUDE = [
        // Notes for developers and their AI assistants.
        'AI-shared/', '.claude/', 'CLAUDE.md', 'PORTING.md', 'README.md',
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

        $files = $this->applicationFiles();

        if ($files === null) {
            $this->components->error('Could not list the application\'s files with git. Build the release from a git checkout.');

            return self::FAILURE;
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

        foreach ($files as $relative) {
            $zip->addFile(base_path($relative), $prefix.$relative);
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
                $count++;
            }
        }

        $this->components->task("Writing {$count} files", fn () => $zip->close());

        // `sha256sum -c epesi-2.0.zip.sha256` checks a download against it.
        File::put($file.'.sha256', hash_file('sha256', $file).'  '.basename($file).PHP_EOL);

        $this->components->info('Release written to '.$file.' ('.round(filesize($file) / 1048576, 1).' MB), with its checksum in '.basename($file).'.sha256.');

        if (str_contains($name, '-dev.')) {
            $this->components->warn('This commit isn\'t tagged v'.Version::short().', so this is a development build. To build the release itself, tag the commit (git tag v'.Version::short().') and run this again.');
        }

        return self::SUCCESS;
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
