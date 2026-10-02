<?php

namespace App\Support\Optimize;

use BladeUI\Icons\Factory as IconFactory;
use BladeUI\Icons\IconsManifest;
use Filament\Facades\Filament;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

/**
 * Laravel's config, event and route caches, Filament's component cache and
 * Blade Icons' manifest: together 100-150 ms off every page (measured in
 * AI-shared/Epesi-optimization.md). Not view:cache, which gained nothing:
 * Blade compiles a view on first use anyway.
 *
 * Who does what:
 *
 * - Cron builds them (`epesi:optimize` every minute, refresh()), and only in
 *   a process that started with none of them. The process that installs a
 *   module or writes .env can't: its panels and module registry predate the
 *   change, and would be cached as they were.
 * - Whatever changes what they hold only forgets them: ModuleInstaller,
 *   SystemUpdate, MailConfig.
 * - Cron also notices changes nobody announced (a release unpacked over the
 *   old one, a file edited, .env edited by hand, memcached coming or going)
 *   through a fingerprint of the application's files, and forgets the
 *   caches; the run after that builds them again.
 * - Every request runs guard() from bootstrap/app.php: two stat() calls and
 *   two small includes. It forgets the caches before they are read when .env
 *   is newer than the cached config, or when a release zip with a different
 *   build id (bootstrap/release-id, written by `epesi:package`) has been
 *   unpacked, so the minute until cron notices runs on the new files.
 *
 * Switched on by config('optimize.enabled'). Caches someone built by hand
 * (`php artisan optimize`, without the state file) are left alone by guard().
 */
class FrameworkCaches
{
    public function __construct(protected Application $app) {}

    public static function enabled(): bool
    {
        return (bool) config('optimize.enabled');
    }

    /** From bootstrap/app.php, before the configuration loads. */
    public static function guard(Application $app): void
    {
        if (is_file($app->getCachedConfigPath())) {
            (new static($app))->dropIfStale();
        }
    }

    /** Whether cron has anything to do here: caches to build or forget, or a cache store to choose. */
    public function wanted(): bool
    {
        return static::enabled() || CacheStore::isAuto() || $this->state() !== null;
    }

    /** All of them in place. */
    public function built(): bool
    {
        return is_file($this->configPath()) && is_file($this->routesPath());
    }

    /**
     * What cron runs: decides the cache store, then builds, keeps or forgets
     * the caches. Returns what it did, for the Cron page.
     */
    public function refresh(): string
    {
        $memcached = CacheStore::refresh();
        $store = match ($memcached) {
            null => '',
            true => ' '.__('Cache store: memcached.'),
            false => ' '.__('Cache store: files (memcached isn\'t available).'),
        };

        if (! static::enabled()) {
            if ($this->state() !== null || $this->present()) {
                $this->forget();

                return __('Caching is switched off: removed the caches.').$store;
            }

            return __('Caching is switched off.').$store;
        }

        $fingerprint = $this->fingerprint();

        if ($this->built() && ($this->state()['fingerprint'] ?? null) === $fingerprint) {
            return __('The caches are up to date.').$store;
        }

        if ($this->present()) {
            $this->forget();

            return __('Files changed since the caches were built, so they were removed. The next run builds them again.').$store;
        }

        $this->build();
        $this->writeState($fingerprint);

        return __('Built the caches.').$store;
    }

    /** Removes every cache file this class builds, and its state. */
    public function forget(): void
    {
        foreach ([...$this->files(), $this->statePath()] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * Forgets the caches when .env changed after they were built, or a
     * different release was unpacked.
     */
    public function dropIfStale(): void
    {
        $state = $this->state();

        if ($state === null) {
            return;
        }

        $env = $this->envPath();
        $config = $this->configPath();

        if ((is_file($env) && is_file($config) && filemtime($env) > filemtime($config))
            || $this->releaseId() !== ($state['release'] ?? null)) {
            $this->forget();
        }
    }

    /**
     * The files whose change changes what the caches hold, by path, size and
     * modification time, plus the cache store "auto" chose.
     */
    public function fingerprint(): string
    {
        $parts = [];

        foreach (['app', 'config', 'routes', 'modules'] as $directory) {
            $path = $this->path($directory);

            if (! is_dir($path)) {
                continue;
            }

            // Not into a nested repository's .git (a module kept in its own
            // repository) or node_modules: neither changes a cache.
            $files = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                fn (SplFileInfo $file): bool => ! ($file->isDir() && in_array($file->getFilename(), ['.git', 'node_modules'], true)),
            ));

            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                $parts[] = $file->getPathname().'|'.$file->getSize().'|'.$file->getMTime();
            }
        }

