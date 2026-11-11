<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\CompanyResource;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\ListCompanies;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\ViewRecordset;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Epesi\Modules\RecordBrowser\Recordset\ExportGrants;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetCsvExport;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetFeatures;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class RecordsetExportTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function tearDown(): void
    {
        RecordsetFeatures::flush();

        parent::tearDown();
    }

    public function test_export_is_on_for_super_admin_and_can_be_granted_to_a_role_per_recordset(): void
    {
        $admin = $this->userWithRole('super_admin');
        $employee = $this->userWithRole('employee');
        $manager = $this->userWithRole('manager');

        $this->assertTrue(ExportGrants::allowed($admin, 'company'));
        $this->assertFalse(ExportGrants::allowed($employee, 'company'));

        $this->actingAs($admin);
        ExportGrants::save('company', [Role::findByName('employee')->id]);
        $this->assertSame([Role::findByName('employee')->id], ExportGrants::rolesFor('company'));
        $this->assertTrue(ExportGrants::allowed($employee, 'company'));
        $this->assertFalse(ExportGrants::allowed($manager, 'company'));
        $this->assertFalse(ExportGrants::allowed($employee, 'contact'));

        RecordsetFeatures::set('export', 'company', false);
        $this->assertFalse(ExportGrants::allowed($admin, 'company'));
        $this->assertFalse(ExportGrants::allowed($employee, 'company'));
    }

    public function test_a_stored_user_grant_gives_no_export_and_the_migration_removes_it(): void
    {
        $employee = $this->userWithRole('employee');
        $role = Role::findByName('employee');
        DB::table('epesi_recordbrowser_export_grants')->insert([
            ['model_type' => 'company', 'subject_type' => 'user', 'subject_id' => $employee->id],
            ['model_type' => 'contact', 'subject_type' => 'role', 'subject_id' => $role->id],
        ]);

        $this->assertFalse(ExportGrants::allowed($employee, 'company'));

        (require base_path('modules/Epesi/RecordBrowser/database/migrations/2026_11_07_100000_remove_user_export_grants.php'))->up();

        $this->assertSame(0, DB::table('epesi_recordbrowser_export_grants')->where('subject_type', 'user')->count());
        $this->assertSame([$role->id], ExportGrants::rolesFor('contact'), 'role grants stay');
    }

    public function test_the_recordset_page_offers_export_to_roles_only(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::test(ViewRecordset::class, ['alias' => 'company'])
            ->assertFormFieldExists('export_roles')
            ->assertFormFieldDoesNotExist('export_users');
    }

    public function test_export_denies_a_direct_call_without_a_grant(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        Filament::setCurrentPanel('main');

        Livewire::test(ListCompanies::class)->call('exportRecordset')->assertForbidden();
    }

    public function test_administrator_can_grant_export_on_one_recordset_from_its_detail_page(): void
    {
        $admin = $this->userWithRole('super_admin');
        $employee = $this->userWithRole('employee');
        $role = Role::findByName('employee');
        $this->actingAs($admin);
        Filament::setCurrentPanel('administration');

        Livewire::test(ViewRecordset::class, ['alias' => 'company'])
            ->fillForm(['features' => ['export'], 'export_roles' => [$role->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(ExportGrants::allowed($employee, 'company'));
        $this->assertFalse(ExportGrants::allowed($employee, 'contact'));
    }

    public function test_authorized_list_download_uses_the_filtered_table_query(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('main');
        Company::create(['company_name' => 'Alpha', 'permission' => RecordPermission::Public]);
        Company::create(['company_name' => 'Beta', 'permission' => RecordPermission::Public]);

        $list = Livewire::test(ListCompanies::class)->set('tableSearch', 'Alpha');

        $this->assertSame(['Alpha'], $list->instance()->getTableQueryForExport()->pluck('company_name')->all());
        $this->assertTrue(CompanyResource::canViewAny());
        $this->assertTrue(ExportGrants::allowed(auth()->user(), 'company'));
        $action = collect($list->instance()->getTable()->getToolbarActions())->first(fn ($action) => $action instanceof Action && $action->getName() === 'exportRecordset');
        $this->assertNotNull($action);
        $this->assertTrue($action->isVisible());
        $list->callTableAction('exportRecordset')->assertFileDownloaded();
    }

    public function test_csv_uses_the_recordset_fields_and_omits_custom_fields_excluded_from_export(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        $included = CustomField::create([
            'model_type' => 'company', 'name' => 'external_code', 'label' => 'External code',
            'type' => FieldType::Text, 'exportable' => true,
        ]);
        $excluded = CustomField::create([
            'model_type' => 'company', 'name' => 'internal_code', 'label' => 'Internal code',
            'type' => FieldType::Text, 'exportable' => false,
        ]);
        Company::create([
            'company_name' => '=HYPERLINK("https://example.test")',
            'permission' => RecordPermission::Public,
            $included->column => 'VISIBLE', $excluded->column => 'SECRET',
        ]);

        $out = fopen('php://temp', 'w+');
        (new RecordsetCsvExport(CompanyResource::class))->write($out, Company::query());
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        $this->assertStringContainsString('External code', $csv);
        $this->assertStringContainsString('VISIBLE', $csv);
        $this->assertStringNotContainsString('Internal code', $csv);
        $this->assertStringNotContainsString('SECRET', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }
}
