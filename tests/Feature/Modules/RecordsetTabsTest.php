<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use Epesi\Modules\Attachments\AttachmentsServiceProvider;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\Mail\MailServiceProvider;
use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\RecordsetAddons;
use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\RecordsetClipboard;
use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\RecordsetFields;
use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\RecordsetPage;
use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\RecordsetRecords;
use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\RecordsetSettings;
use Epesi\Modules\RecordBrowser\Filament\Administration\RecordInAdministration;
use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\CustomFieldResource;
use Epesi\Modules\RecordBrowser\Recordset\ClipboardPattern;
use Epesi\Modules\RecordBrowser\Recordset\ExportGrants;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetFeatures;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class RecordsetTabsTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_every_tab_renders_for_a_super_admin_and_the_first_is_the_default_url(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        foreach (RecordsetPage::tabs() as $tab) {
            Livewire::test($tab, ['alias' => 'company'])->assertSuccessful();
        }

        $this->assertSame(RecordsetFields::class, RecordsetPage::tabs()[0]);
        $this->assertStringEndsWith('/recordsets/company', RecordsetFields::getUrl(['alias' => 'company']));
        $this->assertStringEndsWith('/recordsets/company/settings', RecordsetSettings::getUrl(['alias' => 'company']));
    }

    public function test_tabs_are_forbidden_to_anyone_but_a_super_admin_and_not_found_for_an_unknown_recordset(): void
    {
        Filament::setCurrentPanel('administration');

        $this->actingAs($this->userWithRole('manager'));
        foreach (RecordsetPage::tabs() as $tab) {
            $this->assertFalse($tab::canAccess());
            $this->get($tab::getUrl(['alias' => 'company']))->assertForbidden();
        }

        $this->actingAs($this->userWithRole('super_admin'));
        foreach (RecordsetPage::tabs() as $tab) {
            Livewire::test($tab, ['alias' => 'no_such_recordset'])->assertNotFound();
        }
    }

    public function test_the_tabs_have_no_recordset_selector(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        foreach (RecordsetPage::tabs() as $tab) {
            Livewire::test($tab, ['alias' => 'company'])->assertDontSeeHtml('recordset-selector');
        }
    }

    public function test_the_fields_tab_shows_that_recordsets_field_list_itself(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        $fields = Livewire::test(RecordsetFields::class, ['alias' => 'company'])
            ->assertSee('Company Name')
            ->assertDontSee('Postal Code')
            ->assertTableFilterHidden('model_type')
            ->assertTableColumnHidden('recordset')
            ->assertActionDoesNotExist('fields')
            ->assertSeeHtml('href="'.CustomFieldResource::getUrl('create', ['recordset' => 'company']).'"');

        $this->assertSame('company', $fields->instance()->selectedRecordset());
        $this->assertTrue($fields->instance()->canReorderFields());

        // The field pages lead back to this tab.
        $this->assertSame(RecordsetFields::getUrl(['alias' => 'company']), CustomFieldResource::fieldsUrl('company'));

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $fields->set('recordset', 'contact');
    }

    public function test_fields_tab_keeps_add_custom_field_when_a_filter_leaves_no_rows(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::test(RecordsetFields::class, ['alias' => 'company'])
            ->filterTable('origin', 'custom')
            ->assertSeeHtml('href="'.CustomFieldResource::getUrl('create', ['recordset' => 'company']).'"');
    }

    public function test_the_tabs_share_a_sub_navigation_and_the_breadcrumb_names_the_tab(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        $page = Livewire::test(RecordsetSettings::class, ['alias' => 'company'])->instance();

        $items = collect($page->getCachedSubNavigation())->flatMap(fn ($group) => $group->getItems());
        $this->assertSame(['Fields', 'Records', 'Addons', 'Settings', 'Clipboard'], $items->map(fn ($item) => $item->getLabel())->all());
        $this->assertContains('Settings', array_values($page->getBreadcrumbs()));
        $this->assertFalse(RecordsetSettings::shouldRegisterNavigation());
    }

    public function test_the_records_tab_lists_every_record_active_and_deleted_in_the_recordsets_own_table(): void
    {
        // Private, by someone else: an employee's list wouldn't show it to anyone but its author.
        $this->actingAs($this->userWithRole('employee'));
        $private = Company::create(['company_name' => 'Private Co', 'permission' => RecordPermission::Private]);

        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');
        $active = Company::create(['company_name' => 'Active Co', 'permission' => RecordPermission::Public]);
        $deleted = Company::create(['company_name' => 'Deleted Co', 'permission' => RecordPermission::Public]);
        $deleted->delete();

        $page = Livewire::test(RecordsetRecords::class, ['alias' => 'company'])
            ->assertSuccessful()
            ->filterTable(RecordsetRecords::DELETED_FILTER, 'all')
            ->assertCanSeeTableRecords([$active, $deleted, $private])
            ->assertTableColumnExists('company_name')
            ->assertTableColumnExists('deleted_at')
            ->assertTableFilterExists(RecordsetRecords::DELETED_FILTER)
            ->assertActionDoesNotExist('openList')
            // The main list's own ways of browsing stay there.
            ->assertActionDoesNotExist(TestAction::make('favorite')->table($active))
            // Neither "My records" nor the All / Favorites / Recent tabs (both drawn as epesi-browse-tabs).
            ->assertDontSeeHtml('epesi-browse-tabs');

        $table = $page->instance()->getTable();
        $this->assertNull($table->getFilter('trashed'));
        $this->assertNull($table->getFilter(ListRecords::MY_RECORDS_FILTER));
        $this->assertNull($table->getAction('exportRecordset'));

        // Rows open the record's View page here in the administration panel, a deleted one
        // too (RecordsetRecordViewTest covers that page).
        $this->assertSame(RecordInAdministration::url('company', $deleted), $table->getRecordUrl($deleted));
        $this->assertStringEndsWith('/administration/recordsets/company/records/'.$deleted->getKey(), $table->getRecordUrl($deleted));
    }

    public function test_the_records_tab_filters_active_and_deleted_records(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');
        $active = Company::create(['company_name' => 'Active Co', 'permission' => RecordPermission::Public]);
        $deleted = Company::create(['company_name' => 'Deleted Co', 'permission' => RecordPermission::Public]);
        $deleted->delete();

        // Active records to start with.
        Livewire::test(RecordsetRecords::class, ['alias' => 'company'])
            ->assertSet('tableFilters.'.RecordsetRecords::DELETED_FILTER.'.value', 'active')
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$deleted])
            ->filterTable(RecordsetRecords::DELETED_FILTER, 'deleted')
            ->assertCanSeeTableRecords([$deleted])
            ->assertCanNotSeeTableRecords([$active])
            ->filterTable(RecordsetRecords::DELETED_FILTER, 'all')
            ->assertCanSeeTableRecords([$active, $deleted])
            ->filterTable(RecordsetRecords::DELETED_FILTER, 'active')
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$deleted]);
    }

    public function test_the_records_select_is_shown_above_the_table_and_applies_on_change(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        $page = Livewire::test(RecordsetRecords::class, ['alias' => 'company'])
            // Active / Deleted / All, Active first, no empty choice.
            ->assertSeeInOrder([__('Active'), __('Deleted'), __('All')])
            // Not behind the funnel: no filters trigger, and no Apply button.
            ->assertDontSeeHtml('fi-ta-filters-trigger-action-ctn')
            ->assertDontSeeHtml('applyTableFilters');

        $table = $page->instance()->getTable();
        $filter = $table->getFilter(RecordsetRecords::DELETED_FILTER);

        $this->assertSame(FiltersLayout::AboveContent, $table->getFiltersLayout());
        $this->assertFalse($table->hasDeferredFilters());
        $this->assertSame('active', $filter->getDefaultState());
        $this->assertSame([], $filter->getIndicators());
        // The list's own filters stay on the main panel's list: only the select would fit the row.
        $this->assertSame([RecordsetRecords::DELETED_FILTER], array_keys($table->getFilters()));
        // The field list's one-row layout (recordset-catalogue.css).
        $this->assertStringContainsString('epesi-recordset-catalogue', $table->getExtraAttributes()['class'] ?? '');
    }

    public function test_the_records_tab_restores_a_deleted_record_and_its_history_says_so(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');
        $active = Company::create(['company_name' => 'Active Co', 'permission' => RecordPermission::Public]);
        $deleted = Company::create(['company_name' => 'Deleted Co', 'permission' => RecordPermission::Public]);
        $deleted->delete();

        Livewire::test(RecordsetRecords::class, ['alias' => 'company'])
            ->filterTable(RecordsetRecords::DELETED_FILTER, 'all')
            ->assertActionHidden(TestAction::make('restore')->table($active))
            ->assertActionVisible(TestAction::make('restore')->table($deleted))
            ->callAction(TestAction::make('restore')->table($deleted))
            ->assertHasNoActionErrors();

        $this->assertFalse($deleted->fresh()->trashed());
        $this->assertTrue($deleted->activities()->where('event', 'restored')->exists());
    }

    public function test_the_records_tab_bulk_restores_only_the_deleted_among_the_selected(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');
        $active = Company::create(['company_name' => 'Active Co', 'permission' => RecordPermission::Public]);
        $first = Company::create(['company_name' => 'First Deleted', 'permission' => RecordPermission::Public]);
        $second = Company::create(['company_name' => 'Second Deleted', 'permission' => RecordPermission::Public]);
        $first->delete();
        $second->delete();

        Livewire::test(RecordsetRecords::class, ['alias' => 'company'])
            ->filterTable(RecordsetRecords::DELETED_FILTER, 'all')
            ->selectTableRecords([$active->getKey(), $first->getKey(), $second->getKey()])
            ->callAction(TestAction::make('restore')->table()->bulk());

        $this->assertFalse($first->fresh()->trashed());
        $this->assertFalse($second->fresh()->trashed());
        $this->assertFalse($active->activities()->where('event', 'restored')->exists());
    }

    public function test_restoring_on_the_records_tab_is_refused_to_anyone_but_a_super_admin_and_in_demo_mode(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');
        $deleted = Company::create(['company_name' => 'Deleted Co', 'permission' => RecordPermission::Public]);
        $deleted->delete();

        // A manager can't open the tab, and a page already open doesn't let him restore.
        $page = Livewire::test(RecordsetRecords::class, ['alias' => 'company'])->filterTable(RecordsetRecords::DELETED_FILTER, 'deleted');
        $this->actingAs($this->userWithRole('manager'));
        Livewire::test(RecordsetRecords::class, ['alias' => 'company'])->assertForbidden();
        $page->assertActionHidden(TestAction::make('restore')->table($deleted));
        $this->assertTrue($deleted->fresh()->trashed());

        // Demo mode: closed as well, and the open page's Restore does nothing.
        $this->actingAs($this->userWithRole('super_admin'));
        $page = Livewire::test(RecordsetRecords::class, ['alias' => 'company'])->filterTable(RecordsetRecords::DELETED_FILTER, 'deleted');
        config(['demo.enabled' => true]);
        Livewire::test(RecordsetRecords::class, ['alias' => 'company'])->assertForbidden();
        $page->assertActionHidden(TestAction::make('restore')->table($deleted));
        $this->assertTrue($deleted->fresh()->trashed());
    }

    public function test_the_records_tab_of_a_recordset_without_soft_deletes_is_the_plain_list(): void
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
            Filament::setCurrentPanel('administration');
            $note = RecordsetTabsPlainNote::create(['name' => 'Plain note']);

            $page = Livewire::test(RecordsetRecords::class, ['alias' => 'recordset_tabs_plain_note'])
                ->assertSuccessful()
                ->assertCanSeeTableRecords([$note])
                ->assertTableColumnExists('name')
                ->assertActionDoesNotExist(TestAction::make('restore')->table($note));

            $table = $page->instance()->getTable();
            $this->assertNull($table->getFilter(RecordsetRecords::DELETED_FILTER));
            $this->assertNull($table->getColumn('deleted_at'));
            $this->assertSame([], $table->getToolbarActions());
            // No View page (nor Edit) to open: the rows stay unlinked, as before.
            $this->assertNull($table->getRecordUrl($note));
        } finally {
            Relation::$morphMap = $morphMap;
        }
    }

    public function test_addons_hold_the_module_features_and_settings_the_engine_ones(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        $addons = Livewire::test(RecordsetAddons::class, ['alias' => 'company'])->instance();
        $settings = Livewire::test(RecordsetSettings::class, ['alias' => 'company'])->instance();

        $addonKeys = array_keys((fn () => $this->featureOptions(false))->call($addons));
        $settingsKeys = array_keys((fn () => $this->featureOptions(true))->call($settings));

        $this->assertContains(MailServiceProvider::FEATURE, $addonKeys);
        $this->assertContains(AttachmentsServiceProvider::FEATURE, $addonKeys);
        $this->assertSame([], array_intersect($addonKeys, $settingsKeys));
        $this->assertEqualsCanonicalizing(['clipboard', 'export'], $settingsKeys);
    }

    public function test_saving_one_tab_leaves_the_other_tabs_data_alone(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');
        $role = Role::findByName('employee');

        Livewire::test(RecordsetSettings::class, ['alias' => 'company'])
            ->fillForm(['features' => ['clipboard', 'export'], 'export_roles' => [$role->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(RecordsetClipboard::class, ['alias' => 'company'])
            ->fillForm(['clipboard_pattern' => '{company_name} kept'])
            ->call('save')
            ->assertHasNoFormErrors();

        // Clipboard saved: features and export roles are untouched.
        $this->assertTrue(RecordsetFeatures::enabled('clipboard', 'company'));
        $this->assertTrue(RecordsetFeatures::enabled('export', 'company'));
        $this->assertSame([$role->id], ExportGrants::rolesFor('company'));
        $this->assertTrue(RecordsetFeatures::enabled(MailServiceProvider::FEATURE, 'company'));

        Livewire::test(RecordsetAddons::class, ['alias' => 'company'])
            ->fillForm(['features' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        // Addons saved: engine switches, roles and pattern are untouched.
        $this->assertFalse(RecordsetFeatures::enabled(MailServiceProvider::FEATURE, 'company'));
        $this->assertFalse(RecordsetFeatures::enabled(AttachmentsServiceProvider::FEATURE, 'company'));
        $this->assertTrue(RecordsetFeatures::enabled('clipboard', 'company'));
        $this->assertSame([$role->id], ExportGrants::rolesFor('company'));
        $this->assertSame('{company_name} kept', ClipboardPattern::storedPatternFor('company'));

        Livewire::test(RecordsetSettings::class, ['alias' => 'company'])
            ->fillForm(['features' => ['clipboard', 'export'], 'export_roles' => [$role->id]])
            ->call('save');

        // Settings saved again: the pattern and the addon choices stay.
        $this->assertSame('{company_name} kept', ClipboardPattern::storedPatternFor('company'));
        $this->assertFalse(RecordsetFeatures::enabled(MailServiceProvider::FEATURE, 'company'));
    }

    public function test_the_clipboard_tab_explains_when_the_feature_is_off_and_does_not_save(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');
        RecordsetFeatures::set('clipboard', 'company', false);

        Livewire::test(RecordsetClipboard::class, ['alias' => 'company'])
            ->assertSuccessful()
            ->assertFormFieldHidden('clipboard_pattern')
            ->assertActionVisible('openSettings')
            ->call('save')
            ->assertForbidden();

        $this->assertNull(ClipboardPattern::storedPatternFor('company'));
    }
}