        foreach (['.env', 'VERSION', 'bootstrap/app.php', 'bootstrap/providers.php', 'bootstrap/cache/epesi-modules.php', 'bootstrap/release-id', 'vendor/composer/installed.json'] as $relative) {
            $path = $this->path($relative);
            $parts[] = is_file($path) ? $relative.'|'.filesize($path).'|'.filemtime($path) : $relative.'|-';
        }

        $parts[] = 'memcached|'.(int) CacheStore::memcachedAvailable();

        sort($parts);

        return hash('xxh128', implode("\n", $parts));
    }

    /** The build id of the unpacked release zip; null in a git checkout. */
    public function releaseId(): ?string
    {
        $path = $this->path('bootstrap/release-id');

        return is_file($path) ? (trim((string) @file_get_contents($path)) ?: null) : null;
    }

    /**
     * @return array{fingerprint: string, release: ?string, built_at: int}|null
     */
    public function state(): ?array
    {
        $path = $this->statePath();
        $state = is_file($path) ? @include $path : null;

        return is_array($state) ? $state : null;
    }

    public function statePath(): string
    {
        return $this->path('bootstrap/cache/epesi-optimize.php');
    }

    /**
     * The cache files, wherever they are: Laravel's from the application
     * (APP_CONFIG_CACHE and friends move them), Filament's and Blade Icons'
     * at their defaults, which config/ doesn't change.
     *
     * @return list<string>
     */
    public function files(): array
    {
        return [
            $this->configPath(),
            $this->routesPath(),
            $this->eventsPath(),
            $this->path('bootstrap/cache/blade-icons.php'),
            ...(glob($this->path('bootstrap/cache/filament/panels').DIRECTORY_SEPARATOR.'*.php') ?: []),
        ];
    }

    /** Under the application's folder (a test points these elsewhere). */
    protected function path(string $relative): string
    {
        return $this->app->basePath($relative);
    }

    protected function configPath(): string
    {
        return $this->app->getCachedConfigPath();
    }

    protected function routesPath(): string
    {
        return $this->app->getCachedRoutesPath();
    }

    protected function eventsPath(): string
    {
        return $this->app->getCachedEventsPath();
    }

    protected function envPath(): string
    {
        return $this->app->environmentFilePath();
    }

    /** Any of them, left by a build or by someone's `php artisan optimize`. */
    protected function present(): bool
    {
        foreach ($this->files() as $file) {
            if (is_file($file)) {
                return true;
            }
        }

        return false;
    }

    protected function build(): void
    {
        try {
            foreach (['config:cache', 'event:cache', 'route:cache'] as $command) {
                if (Artisan::call($command) !== 0) {
                    throw new RuntimeException(trim(Artisan::output()) ?: "{$command} failed.");
                }
            }

            // filament:cache-components and icons:cache, which their packages
            // register on the command line only, and the cron URL runs here
            // in a web request.
            foreach (Filament::getPanels() as $panel) {
                $panel->cacheComponents();
            }

            $this->app->make(IconsManifest::class)->write($this->app->make(IconFactory::class)->all());
        } catch (Throwable $e) {
            // Half a set is worse than none: a cached config with no route
            // cache is consistent, but nothing should rely on that.
            $this->forget();

            throw $e;
        }
    }

    protected function writeState(string $fingerprint): void
    {
        $path = $this->statePath();
        $state = ['fingerprint' => $fingerprint, 'release' => $this->releaseId(), 'built_at' => time()];

        file_put_contents($path, '<?php return '.var_export($state, true).';'.PHP_EOL, LOCK_EX);

        // Read back with include: OPcache would otherwise keep serving the
        // previous state for a while.
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }
}
