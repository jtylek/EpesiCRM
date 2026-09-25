<?php

namespace Tests\Feature;

use App\Filament\Setup\Pages\FinishSetup;
use App\Filament\Setup\Pages\InstallWizard;
use App\Models\Module;
use App\Models\User;
use App\Services\Setup\DatabaseSetup;
use App\Services\Setup\Installer;
use App\Services\Setup\InstallOptions;
use App\Services\Setup\ModulePlan;
use App\Services\Setup\RoundcubeSetup;
use App\Services\Setup\SetupException;
use App\Support\Modules\ModuleManifest;
use App\Support\Setup\FirstBoot;
use App\Support\Setup\SetupCode;
use App\Support\Setup\SetupState;
use Dotenv\Dotenv;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Epesi\Modules\Roundcube\Services\RoundcubeInstaller;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

/**
 * The setup wizard (Epesi's FirstRun) on an empty database. The marker file
 * and .env are redirected to a scratch directory so a test run never touches
 * the checkout's own.
 */
class SetupTest extends TestCase
{
    use RefreshDatabase;

    protected string $scratch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scratch = storage_path('framework/testing/setup-'.uniqid());
        File::ensureDirectoryExists($this->scratch);
        File::put($this->scratch.'/.env', "APP_NAME=epesi\nMAIL_MAILER=log\n# MAIL_FROM_ADDRESS=\n");
        $this->app->useEnvironmentPath($this->scratch);
        config(['setup.marker_path' => $this->scratch.'/installed.json', 'setup.code_path' => $this->scratch.'/setup-code.txt', 'setup.token' => null]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->scratch);

