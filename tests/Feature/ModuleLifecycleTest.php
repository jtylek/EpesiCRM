<?php

namespace Tests\Feature;

use App\Filament\Administration\Resources\Modules\Pages\DiscoverModules;
use App\Models\Module;
use App\Models\StoredFile;
use App\Models\StoredFileContent;
use App\Models\User;
use App\Services\FileStorage;
use App\Services\Modules\ModuleDiscovery;
use App\Services\Modules\ModuleFootprint;
use App\Services\Modules\ModuleInstaller;
use App\Support\Modules\ModuleRegistry;
use App\Support\Optimize\FrameworkCaches;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\CRM\Companies\Models\Company;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Discovering, registering and uninstalling a module. Uninstalling with data
 * puts the database back as it was before the module was installed. The module is a fixture written to a scratch modules/
 * directory: its migration's down() does nothing, so everything removed here
 * was found through the footprint and the morph aliases.
 */
class ModuleLifecycleTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected string $modules;

    protected string $originalModules;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalModules = config('modules.path');
        $this->modules = storage_path('framework/testing/modules-'.uniqid());
        config(['modules.path' => $this->modules]);

        // The real checkout's framework caches are not this test's to delete.
        $this->mock(FrameworkCaches::class, fn ($mock) => $mock->shouldReceive('forget'));

        $this->writeFixtureModule();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modules);
        config(['modules.path' => $this->originalModules]);
        ModuleRegistry::refresh();

        parent::tearDown();
    }

    public function test_installing_records_what_the_migrations_created(): void
    {
        app(ModuleInstaller::class)->registerExisting('Acme/Widgets');

        $this->assertSame(['acme_widgets'], ModuleFootprint::tablesOf('acme/widgets'));
        $this->assertCount(3, ModuleFootprint::rowsOf('acme/widgets')['common_data']);
    }

    public function test_uninstalling_with_data_leaves_nothing_behind(): void
    {
        $installer = app(ModuleInstaller::class);
        $module = $installer->registerExisting('Acme/Widgets');
        $this->assertTrue(Schema::hasTable('acme_widgets'));

        // An option an administrator added to the module's list later.
        $list = DB::table('common_data')->where('path', 'Acme_Kinds')->value('id');
        DB::table('common_data')->insert(['parent_id' => $list, 'key' => 'extra', 'value' => 'Extra', 'path' => 'Acme_Kinds/extra', 'readonly' => false, 'position' => 9]);

        $storage = app(FileStorage::class);
        $own = $storage->put('same bytes', 'own.txt');
        $twin = $storage->put('same bytes', 'twin.txt');
        $shared = $storage->put('shared', 'shared.txt');
        $this->assertSame($own->content_id, $twin->content_id);

        $widget = DB::table('acme_widgets')->insertGetId(['name' => 'W', 'files' => json_encode([(string) $own->id, (string) $shared->id])]);
        $company = Company::create(['company_name' => 'Keeps its note']);

        $keptNote = Attachment::create(['title' => 'Also on a company', 'files' => [(string) $twin->id, (string) $shared->id]]);
        $orphanNote = Attachment::create(['title' => 'Only on the widget']);
        DB::table('epesi_attachment_links')->insert([
            ['attachment_id' => $keptNote->id, 'attachable_type' => 'acme_widget', 'attachable_id' => $widget],
            ['attachment_id' => $keptNote->id, 'attachable_type' => $company->getMorphClass(), 'attachable_id' => $company->id],
            ['attachment_id' => $orphanNote->id, 'attachable_type' => 'acme_widget', 'attachable_id' => $widget],
        ]);

        DB::table('activity_log')->insert(['log_name' => 'acme', 'description' => 'created', 'subject_type' => 'acme_widget', 'subject_id' => $widget]);
        DB::table('epesi_recordbrowser_links')->insert(['source_type' => $company->getMorphClass(), 'source_id' => $company->id, 'field' => 'related', 'target_type' => 'acme_widget', 'target_id' => $widget]);
        DB::table('permissions')->insert(['name' => 'View:Widget', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $tab = DB::table('dashboard_tabs')->insertGetId(['user_id' => $user->id, 'name' => 'Main', 'pos' => 1]);
        DB::table('dashboard_applets')->insert(['user_id' => $user->id, 'dashboard_tab_id' => $tab, 'widget' => 'Acme\\Widgets\\WidgetsApplet', 'col' => 1, 'pos' => 1]);

        $report = $installer->uninstall($module);

        $this->assertSame([], $report->warnings);
        $this->assertTrue($report->dataDeleted);
        $this->assertFalse(Schema::hasTable('acme_widgets'));
        $this->assertSame(0, DB::table('common_data')->where('path', 'like', 'Acme_Kinds%')->count());
        $this->assertSame(0, DB::table('migrations')->where('migration', 'like', '%acme_widgets%')->count());
        $this->assertSame(0, DB::table('activity_log')->where('subject_type', 'acme_widget')->count());
        $this->assertSame(0, DB::table('epesi_recordbrowser_links')->where('target_type', 'acme_widget')->count());
        $this->assertSame(0, DB::table('permissions')->where('name', 'View:Widget')->count());
        $this->assertSame(0, DB::table('dashboard_applets')->where('widget', 'like', 'Acme%')->count());
        $this->assertSame([], ModuleFootprint::tablesOf('acme/widgets'));

        // The note only the widget had goes; the company's note stays, without its link to the widget.
        $this->assertNull(Attachment::withTrashed()->find($orphanNote->id));
        $this->assertNotNull(Attachment::find($keptNote->id));
        $this->assertSame(1, DB::table('epesi_attachment_links')->where('attachment_id', $keptNote->id)->count());

        // The widget's own file goes, but its content stays for the note's twin
        // (deduplicated storage); the file the note also holds stays.
        $this->assertNull(StoredFile::find($own->id));
        $this->assertNotNull(StoredFile::find($twin->id));
        $this->assertNotNull(StoredFile::find($shared->id));
        $this->assertNotNull(StoredFileContent::find($own->content_id));

        $this->assertNull(Module::query()->where('module_id', 'acme/widgets')->first());
        $this->assertDirectoryDoesNotExist($this->modules.'/Acme/Widgets');
    }

    public function test_keeping_the_data_leaves_the_tables_for_a_reinstall(): void
    {
        $installer = app(ModuleInstaller::class);

        $report = $installer->uninstall($installer->registerExisting('Acme/Widgets'), deleteData: false);

        $this->assertFalse($report->dataDeleted);
        $this->assertTrue(Schema::hasTable('acme_widgets'));
        $this->assertSame(1, DB::table('common_data')->where('path', 'Acme_Kinds')->count());
    }

    public function test_a_git_checkout_keeps_its_files(): void
    {
        File::ensureDirectoryExists($this->modules.'/Acme/Widgets/.git');
        $installer = app(ModuleInstaller::class);

        $report = $installer->uninstall($installer->registerExisting('Acme/Widgets'));

        $this->assertTrue($report->filesKept);
        $this->assertFileExists($this->modules.'/Acme/Widgets/module.json');
        $this->assertFalse(Schema::hasTable('acme_widgets'));
    }

    public function test_discovery_lists_what_is_on_disk_and_registers_it(): void
    {
        File::ensureDirectoryExists($this->modules.'/Broken/Thing');
        File::put($this->modules.'/Broken/Thing/module.json', '{not json');

        $found = collect(app(ModuleDiscovery::class)->scan())->keyBy('path');
        $this->assertSame(ModuleDiscovery::AVAILABLE, $found['Acme/Widgets']['status']);
        $this->assertSame(ModuleDiscovery::INVALID, $found['Broken/Thing']['status']);

        Filament::setCurrentPanel('administration');
        $this->actingAs($this->userWithRole('super_admin'));

        Livewire::test(DiscoverModules::class)
            ->assertSuccessful()
            ->assertSee('acme/widgets')
            ->assertTableActionVisible('register', 'Acme/Widgets')
            ->assertTableActionHidden('register', 'Broken/Thing')
            ->callTableAction('register', 'Acme/Widgets');

        $this->assertTrue(Module::query()->where('module_id', 'acme/widgets')->exists());
        $this->assertTrue(Schema::hasTable('acme_widgets'));
        $this->assertSame(ModuleDiscovery::REGISTERED, collect(app(ModuleDiscovery::class)->scan())->firstWhere('path', 'Acme/Widgets')['status']);
    }

    public function test_a_module_whose_requirements_are_missing_cannot_be_registered(): void
    {
        $manifest = json_decode(File::get($this->modules.'/Acme/Widgets/module.json'), true);
        File::put($this->modules.'/Acme/Widgets/module.json', json_encode([...$manifest, 'requires' => ['acme/missing']]));

        $found = collect(app(ModuleDiscovery::class)->scan())->firstWhere('path', 'Acme/Widgets');

        $this->assertSame(ModuleDiscovery::BLOCKED, $found['status']);
        $this->assertStringContainsString('acme/missing', $found['problem']);
    }

    protected function writeFixtureModule(): void
    {
        $root = $this->modules.'/Acme/Widgets';

        File::ensureDirectoryExists($root.'/src');
        File::ensureDirectoryExists($root.'/database/migrations');

        File::put($root.'/module.json', json_encode([
            'id' => 'acme/widgets',
            'name' => 'Widgets',
            'version' => '1.0.0',
            'epesi_core' => '^3.0',
            'namespace' => 'Acme\\Widgets\\',
            'path' => 'Acme/Widgets',
            'provider_class' => 'Acme\\Widgets\\WidgetsServiceProvider',
            'panels' => [],
            'requires' => [],
        ]));

        File::put($root.'/src/WidgetsServiceProvider.php', <<<'PHP'
            <?php

            namespace Acme\Widgets;

            use Illuminate\Database\Eloquent\Relations\Relation;
            use Illuminate\Support\ServiceProvider;

            class WidgetsServiceProvider extends ServiceProvider
            {
                public function register(): void
                {
                    Relation::morphMap(['acme_widget' => Widget::class]);
                }
            }
            PHP);

        File::put($root.'/src/Widget.php', <<<'PHP'
            <?php

            namespace Acme\Widgets;

            use Illuminate\Database\Eloquent\Model;

            class Widget extends Model
            {
                protected $table = 'acme_widgets';

                public function fileColumns(): array
                {
                    return ['files'];
                }
            }
            PHP);

        File::put($root.'/database/migrations/2026_01_01_000000_create_acme_widgets_table.php', <<<'PHP'
            <?php

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\DB;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration
            {
                public function up(): void
                {
                    Schema::create('acme_widgets', function (Blueprint $table): void {
                        $table->id();
                        $table->string('name');
                        $table->json('files')->nullable();
                    });

                    $list = DB::table('common_data')->insertGetId(['key' => 'Acme_Kinds', 'path' => 'Acme_Kinds', 'readonly' => true, 'position' => 99]);
                    DB::table('common_data')->insert([
                        ['parent_id' => $list, 'key' => 'a', 'value' => 'A', 'path' => 'Acme_Kinds/a', 'readonly' => true, 'position' => 1],
                        ['parent_id' => $list, 'key' => 'b', 'value' => 'B', 'path' => 'Acme_Kinds/b', 'readonly' => true, 'position' => 2],
                    ]);
                }

                // Deliberately nothing: the footprint has to do the work.
                public function down(): void {}
            };
            PHP);
    }
}
