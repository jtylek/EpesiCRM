<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\CompanyResource;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\RecordsetFields;
use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\RecordsetRecords;
use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\Recordsets;
use Epesi\Modules\RecordBrowser\Filament\Administration\RecordInAdministration;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Administration → Recordsets → <recordset> → Records → a record: the
 * recordset's own View page inside the administration panel
 * (RecordInAdministration), deleted records too.
 */
class RecordsetRecordViewTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_the_records_tab_opens_its_rows_in_the_administration_panel(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');
        $company = Company::create(['company_name' => 'Active Co', 'permission' => RecordPermission::Public]);

        $url = RecordInAdministration::url('company', $company);
        $this->assertStringEndsWith('/administration/recordsets/company/records/'.$company->getKey(), $url);

        $page = Livewire::test(RecordsetRecords::class, ['alias' => 'company'])
            ->assertActionHasUrl(TestAction::make('view')->table($company), $url)
            // Edit stays the main panel's Edit page.
            ->assertActionHasUrl(TestAction::make('edit')->table($company), CompanyResource::getUrl('edit', ['record' => $company], panel: 'main'));

        $this->assertSame($url, $page->instance()->getTable()->getRecordUrl($company));
    }

    public function test_an_active_record_opens_in_its_own_view_page_inside_the_administration_panel(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        $company = Company::create(['company_name' => 'Active Co', 'permission' => RecordPermission::Public]);

        $this->get(RecordInAdministration::url('company', $company))
            ->assertOk()
            ->assertSee('Active Co')
            // The engine's View page: the Record Info / History kebab of its header.
            ->assertSee(__('Record Info'))
            ->assertSee(__('History'))
            // Its Edit leads to the main panel.
            ->assertSee(CompanyResource::getUrl('edit', ['record' => $company], panel: 'main'), false)
            // Drawn in the administration panel's frame (its user menu's way back to the app).
            ->assertSee(__('Back to App'))
            // The way back: Recordsets > Company > Records > the record.
            ->assertSeeInOrder([
                Recordsets::getUrl(panel: 'administration'),
                RecordsetFields::getUrl(['alias' => 'company'], panel: 'administration'),
                RecordsetRecords::getUrl(['alias' => 'company'], panel: 'administration'),
            ], false);
    }

    public function test_a_deleted_record_opens_there_too_with_restore_in_the_header(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        $company = Company::create(['company_name' => 'Deleted Co', 'permission' => RecordPermission::Public]);
        $company->delete();

        $this->get(RecordInAdministration::url('company', $company))
            ->assertOk()
            ->assertSee('Deleted Co')
            ->assertSee(__('Record Info'))
            ->assertSee(__('Restore'));

        $this->assertTrue($company->fresh()->trashed());
    }

    public function test_the_main_panels_view_page_stays_as_it_was(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        $company = Company::create(['company_name' => 'Deleted Co', 'permission' => RecordPermission::Public]);
        $company->delete();

        $this->get(CompanyResource::getUrl('view', ['record' => $company], panel: 'main'))
            ->assertOk()
            ->assertSee('Deleted Co')
            ->assertDontSee(RecordsetRecords::getUrl(['alias' => 'company'], panel: 'administration'), false)
            ->assertDontSee(__('Back to App'));
    }

    public function test_it_is_forbidden_to_anyone_but_a_super_admin(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        $active = Company::create(['company_name' => 'Active Co', 'permission' => RecordPermission::Public]);
        $deleted = Company::create(['company_name' => 'Deleted Co', 'permission' => RecordPermission::Public]);
        $deleted->delete();

        foreach (['manager', 'employee'] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->get(RecordInAdministration::url('company', $active))->assertForbidden();
            $this->get(RecordInAdministration::url('company', $deleted))->assertForbidden();
        }

        // Demo mode closes the administration panel, this page with it.
        $this->actingAs($this->userWithRole('super_admin'));
        config(['demo.enabled' => true]);
        // A deleted record is out of reach for anyone but an admin, so the lookup may answer 404 before the 403.
        $this->assertContains($this->get(RecordInAdministration::url('company', $deleted))->getStatusCode(), [403, 404]);
        $this->assertContains($this->get(RecordInAdministration::url('company', $active))->getStatusCode(), [403, 404]);
    }

    public function test_an_unknown_recordset_or_record_is_not_found(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        $company = Company::create(['company_name' => 'Active Co', 'permission' => RecordPermission::Public]);

        $this->get(RecordInAdministration::url('no_such_recordset', $company))->assertNotFound();
        $this->get(RecordInAdministration::url('company', 999999))->assertNotFound();
    }

    public function test_a_recordset_without_a_view_page_has_none_here_either(): void
    {
        Schema::create('recordset_tabs_plain_notes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        $morphMap = Relation::$morphMap;
        require_once __DIR__.'/Fixtures/RecordsetTabsPlainNote.php';

        Relation::morphMap(['recordset_tabs_plain_note' => RecordsetTabsPlainNote::class]);
        Filament::getPanel('main')->resources([RecordsetTabsPlainNoteResource::class]);

        try {
            $this->actingAs($this->userWithRole('super_admin'));
            $note = RecordsetTabsPlainNote::create(['name' => 'Plain note']);

            $this->get(RecordInAdministration::url('recordset_tabs_plain_note', $note))->assertNotFound();
        } finally {
            Relation::$morphMap = $morphMap;
        }
    }
}