        parent::tearDown();
    }

    protected function wizardData(array $overrides = []): array
    {
        return [
            'code' => SetupCode::current(),
            'profile' => 'crm',
            'demo_data' => false,
            'admin_name' => 'Jan Kowalski',
            'admin_email' => 'jan@example.test',
            'admin_password' => 'correct horse battery',
            'admin_password_confirmation' => 'correct horse battery',
            'mail_method' => InstallOptions::MAIL_SMTP,
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => '465',
            'smtp_security' => 'ssl',
            'smtp_username' => 'jan',
            'smtp_password' => 'p@ss $ecret',
            'roundcube' => 'no',
            ...$overrides,
        ];
    }

    public function test_a_fresh_install_sends_everyone_to_the_wizard(): void
    {
        $this->get('/')->assertRedirect(route('filament.setup.install'));
        $this->get('/administration')->assertRedirect(route('filament.setup.install'));
        $this->get('/setup')->assertRedirect(route('filament.setup.install'));

        $this->get(route('filament.setup.install'))
            ->assertOk()
            ->assertSee('Welcome to epesi')
            ->assertSee('CRM installation');
    }

    public function test_the_wizard_installs_modules_creates_the_administrator_and_saves_mail_settings(): void
    {
        Filament::setCurrentPanel('setup');

        Livewire::test(InstallWizard::class)
            ->fillForm($this->wizardData())
            ->call('install')
            ->assertHasNoFormErrors()
            ->assertRedirect(route('filament.setup.finish'));

        $admin = User::query()->where('email', 'jan@example.test')->sole();
        $this->assertTrue($admin->hasRole('super_admin'));
        $this->assertAuthenticatedAs($admin);

        // Core modules always, then the CRM profile's own.
        $installed = Module::query()->pluck('module_id')->all();
        foreach (['epesi/recordbrowser', 'epesi/commondata', 'epesi/crm-contacts', 'epesi/mail', 'epesi/reminders', 'epesi/regional-settings'] as $id) {
            $this->assertContains($id, $installed);
        }
        $this->assertNotContains('epesi/store-server', $installed);

        $env = File::get($this->scratch.'/.env');
        $this->assertStringContainsString('MAIL_MAILER=smtp', $env);
        $this->assertStringContainsString('MAIL_SCHEME=smtps', $env);
        $this->assertStringContainsString('MAIL_PORT=465', $env);
        $this->assertStringContainsString('MAIL_PASSWORD="p@ss \\$ecret"', $env);
        $this->assertStringContainsString('MAIL_FROM_ADDRESS=jan@example.test', $env);
        $this->assertStringContainsString('APP_NAME=epesi', $env, 'other keys are kept');
        $this->assertStringContainsString('APP_ENV=production', $env);
        $this->assertStringContainsString('APP_DEBUG=false', $env);

        SetupState::flush();
        $this->assertTrue(SetupState::isInstalled());
        $this->assertTrue(SetupState::finishPending());
    }

    public function test_the_wizard_validates_its_pages(): void
    {
        Filament::setCurrentPanel('setup');

        Livewire::test(InstallWizard::class)
            ->fillForm($this->wizardData([
                'admin_email' => 'not-an-address',
                'admin_password_confirmation' => 'something else',
                'smtp_host' => null,
            ]))
            ->call('install')
            ->assertHasFormErrors(['admin_email', 'admin_password', 'smtp_host']);

        $this->assertSame(0, User::count());
    }

    public function test_the_one_time_setup_code_is_required(): void
    {
        Filament::setCurrentPanel('setup');

        Livewire::test(InstallWizard::class)
            ->fillForm($this->wizardData(['code' => 'guess']))
            ->call('install')
            ->assertHasFormErrors(['code']);

        $this->assertSame(0, User::count());

        // Read off the file: case, spaces and dashes don't matter.
        $code = File::get($this->scratch.'/setup-code.txt');
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/', trim($code));

        Livewire::test(InstallWizard::class)
            ->fillForm($this->wizardData(['code' => ' '.strtolower(str_replace('-', ' ', trim($code)))]))
            ->call('install')
            ->assertHasNoFormErrors();

        $this->assertSame(1, User::count());
        $this->assertFileDoesNotExist($this->scratch.'/setup-code.txt', 'used up once installed');
    }

    public function test_the_code_is_asked_once_per_session(): void
    {
        Filament::setCurrentPanel('setup');
        SetupCode::markVerified();

        $data = $this->wizardData();
        unset($data['code']);

        Livewire::test(InstallWizard::class)
            ->assertDontSee(__('Setup code'))
            ->fillForm($data)
            ->call('install')
            ->assertHasNoFormErrors();

        $this->assertSame(1, User::count());
    }

    public function test_a_configured_setup_token_replaces_the_code(): void
    {
        config(['setup.token' => 'let-me-in']);
        Filament::setCurrentPanel('setup');

        Livewire::test(InstallWizard::class)
            ->fillForm($this->wizardData(['code' => 'LET-ME-IN']))
            ->call('install')
            ->assertHasFormErrors(['code']);

        Livewire::test(InstallWizard::class)
            ->fillForm($this->wizardData(['code' => 'let-me-in']))
            ->call('install')
            ->assertHasNoFormErrors();

        $this->assertSame(1, User::count());
        $this->assertFileDoesNotExist($this->scratch.'/setup-code.txt');
    }

    public function test_without_tables_the_wizard_asks_for_the_server_and_the_database(): void
    {
        $this->useEmptyDatabase();

        $this->get(route('filament.setup.install'))
            ->assertOk()
            ->assertSee('Setup code')
            ->assertSee('Server check')
            ->assertSee('PHP extension intl')
            ->assertSee('Create the tables')
            ->assertDontSee('CRM installation');

        $this->assertFileExists($this->scratch.'/setup-code.txt', 'written before anyone is told to look for it');

        config(['database.default' => 'sqlite']);
    }

    public function test_the_database_page_refuses_a_wrong_code(): void
    {
        $this->useEmptyDatabase();
        Filament::setCurrentPanel('setup');

        Livewire::test(InstallWizard::class)
            ->fillForm(['code' => 'guess', 'connection' => 'sqlite', 'file' => $this->scratch.'/other.sqlite'], 'databaseForm')
            ->call('connectDatabase')
            ->assertHasFormErrors(['code'], 'databaseForm');

        $this->assertFileDoesNotExist($this->scratch.'/other.sqlite');
        $this->assertStringNotContainsString('DB_CONNECTION', File::get($this->scratch.'/.env'));

        config(['database.default' => 'sqlite']);
    }

    public function test_the_database_is_connected_saved_and_given_its_tables(): void
    {
        $database = $this->scratch.'/epesi.sqlite';
        config(['database.connections.install_test' => ['driver' => 'sqlite', 'database' => '', 'prefix' => '', 'foreign_key_constraints' => true]]);

        $setup = app(DatabaseSetup::class);
        $settings = $setup->settings('install_test', ['database' => $database, 'host' => 'ignored']);
        $this->assertSame(['DB_CONNECTION' => 'install_test', 'DB_DATABASE' => $database], $settings);

        $setup->connect($settings);
        $setup->save($settings);
        $setup->migrate();

        $this->assertTrue(DB::connection('install_test')->getSchemaBuilder()->hasTable('users'));
        $this->assertSame($database, Dotenv::parse(File::get($this->scratch.'/.env'))['DB_DATABASE']);

        $this->assertSame(
            ['DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_DATABASE' => 'epesi', 'DB_USERNAME' => 'root', 'DB_PASSWORD' => ''],
            $setup->settings('mysql', ['host' => '127.0.0.1', 'port' => '3306', 'database' => 'epesi', 'username' => 'root']),
        );

        // See test_the_install_command_can_set_up_without_the_browser.
        config(['database.default' => 'sqlite']);
    }

    public function test_a_fresh_copy_gets_its_env_file_on_the_first_request(): void
    {
        $base = $this->scratch.'/app';
        File::ensureDirectoryExists($base.'/storage');
        File::ensureDirectoryExists($base.'/bootstrap/cache');
        File::put($base.'/.env.example', "APP_NAME=epesi\nAPP_KEY=\n\nSESSION_DRIVER=database\n# CACHE_STORE=database\n");

        FirstBoot::prepare($base);

        $env = Dotenv::parse(File::get($base.'/.env'));
        $this->assertSame('epesi', $env['APP_NAME']);
        $this->assertMatchesRegularExpression('/^base64:.{44}$/', $env['APP_KEY']);
        $this->assertSame(32, strlen(base64_decode(substr($env['APP_KEY'], 7))));
        $this->assertSame('file', $env['SESSION_DRIVER'], 'no database yet');
        $this->assertSame('file', $env['CACHE_STORE']);
        $this->assertSame('production', $env['APP_ENV'], 'an installation, not .env.example\'s development settings');
        $this->assertSame('false', $env['APP_DEBUG']);

        // A configured copy is left alone.
        FirstBoot::prepare($base);
        $this->assertSame($env['APP_KEY'], Dotenv::parse(File::get($base.'/.env'))['APP_KEY']);

        // A hand-copied .env without a key gets one.
        File::put($base.'/.env', "APP_NAME=mine\nAPP_KEY=\nSESSION_DRIVER=database\n");
        FirstBoot::prepare($base);
        $env = Dotenv::parse(File::get($base.'/.env'));
        $this->assertSame('mine', $env['APP_NAME']);
        $this->assertNotEmpty($env['APP_KEY']);
        $this->assertArrayNotHasKey('APP_DEBUG', $env, 'a developer\'s own .env keeps its settings');

        $this->assertSame([], (new FirstBoot($base))->problems($base.'/.env'));
        File::delete($base.'/.env');
        File::delete($base.'/.env.example');
        $this->assertStringContainsString('.env.example is missing', (new FirstBoot($base))->problems($base.'/.env')[0]);
    }

    /**
     * Points the default connection at an empty SQLite file, so the wizard
     * sees a database without tables. Callers put "sqlite" back at the end
     * (see test_the_install_command_can_set_up_without_the_browser).
     */
    protected function useEmptyDatabase(): void
    {
        $file = $this->scratch.'/empty.sqlite';
        touch($file);
        config([
            'database.connections.empty_test' => ['driver' => 'sqlite', 'database' => $file, 'prefix' => '', 'foreign_key_constraints' => true],
            'database.default' => 'empty_test',
        ]);
    }

    public function test_roundcube_is_asked_for_never_assumed(): void
    {
        Filament::setCurrentPanel('setup');
        $this->mock(RoundcubeInstaller::class, fn (MockInterface $mock) => $mock->shouldNotReceive('install'));

        Livewire::test(InstallWizard::class)
            ->assertSee('GNU General Public License, version 3')
            ->fillForm($this->wizardData(['roundcube' => null]))
            ->call('install')
            ->assertHasFormErrors(['roundcube']);

        Livewire::test(InstallWizard::class)
            ->fillForm($this->wizardData(['roundcube' => 'no']))
            ->call('install')
            ->assertHasNoFormErrors();

        $this->assertSame(1, User::count());
        $this->assertFalse(Module::query()->where('module_id', RoundcubeSetup::MODULE_ID)->exists());
    }

    public function test_saying_yes_installs_the_module_and_downloads_roundcube(): void
    {
        Filament::setCurrentPanel('setup');
        $this->mock(RoundcubeInstaller::class, fn (MockInterface $mock) => $mock->shouldReceive('install')
            ->once()
            ->withArgs(fn (string $version, string $sha256) => $version === config('epesi-roundcube.release.version') && $sha256 === config('epesi-roundcube.release.sha256')));

        Livewire::test(InstallWizard::class)
            ->fillForm($this->wizardData(['roundcube' => 'yes']))
            ->assertSee('Then it downloads Roundcube')
            ->call('install')
            ->assertHasNoFormErrors()
            ->assertRedirect(route('filament.setup.finish'));

        $this->assertTrue(Module::query()->where('module_id', RoundcubeSetup::MODULE_ID)->where('enabled', true)->exists());
    }

    public function test_a_failed_roundcube_download_still_finishes_setup(): void
    {
        Filament::setCurrentPanel('setup');
        $this->mock(RoundcubeInstaller::class, fn (MockInterface $mock) => $mock->shouldReceive('install')
            ->once()
            ->andThrow(new RuntimeException('no internet')));

        Livewire::test(InstallWizard::class)
            ->fillForm($this->wizardData(['roundcube' => 'yes']))
            ->call('install')
            ->assertHasNoFormErrors()
            ->assertNotified(__('One thing to finish by hand'))
            ->assertRedirect(route('filament.setup.finish'));

        $this->assertSame(1, User::count());
        $this->assertTrue(Module::query()->where('module_id', RoundcubeSetup::MODULE_ID)->exists(), 'so the Mailbox page can offer the download again');
    }

    public function test_a_development_install_keeps_its_debug_settings(): void
    {
        File::put($this->scratch.'/.env', "APP_ENV=local\nAPP_DEBUG=true\n");

        app(Installer::class)->install(new InstallOptions('core', 'Jan', 'jan@example.test', 'correct horse battery', InstallOptions::MAIL_LOG, development: true));

        $env = Dotenv::parse(File::get($this->scratch.'/.env'));
        $this->assertSame('local', $env['APP_ENV']);
        $this->assertSame('true', $env['APP_DEBUG']);
    }

    public function test_setup_cannot_run_twice(): void
    {
        $installer = app(Installer::class);
        $options = new InstallOptions('core', 'Jan', 'jan@example.test', 'correct horse battery', InstallOptions::MAIL_LOG);
        $installer->install($options);

        $this->expectException(SetupException::class);
        $installer->install(new InstallOptions('core', 'Eve', 'eve@example.test', 'correct horse battery'));
    }

    public function test_the_wizard_closes_once_installed(): void
    {
        User::factory()->create();

        $this->get(route('filament.setup.install'))->assertRedirect(filament()->getPanel('main')->getUrl());
    }

    public function test_demo_data_is_optional(): void
    {
        app(Installer::class)->install(new InstallOptions('core', 'Jan', 'jan@example.test', 'correct horse battery', InstallOptions::MAIL_LOG, demoData: true));

        $this->assertTrue(Company::query()->withoutGlobalScopes()->where('company_name', 'Acme Corp')->exists());
        $this->assertTrue(User::query()->where('email', 'employee@example.com')->exists());
    }

    public function test_the_administrator_finishes_with_the_module_pages(): void
    {
        $admin = app(Installer::class)->install(new InstallOptions('crm', 'Jan Kowalski', 'jan@example.test', 'correct horse battery', InstallOptions::MAIL_LOG));
        $this->actingAs($admin);

        $this->get('/')->assertRedirect(route('filament.setup.finish'));
        $this->get(route('filament.setup.finish'))
            ->assertOk()
            ->assertSee('Your company')
            ->assertSee('Regional settings');

        Filament::setCurrentPanel('setup');

        Livewire::test(FinishSetup::class)
            ->assertFormSet(['contacts-your-company.first_name' => 'Jan', 'contacts-your-company.last_name' => 'Kowalski'])
            ->fillForm([
                'contacts-your-company.company_name' => 'Kowalski Sp. z o.o.',
                'contacts-your-company.city' => 'Kraków',
                'contacts-your-company.country' => 'PL',
                'regional-settings-defaults.timezone' => 'Europe/Warsaw',
                'regional-settings-defaults.date_format' => 'd/m/Y',
                'regional-settings-defaults.time_format' => 'H:i',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(filament()->getPanel('main')->getUrl());

        $contact = $admin->fresh()->contact;
        $this->assertSame('Jan', $contact->first_name);
        $this->assertSame('Kowalski Sp. z o.o.', $contact->company->company_name);
        $this->assertSame('jan@example.test', $contact->email);

        $this->assertSame('Europe/Warsaw', RegionalSetting::defaults()->timezone);
        $this->assertSame('d/m/Y', RegionalSetting::query()->where('user_id', $admin->id)->value('date_format'));

        // A user added later starts from the defaults.
        $this->actingAs(User::factory()->create());
        $this->assertSame('Europe/Warsaw', RegionalSetting::current()->timezone);

        SetupState::flush();
        $this->assertFalse(SetupState::finishPending());
        $this->actingAs($admin)->get('/')->assertOk();
    }

    public function test_the_module_pages_can_be_skipped(): void
    {
        $admin = app(Installer::class)->install(new InstallOptions('crm', 'Jan', 'jan@example.test', 'correct horse battery', InstallOptions::MAIL_LOG));
        $this->actingAs($admin);
        Filament::setCurrentPanel('setup');

        Livewire::test(FinishSetup::class)
            ->callAction('skip')
            ->assertRedirect(filament()->getPanel('main')->getUrl());

        $this->assertFalse(SetupState::finishPending());
        $this->assertNull($admin->fresh()->contact);
    }

    public function test_the_module_plan_orders_requirements_first_and_skips_missing_modules(): void
    {
        $plan = collect(app(ModulePlan::class)->for(['Epesi/Mail', 'Epesi/DoesNotExist']));
        $ids = $plan->map(fn (ModuleManifest $m): string => $m->id)->all();

        $this->assertContains('epesi/mail', $ids);
        $this->assertLessThan(array_search('epesi/crm-contacts', $ids), array_search('epesi/commondata', $ids));
        $this->assertLessThan(array_search('epesi/mail', $ids), array_search('epesi/recordbrowser', $ids));
    }

    public function test_the_install_command_can_set_up_without_the_browser(): void
    {
        $database = $this->scratch.'/epesi.sqlite';

        // A connection of its own: reconfiguring "sqlite" would pull the
        // in-memory database out from under the rest of the test run.
        config(['database.connections.install_test' => ['driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'foreign_key_constraints' => true]]);

        $this->artisan('epesi:install', [
            '--db-connection' => 'install_test',
            '--db-database' => $database,
            '--admin-name' => 'Jan',
            '--admin-email' => 'jan@example.test',
            '--admin-password' => 'correct horse battery',
            '--profile' => 'core',
            '--mail' => 'log',
            '--no-interaction' => true,
            '--force' => true,
        ])->assertSuccessful();

        // Compared as dotenv reads it back, not as raw text: a Windows path
        // has backslashes, so EnvFile quotes and escapes it.
        $env = Dotenv::parse(File::get($this->scratch.'/.env'));
        $this->assertSame('install_test', $env['DB_CONNECTION']);
        $this->assertSame($database, $env['DB_DATABASE']);
        $this->assertTrue(
            DB::connection('install_test')->table('users')->where('email', 'jan@example.test')->exists(),
            'created in the database the command configured',
        );

        // The command switched the default connection, and RefreshDatabase
        // rolls back whichever is default at teardown — left like this, the
        // in-memory database would keep an open transaction into the next
        // test class.
        config(['database.default' => 'sqlite']);
    }
}
