<?php

namespace Tests\Feature;

use App\Providers\ModuleServiceProvider;
use App\Support\Setup\SetupState;
use Epesi\Modules\MigrationWizard\MigrationWizardServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

/**
 * An installation that is still a legacy Epesi has a `modules` table the registry cannot read, so
 * the Migration Wizard's provider (command, views, config, importers, approved schemas) must be
 * registered by ModuleServiceProvider itself, and only then.
 */
class ModuleServiceProviderLegacySiteTest extends TestCase
{
    use RefreshDatabase;

    protected string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = storage_path('framework/testing/legacy-site-'.uniqid());
        File::ensureDirectoryExists($this->base.'/data');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);

        parent::tearDown();
    }

    protected function writeLegacyConfig(): void
    {
        File::put($this->base.'/data/config.php', "<?php\ndefine('DATABASE_HOST','localhost');\ndefine('DATABASE_USER','u');\ndefine('DATABASE_NAME','legacy');\n");
    }

    /**
     * @param  array<int, array<string, mixed>>  $modules  what the registry returned
     */
    protected function run_(array $modules, bool $expectRegistered): void
    {
        config(['modules.from_manifests' => false]);

        $app = Mockery::mock(Application::class);
        $app->shouldReceive('basePath')->andReturn($this->base);

        if ($expectRegistered) {
            $app->shouldReceive('register')->once()->with(MigrationWizardServiceProvider::class);
        } else {
            $app->shouldReceive('register')->never();
        }

        $method = new ReflectionMethod(ModuleServiceProvider::class, 'registerMigrationWizardForLegacySite');
        $method->invoke(new ModuleServiceProvider($app), $modules);
    }

    public function test_registers_the_migration_wizard_on_a_legacy_site_that_is_not_installed(): void
    {
        $this->writeLegacyConfig();
        config(['database.default' => 'sqlite']);
        $before = config('database.connections.mysql');

        try {
            $this->run_([], true);

            // The application database becomes the legacy one from data/config.php (config only, lazily).
            $this->assertSame('mysql', config('database.default'));
            $this->assertSame('legacy', config('database.connections.mysql.database'));
            $this->assertSame('u', config('database.connections.mysql.username'));
        } finally {
            // The mocked application leaves the real config to this test: put it back for the transaction rollback.
            config(['database.default' => 'sqlite', 'database.connections.mysql' => $before, 'migration-wizard.legacy_connection' => null]);
        }
    }

    public function test_an_explicitly_configured_default_connection_is_kept(): void
    {
        $this->writeLegacyConfig();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'chosen']);

        try {
            $this->run_([], true);

            $this->assertSame('chosen', config('database.connections.mysql.database'));
            $this->assertNull(config('migration-wizard.legacy_connection'));
        } finally {
            config(['database.default' => 'sqlite']);
        }
    }

    public function test_does_nothing_without_a_legacy_installation(): void
    {
        $this->run_([], false);
    }

    public function test_does_nothing_once_epesi_is_installed(): void
    {
        $this->writeLegacyConfig();
        File::ensureDirectoryExists(dirname(SetupState::markerPath()));
        File::put(SetupState::markerPath(), '{}');
        SetupState::flush();

        try {
            $this->run_([], false);
        } finally {
            File::delete(SetupState::markerPath());
            SetupState::flush();
        }
    }

    public function test_leaves_a_regular_install_to_the_module_registry(): void
    {
        $this->writeLegacyConfig();

        $this->run_([[
            'id' => 'epesi/migration-wizard',
            'path' => 'Epesi/MigrationWizard',
            'namespace' => 'Epesi\\Modules\\MigrationWizard\\',
            'provider' => MigrationWizardServiceProvider::class,
            'plugin' => null,
            'panels' => [],
        ]], false);
    }

    public function test_the_provider_is_registered_exactly_once_in_the_booted_application(): void
    {
        $this->assertInstanceOf(MigrationWizardServiceProvider::class, $this->app->getProvider(MigrationWizardServiceProvider::class));
        $this->assertSame(
            $this->app->getProvider(MigrationWizardServiceProvider::class),
            $this->app->register(MigrationWizardServiceProvider::class),
        );
    }
}
