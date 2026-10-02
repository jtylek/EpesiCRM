<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\Attachments\Attachments;
use Epesi\Modules\Attachments\AttachmentsServiceProvider;
use Epesi\Modules\Mail\MailServiceProvider;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Pages\ListCustomFields;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetFeatures;
use Filament\Actions\Testing\TestAction;
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
        $this->assertEqualsCanonicalizing(MailServiceProvider::DEFAULT_RECORD_TYPES, MailServiceProvider::recordTypes());

        RecordsetFeatures::set(MailServiceProvider::FEATURE, 'task', false);

        $this->assertNotContains('task', MailServiceProvider::recordTypes());
    }

    public function test_the_features_action_saves_the_choice_for_a_recordset(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::test(ListCustomFields::class)
            ->filterTable('model_type', 'company')
            ->callAction(TestAction::make('features'), data: ['recordset' => 'company', 'features' => ['notes']])
            ->assertHasNoActionErrors();

        $this->assertFalse(RecordsetFeatures::enabled(MailServiceProvider::FEATURE, 'company'));
        $this->assertTrue(RecordsetFeatures::enabled(AttachmentsServiceProvider::FEATURE, 'company'));
        $this->assertTrue(RecordsetFeatures::enabled(MailServiceProvider::FEATURE, 'contact'), 'other recordsets are untouched');
    }
}
