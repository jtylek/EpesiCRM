<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\Attachments\Attachments;
use Epesi\Modules\Attachments\AttachmentsServiceProvider;
use Epesi\Modules\Mail\MailServiceProvider;
use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\RelatedModules;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Pages\ListCustomFields;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetFeatures;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class RecordsetFeaturesTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function tearDown(): void
    {
        RecordsetFeatures::flush();

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

    public function test_the_recordsets_list_has_no_related_modules_button(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::test(ListCustomFields::class)
            ->assertActionDoesNotExist('features');
    }

    public function test_the_related_modules_page_lists_recordsets_and_saves_the_choice(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::test(RelatedModules::class)
            ->assertSuccessful()
            ->callTableAction('edit', 'company', data: ['features' => ['notes']])
            ->assertHasNoTableActionErrors();

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
        $this->assertSame(['Cron', 'Mail Server', 'Regional Settings', 'Server Check', 'Translations'], $labels('Server Setup'));
        $this->assertSame(['Common Data', 'Currencies', 'Exchange Rates', 'Priority list types', 'Recordsets', 'Related modules'], $labels('Data'));
        $this->assertSame(['Server Setup', 'Data'], $navigation->map(fn ($g) => $g->getLabel())->filter()->take(2)->values()->all());
    }
}
