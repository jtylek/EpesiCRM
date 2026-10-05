<?php

namespace Tests\Feature;

use App\Models\LoginAudit;
use App\Routing\Router;
use App\Support\Optimize\CacheStore;
use App\Support\Optimize\FrameworkCaches;
use App\Support\Optimize\PhpSettings;
use App\Support\Optimize\RelativeExpiryMemcachedStore;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\CompiledRouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * The caches that speed up every page (AI-shared/Epesi-optimization.md):
 * the cached-route fix for an installation in a folder, how cron builds,
 * keeps and forgets the caches, the "auto" cache store, and the login
 * audit's once-a-minute write.
 *
 * Nothing here builds a real cache: a cached config in this checkout's
 * bootstrap/cache would outlive phpunit.xml for every other test run. The
 * cache files are stand-ins in a scratch folder.
 */
class OptimizeTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/optimize-'.uniqid());
        File::ensureDirectoryExists($this->root.'/bootstrap/cache/filament/panels');
        File::put($this->root.'/.env', "APP_NAME=epesi\n");
        touch($this->root.'/.env', time() - 3600);

        CacheStore::$probePath = $this->root.'/epesi-memcached.json';
    }

    protected function tearDown(): void
    {
        CacheStore::$probePath = null;
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_cached_routes_still_find_the_home_page_of_an_installation_in_a_folder(): void
    {
        $router = app('router');
        $this->assertInstanceOf(Router::class, $router);
        $this->assertSame($router, app(\Illuminate\Routing\Router::class), 'one router, whichever name asks');

        $compiled = $router->getRoutes()->compile();
        $home = fn (): Request => Request::create('http://localhost/epesi/', 'GET', server: [
            'SCRIPT_NAME' => '/epesi/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
            'PHP_SELF' => '/epesi/index.php',
        ]);

        // Laravel's own collection loses the base URL with the slash.
        $laravels = (new CompiledRouteCollection($compiled['compiled'], $compiled['attributes']))
            ->setRouter($router)->setContainer($this->app);

        try {
            $laravels->match($home());
            $this->fail('Laravel\'s cached routes found the home page; the fix may no longer be needed.');
        } catch (HttpException) {
            // 405 Method Not Allowed, or 404.
        }

        $router->setCompiledRoutes($compiled);

        $this->assertSame('filament.main.pages.dashboard', $router->getRoutes()->match($home())->getName());

        $companies = Request::create('http://localhost/epesi/companies', 'GET', server: [
            'SCRIPT_NAME' => '/epesi/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
        ]);
        $this->assertSame('filament.main.resources.companies.index', $router->getRoutes()->match($companies)->getName());
    }

    public function test_cron_builds_the_caches_once_and_keeps_them_while_nothing_changes(): void
    {
        config(['optimize.enabled' => true]);
        $caches = $this->caches();

        $this->assertSame('Built the caches.', $caches->refresh());
        $this->assertSame(1, $caches->builds);
        $this->assertTrue($caches->built());
        $this->assertSame('one', $caches->state()['fingerprint']);

        $this->assertSame('The caches are up to date.', $caches->refresh());
        $this->assertSame(1, $caches->builds);
    }

    public function test_a_changed_file_drops_the_caches_and_the_next_run_builds_them(): void
    {
        config(['optimize.enabled' => true]);
        $caches = $this->caches();
        $caches->refresh();

        $caches->fingerprint = 'two';

        $this->assertStringStartsWith('Files changed', $caches->refresh());
        $this->assertFalse($caches->built());
        $this->assertNull($caches->state());
        $this->assertSame(1, $caches->builds, 'not in the process that started with the old caches');

        $caches->refresh();
        $this->assertSame(2, $caches->builds);
        $this->assertSame('two', $caches->state()['fingerprint']);
    }

    public function test_caches_built_by_hand_are_rebuilt_by_cron_and_left_alone_by_requests(): void
    {
        config(['optimize.enabled' => true]);
        $caches = $this->caches();
        File::put($this->root.'/bootstrap/cache/config.php', '<?php return [];');
        File::put($this->root.'/bootstrap/cache/routes-v7.php', '<?php');

        $caches->dropIfStale();
        $this->assertTrue($caches->built(), 'no state: someone ran `php artisan optimize`');

        $caches->refresh();
        $this->assertFalse($caches->built());
        $caches->refresh();
        $this->assertSame(1, $caches->builds);
    }

    public function test_switched_off_it_removes_the_caches_it_built(): void
    {
        config(['optimize.enabled' => true]);
        $caches = $this->caches();
        $caches->refresh();

        config(['optimize.enabled' => false]);

        $this->assertSame('Caching is switched off: removed the caches.', $caches->refresh());
        $this->assertFalse($caches->built());
        $this->assertSame('Caching is switched off.', $caches->refresh());
        $this->assertSame(1, $caches->builds);
    }

    public function test_a_request_drops_caches_older_than_env_or_from_another_release(): void
    {
        config(['optimize.enabled' => true]);
        $caches = $this->caches();
        File::put($this->root.'/bootstrap/release-id', "2.0-a\n");
        $caches->refresh();
        $this->assertSame('2.0-a', $caches->state()['release']);

        $caches->dropIfStale();
        $this->assertTrue($caches->built(), 'nothing changed');

        File::put($this->root.'/bootstrap/release-id', "2.0-b\n");
        $caches->dropIfStale();
        $this->assertFalse($caches->built(), 'a release unpacked over the old one');

        $caches->refresh();
        $this->assertTrue($caches->built());
        touch($this->root.'/.env', time() + 5);
        $caches->dropIfStale();
        $this->assertFalse($caches->built(), '.env edited after the build');
    }

    public function test_cron_has_the_task_and_skips_it_on_a_development_checkout(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('epesi:optimize')->assertSuccessful();

        // Testing environment: switched off, CACHE_STORE=array, nothing built.
        $this->assertFalse(FrameworkCaches::enabled());
        $this->assertFalse(app(FrameworkCaches::class)->wanted());
    }

    public function test_auto_uses_memcached_only_once_cron_has_found_it(): void
    {
        $this->assertSame('file', CacheStore::resolve('file'));
        $this->assertSame('database', CacheStore::resolve(null));
        $this->assertSame('file', CacheStore::resolve('auto'), 'never probed');

        File::put(CacheStore::probePath(), json_encode(['available' => true, 'checked_at' => time()]));
        $this->assertSame(extension_loaded('memcached') ? 'memcached' : 'file', CacheStore::resolve('auto'));

        File::put(CacheStore::probePath(), json_encode(['available' => false, 'checked_at' => time()]));
        $this->assertSame('file', CacheStore::resolve('auto'));
    }

    public function test_cron_asks_a_missing_memcached_again_only_every_ten_minutes(): void
    {
        $this->assertNull(CacheStore::refresh(), 'CACHE_STORE isn\'t auto');

        config(['cache.auto' => true]);
        $checked = time() - 60;
        File::put(CacheStore::probePath(), json_encode(['available' => false, 'checked_at' => $checked]));

        $this->assertFalse(CacheStore::refresh());
        $this->assertSame($checked, CacheStore::readProbe()['checked_at'], 'not asked again yet');
    }

    public function test_memcached_lifetimes_go_out_relative_so_the_servers_clock_does_not_matter(): void
    {
        $store = (new \ReflectionClass(RelativeExpiryMemcachedStore::class))->newInstanceWithoutConstructor();
        $expiration = (new \ReflectionMethod($store, 'calculateExpiration'))->getClosure($store);

        $this->assertSame(60, $expiration(60));
        $this->assertSame(60 * 60 * 24 * 30, $expiration(60 * 60 * 24 * 30));
        $this->assertSame(0, $expiration(0), 'forever');
        $this->assertGreaterThan(time(), $expiration(60 * 60 * 24 * 31), 'beyond 30 days memcached needs a Unix time');
    }

    public function test_php_production_ini_holds_exactly_the_recommended_settings(): void
    {
        $file = parse_ini_file(config_path('php-production.ini'), false, INI_SCANNER_RAW);
        $expected = array_map(fn (array $recommendation): string => $recommendation[0], PhpSettings::RECOMMENDED);
        ksort($file);
        ksort($expected);

        $this->assertSame($expected, $file);

        foreach (array_keys(PhpSettings::RECOMMENDED) as $setting) {
            $this->assertNotSame('', PhpSettings::reason($setting), "{$setting} says why it matters");
        }
    }

    public function test_php_settings_compare_switches_sizes_and_exact_values(): void
    {
        $this->assertTrue(PhpSettings::meets('On', '1', 'on'));
        $this->assertFalse(PhpSettings::meets('0', '1', 'on'));
        $this->assertTrue(PhpSettings::meets('', 'Off', 'off'));
        $this->assertFalse(PhpSettings::meets('1', 'Off', 'off'));
        $this->assertTrue(PhpSettings::meets('512M', '256M', 'min'));
        $this->assertTrue(PhpSettings::meets('-1', '256M', 'min'), 'unlimited');
        $this->assertFalse(PhpSettings::meets('128M', '256M', 'min'));
        $this->assertTrue(PhpSettings::meets('4M', '4096K', 'min'));
        $this->assertFalse(PhpSettings::meets('10000', '20000', 'min'));
        $this->assertTrue(PhpSettings::meets('-1', '-1', 'same'));
        $this->assertFalse(PhpSettings::meets('1', '-1', 'same'));

        $this->assertCount(count(PhpSettings::RECOMMENDED), PhpSettings::compare());
    }

    public function test_server_check_shows_the_php_settings(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));

        $this->get('/administration/server-check')
            ->assertOk()
            ->assertSee('PHP settings')
            ->assertSee('PHP '.PHP_VERSION)
            ->assertSee('opcache.memory_consumption');
    }

    public function test_the_login_audit_is_written_at_most_once_a_minute(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $this->get('/')->assertOk();
        $audit = LoginAudit::query()->sole();

        $writes = 0;
        DB::listen(function ($query) use (&$writes): void {
            $writes += str_contains($query->sql, 'login_audits') ? 1 : 0;
        });

        $this->travel(30)->seconds();
        $this->get('/')->assertOk();
        $this->assertSame(0, $writes, 'within the minute: not even a read');

        $this->travel(31)->seconds();
        $this->get('/')->assertOk();
        $this->assertSame(2, $writes, 'the row still exists, and its ended_at moves');
        $this->assertTrue($audit->fresh()->ended_at->greaterThan($audit->ended_at));
        $this->assertSame(1, LoginAudit::query()->count());
    }

    protected function caches(): FakeFrameworkCaches
    {
        return new FakeFrameworkCaches($this->app, $this->root);
    }
}

/** FrameworkCaches in a scratch folder, with stand-in files instead of a real build. */
class FakeFrameworkCaches extends FrameworkCaches
{
    public int $builds = 0;

    public string $fingerprint = 'one';

    public function __construct(Application $app, protected string $root)
    {
        parent::__construct($app);
    }

    public function fingerprint(): string
    {
        return $this->fingerprint;
    }

    protected function build(): void
    {
        $this->builds++;

        foreach ([$this->configPath(), $this->routesPath(), $this->eventsPath(), $this->path('bootstrap/cache/blade-icons.php'), $this->path('bootstrap/cache/filament/panels/main.php')] as $file) {
            File::put($file, '<?php return [];');
        }
    }

    protected function path(string $relative): string
    {
        return $this->root.'/'.$relative;
    }

    protected function configPath(): string
    {
        return $this->path('bootstrap/cache/config.php');
    }

    protected function routesPath(): string
    {
        return $this->path('bootstrap/cache/routes-v7.php');
    }

    protected function eventsPath(): string
    {
        return $this->path('bootstrap/cache/events.php');
    }

    protected function envPath(): string
    {
        return $this->path('.env');
    }
}
