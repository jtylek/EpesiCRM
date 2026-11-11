<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\ContactResource;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ViewContact;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\ViewRecordset;
use Epesi\Modules\RecordBrowser\Recordset\ClipboardPattern;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetFeatures;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * The View page's "Copy to clipboard" header action and Administration's
 * Edit modal extension for it — AI-shared/recordbrowser/clipboard.md.
 */
class ClipboardViewActionTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_the_icon_shows_only_when_the_feature_is_on_and_a_pattern_exists(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('main'));
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['first_name' => 'Ann', 'last_name' => 'Buyer']);

        $actions = fn () => array_filter(
            Livewire::test(ViewContact::class, ['record' => $contact->getRouteKey()])->instance()->getCachedHeaderActions(),
            fn ($action) => method_exists($action, 'getName') && $action->getName() === 'clipboard',
        );

        // On by default: Contacts/ContactsServiceProvider calls Clipboard::enableFor().
        $this->assertNotEmpty($actions());

        RecordsetFeatures::set('clipboard', 'contact', false);
        $this->assertEmpty($actions());

        RecordsetFeatures::set('clipboard', 'contact', true);
        ClipboardPattern::setPattern('contact', ''); // back to the (still present) module default
        $this->assertNotEmpty($actions());
    }

    public function test_a_stored_override_wins_and_clearing_it_restores_the_module_default(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('main'));
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel(Filament::getPanel('administration'));

        $default = ClipboardPattern::patternFor('company');

        Livewire::test(ViewRecordset::class, ['alias' => 'company'])
            ->fillForm([
                'features' => ['clipboard'],
                'clipboard_pattern' => '{company_name} override',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('{company_name} override', ClipboardPattern::storedPatternFor('company'));

        Livewire::test(ViewRecordset::class, ['alias' => 'company'])
            ->fillForm([
                'features' => ['clipboard'],
                'clipboard_pattern' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull(ClipboardPattern::storedPatternFor('company'));
        $this->assertSame($default, ClipboardPattern::patternFor('company'));
    }

    public function test_saving_an_invalid_pattern_is_rejected(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('administration'));
        $this->actingAs($this->userWithRole('super_admin'));

        Livewire::test(ViewRecordset::class, ['alias' => 'company'])
            ->fillForm([
                'features' => ['clipboard'],
                'clipboard_pattern' => '%{a %{b} c}',
            ])
            ->call('save')
            ->assertHasFormErrors(['clipboard_pattern']);

        $this->assertNull(ClipboardPattern::storedPatternFor('company'));
    }

    public function test_a_user_without_view_access_cannot_open_the_view_page_at_all(): void
    {
        $this->withoutVite();
        $contact = Contact::create(['first_name' => 'Ann', 'last_name' => 'Buyer']);
        $this->actingAs($this->userWithRole('customer'));

        $this->get(ContactResource::getUrl('view', ['record' => $contact]))->assertForbidden();
    }
}
