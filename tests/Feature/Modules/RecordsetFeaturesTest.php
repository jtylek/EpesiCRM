<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\Attachments\Attachments;
use Epesi\Modules\Attachments\AttachmentsServiceProvider;
use Epesi\Modules\Mail\MailServiceProvider;
use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\Recordsets;
use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\ViewRecordset;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\CustomFieldResource;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Pages\ListCustomFields;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetFeatures;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class RecordsetFeaturesTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function tearDown(): void
    {
        // flush() only forgets the cached per-record choices; reset() also
        // forgets demo_feature itself, or it leaks "Demo" into later tests
        // (TranslationsTest's crawl picked it up as real UI text to translate).
        RecordsetFeatures::reset();

        parent::tearDown();
    }

    public function test_a_module_declares_its_defaults_and_an_administrator_choice_wins_either_way(): void
    {
        RecordsetFeatures::define('demo_feature', 'Demo', ['company']);

        $this->assertSame(['company'], RecordsetFeatures::aliasesFor('demo_feature'));

        RecordsetFeatures::set('demo_feature', 'company', false);
        RecordsetFeatures::set('demo_feature', 'project', true);

        $this->assertSame(['project'], RecordsetFeatures::aliasesFor('demo_feature'));
        $this->assertFalse(RecordsetFeatures::enabled('demo_feature', 'company'));

        RecordsetFeatures::set('demo_feature', 'company', true);

        $this->assertEqualsCanonicalizing(['company', 'project'], RecordsetFeatures::aliasesFor('demo_feature'));
    }

    public function test_another_module_can_turn_a_feature_on_by_default_and_the_administrator_can_still_turn_it_off(): void
    {
        Attachments::enableFor('project');

        $this->assertContains('project', AttachmentsServiceProvider::recordTypes());

        RecordsetFeatures::set(AttachmentsServiceProvider::FEATURE, 'project', false);

        $this->assertNotContains('project', AttachmentsServiceProvider::recordTypes());
    }

    public function test_mail_starts_on_the_crm_recordsets_and_follows_the_choice(): void
    {
        // Installed modules may switch it on for their own recordsets too.
        $this->assertSame([], array_values(array_diff(MailServiceProvider::DEFAULT_RECORD_TYPES, MailServiceProvider::recordTypes())));

        RecordsetFeatures::set(MailServiceProvider::FEATURE, 'task', false);

        $this->assertNotContains('task', MailServiceProvider::recordTypes());
    }

    public function test_clipboard_starts_on_company_and_contact_and_follows_the_choice(): void
    {
        // Companies/CompaniesServiceProvider and Contacts/ContactsServiceProvider
        // each call Clipboard::enableFor(), which both declares the default and
        // sets the module's pattern text (Clipboard::defaultPattern()).
        $this->assertContains('company', RecordsetFeatures::aliasesFor('clipboard'));
        $this->assertContains('contact', RecordsetFeatures::aliasesFor('clipboard'));

        RecordsetFeatures::set('clipboard', 'company', false);

        $this->assertNotContains('company', RecordsetFeatures::aliasesFor('clipboard'));
    }

    public function test_the_recordsets_list_has_no_related_modules_button(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::test(ListCustomFields::class)
            ->assertActionDoesNotExist('features');
    }

    public function test_manage_fields_opens_the_field_list_on_that_recordset_only(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::test(ViewRecordset::class, ['alias' => 'company'])
            ->assertActionHasUrl('fields', CustomFieldResource::getUrl('index', ['recordset' => 'company']))
            ->assertActionHasUrl('addField', CustomFieldResource::getUrl('create', ['recordset' => 'company']));

        $list = Livewire::withQueryParams(['recordset' => 'company'])
            ->test(ListCustomFields::class)
            ->assertSee('Company Name')
            ->assertDontSee('Postal Code')
            ->assertTableFilterHidden('model_type')
            ->assertTableColumnHidden('recordset')
            ->assertActionHasUrl('create', CustomFieldResource::getUrl('create', ['recordset' => 'company']));

        $this->assertTrue($list->instance()->canReorderFields(), 'reordering needs no filter choice');

        $breadcrumbs = array_keys($list->instance()->getBreadcrumbs());
        $this->assertSame(Recordsets::getUrl(), $breadcrumbs[0]);
        $this->assertSame(ViewRecordset::getUrl(['alias' => 'company']), $breadcrumbs[1]);
    }

    public function test_a_field_list_opened_from_a_recordset_can_not_be_moved_to_another(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        // Neither a filter in the URL nor one set afterwards leaves the recordset.
        Livewire::withQueryParams(['recordset' => 'company', 'filters' => ['model_type' => ['value' => 'contact']]])
            ->test(ListCustomFields::class)
            ->assertSee('Company Name')
            ->assertDontSee('Postal Code')
            ->set('tableFilters.model_type.value', 'contact')
            ->assertSee('Company Name');

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::withQueryParams(['recordset' => 'company'])
            ->test(ListCustomFields::class)
            ->set('recordset', 'contact');
    }

    public function test_a_field_list_for_an_unknown_recordset_is_not_found(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::withQueryParams(['recordset' => 'no_such_recordset'])
            ->test(ListCustomFields::class)
            ->assertNotFound();
    }

    public function test_the_list_only_opens_a_recordset(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::test(Recordsets::class)
            ->assertSuccessful()
            ->assertSee('company')
            ->assertTableActionExists('view')
            ->assertTableActionDoesNotExist('edit')
            ->assertTableActionHasUrl('view', ViewRecordset::getUrl(['alias' => 'company']), 'company');
    }

    public function test_the_recordset_page_lists_recordsets_and_saves_the_choice(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::test(Recordsets::class)
            ->assertSuccessful();

        Livewire::test(ViewRecordset::class, ['alias' => 'company'])
            ->fillForm(['features' => ['notes']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse(RecordsetFeatures::enabled(MailServiceProvider::FEATURE, 'company'));
        $this->assertTrue(RecordsetFeatures::enabled(AttachmentsServiceProvider::FEATURE, 'company'));
        $this->assertTrue(RecordsetFeatures::enabled(MailServiceProvider::FEATURE, 'contact'), 'other recordsets are untouched');
    }

    public function test_the_administration_sidebar_is_pinned_items_then_server_setup_then_data(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        $navigation = collect(Filament::getNavigation());
        $labels = fn (?string $group): array => collect($navigation
            ->first(fn ($g): bool => $g->getLabel() === $group)
            ->getItems())
            ->map(fn ($item): string => $item->getLabel())
            ->values()
            ->all();

        $this->assertSame(['About', 'Epesi Store', 'Modules', 'Database update'], array_slice($labels(null), 0, 4));
        // Optional modules (e.g. Premium ones) may add their own pages to these groups.
        $shipped = fn (array $expected, array $actual): array => array_values(array_intersect($actual, $expected));
        $serverSetup = ['Background worker', 'Cron', 'Mail Server', 'Regional Settings', 'Server Check', 'Translations'];
        $data = ['Common Data', 'Currencies', 'Exchange Rates', 'Priority list', 'Recordsets'];
        $this->assertSame($serverSetup, $shipped($serverSetup, $labels('Server Setup')));
        $this->assertSame($data, $shipped($data, $labels('Data')));
        $this->assertSame(['Server Setup', 'Data'], $navigation->map(fn ($g) => $g->getLabel())->filter()->take(2)->values()->all());
        $this->assertFalse(CustomFieldResource::shouldRegisterNavigation());
        $this->assertSame('Recordsets', Recordsets::getNavigationLabel());
    }
}
