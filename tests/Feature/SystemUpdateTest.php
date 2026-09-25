<?php

namespace Tests\Feature;

use App\Filament\Administration\Pages\DatabaseUpdate;
use App\Services\Setup\SystemUpdate;
use Filament\Facades\Filament;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * After new release files are unpacked, the database changes they bring are
 * made from Administration → Database update or `php artisan epesi:update`.
 * A new release is stood in for by an extra migrations folder with one
 * migration the database hasn't run.
 */
class SystemUpdateTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected string $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = storage_path('framework/testing/update-'.uniqid());
        File::ensureDirectoryExists($this->folder);
        File::put($this->folder.'/2099_01_01_000000_create_epesi_update_probe_table.php', <<<'PHP'
            <?php

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration
            {
                public function up(): void
                {
                    Schema::create('epesi_update_probe', fn (Blueprint $table) => $table->id());
                }
            };
            PHP);

        $folder = $this->folder;
        $this->app->bind(SystemUpdate::class, fn ($app) => new class($app->make(Migrator::class), $folder) extends SystemUpdate
        {
            public function __construct(Migrator $migrator, protected string $extra)
            {
                parent::__construct($migrator);
            }

            public function paths(): array
            {
                return parent::paths() + ['Epesi/Probe' => $this->extra];
            }
        });
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);

        parent::tearDown();
    }

    public function test_it_lists_what_is_waiting_by_where_it_comes_from(): void
    {
        $pending = app(SystemUpdate::class)->pending();

        $this->assertSame(['Epesi/Probe' => ['2099_01_01_000000_create_epesi_update_probe_table']], $pending);
        $this->assertSame(1, SystemUpdate::waiting());
    }

    public function test_it_covers_the_core_app_and_every_enabled_module(): void
    {
        $paths = app(SystemUpdate::class)->paths();

        $this->assertSame(database_path('migrations'), $paths['epesi']);
        $this->assertArrayHasKey('Epesi/Mail', $paths);
        $this->assertArrayHasKey('Epesi/Attachments', $paths);
    }

    public function test_an_administrator_runs_it_from_the_browser(): void
    {
        $admin = $this->userWithRole('super_admin');
        $this->actingAs($admin);

        // The CRM's pages would fail on the missing tables: the
        // administrator is sent to the update, and the Administration pages
        // say it is waiting.
        // (Administration first: within one test the panels share a process,
        // and a panel registers its render hooks when it boots.)
        $this->get('/administration/modules')->assertOk()->assertSee('1 database change is waiting', false);
        $this->get('/')->assertRedirect(DatabaseUpdate::getUrl(panel: 'administration'));

        $this->get(DatabaseUpdate::getUrl(panel: 'administration'))
            ->assertOk()
            ->assertSee('2099_01_01_000000_create_epesi_update_probe_table');

        Filament::setCurrentPanel('administration');
        Livewire::test(DatabaseUpdate::class)
            ->callAction('update')
            ->assertNotified('The database is up to date');

        $this->assertTrue(Schema::hasTable('epesi_update_probe'));
        $this->assertSame([], app(SystemUpdate::class)->pending());

        SystemUpdate::flush();
        $this->get('/')->assertOk();
        $this->get('/administration/modules')->assertOk()->assertDontSee('database change is waiting', false);
    }

    public function test_everyone_else_is_told_to_come_back_later(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $this->get('/')->assertStatus(503)->assertSee('epesi is being updated');
        $this->get(DatabaseUpdate::getUrl(panel: 'administration'))->assertForbidden();

        // Signed out, the login page still works.
        auth()->logout();
        $this->get(route('filament.main.auth.login'))->assertOk();
    }

    public function test_the_command_does_the_same(): void
    {
        $this->artisan('epesi:update', ['--pretend' => true])
            ->expectsOutputToContain('2099_01_01_000000_create_epesi_update_probe_table')
            ->assertSuccessful();
        $this->assertFalse(Schema::hasTable('epesi_update_probe'), '--pretend changes nothing');

        $this->artisan('epesi:update')->assertSuccessful();
        $this->assertTrue(Schema::hasTable('epesi_update_probe'));

        $this->artisan('epesi:update')->expectsOutputToContain('The database is up to date')->assertSuccessful();
    }
}
