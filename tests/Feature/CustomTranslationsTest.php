<?php

namespace Tests\Feature;

use App\Filament\Administration\Pages\Translations;
use App\Support\Translations\CustomTranslations;
use App\Support\Translations\TranslationCatalog;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\CompanyResource;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Administration → Translations: an administrator's own translations, kept
 * apart from the shipped ones and loaded over them.
 */
class CustomTranslationsTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_a_custom_translation_wins_over_every_shipped_one(): void
    {
        CustomTranslations::put('pl', 'Contacts', 'Klienci');            // lang/pl.json
        CustomTranslations::put('pl', 'Watched', 'Śledzone');            // a module's lang/pl.json
        CustomTranslations::put('pl', 'record_labels.status.open', 'Nowe'); // a group file
        CustomTranslations::put('en', 'Companies', 'Clients');           // English, which has no file

        $this->assertSame('Klienci', __('Contacts', [], 'pl'));
        $this->assertSame('Śledzone', __('Watched', [], 'pl'));
        $this->assertSame('Nowe', __('record_labels.status.open', [], 'pl'));
        $this->assertSame('Clients', __('Companies', [], 'en'));
        $this->assertSame('Firmy', __('Companies', [], 'pl'), 'only in the language it was made for');

        // The sidebar reads "Companies", not "companies" capitalised.
        app()->setLocale('en');
        $this->assertSame('Clients', CompanyResource::getNavigationLabel());
        app()->setLocale('pl');
        $this->assertSame('Firmy', CompanyResource::getNavigationLabel());
        $this->assertSame('Firma', CompanyResource::getTitleCaseModelLabel());

        CustomTranslations::put('pl', 'Contacts', null);

        $this->assertSame('Kontakty', __('Contacts', [], 'pl'), 'removed: the shipped one again');
        $this->assertArrayNotHasKey('Contacts', CustomTranslations::for('pl'));
    }

    public function test_only_a_language_code_names_a_file(): void
    {
        $this->assertSame([], CustomTranslations::for('../../.env'));

        $this->expectException(InvalidArgumentException::class);
        CustomTranslations::put('../pl', 'Contacts', 'Klienci');
    }

    public function test_the_list_shows_what_is_translated_missing_and_custom(): void
    {
        CustomTranslations::put('pl', 'Contacts', 'Klienci');
        CustomTranslations::put('pl', 'A word of our own', 'Nasze słowo');

        $polish = TranslationCatalog::for('pl');

        $this->assertSame(['english' => 'Companies', 'source' => 'epesi', 'shipped' => 'Firmy', 'custom' => null, 'status' => TranslationCatalog::TRANSLATED],
            array_intersect_key($polish['Companies'], array_flip(['english', 'source', 'shipped', 'custom', 'status'])));
        $this->assertSame(['shipped' => 'Kontakty', 'custom' => 'Klienci', 'status' => TranslationCatalog::CUSTOM],
            array_intersect_key($polish['Contacts'], array_flip(['shipped', 'custom', 'status'])));
        $this->assertSame('Watchdog', $polish['Watched']['source']);
        $this->assertSame('Open', $polish['record_labels.status.open']['english']);
        $this->assertSame(TranslationCatalog::CUSTOM, $polish['A word of our own']['status']);

        // A language nothing ships yet: every string waits for a translation.
        $german = TranslationCatalog::for('de');
        $this->assertSame(TranslationCatalog::MISSING, $german['Companies']['status']);
        $this->assertSame([TranslationCatalog::MISSING], array_values(array_unique(array_column($german, 'status'))));

        // English is the strings themselves.
        $this->assertSame(TranslationCatalog::TRANSLATED, TranslationCatalog::for('en')['Companies']['status']);
    }

    public function test_an_administrator_translates_and_removes_a_translation(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');
        $contacts = md5('Contacts');

        $page = Livewire::test(Translations::class)
            ->set('language', 'pl')
            ->searchTable('Contacts')
            ->assertActionHidden(TestAction::make('revert')->table($contacts))
            ->callAction(TestAction::make('translate')->table($contacts), ['translation' => 'Klienci'])
            ->assertHasNoFormErrors();

        $this->assertSame(['Contacts' => 'Klienci'], CustomTranslations::for('pl'));

        // The shipped translation typed back in is not kept as a custom one.
        $page->callAction(TestAction::make('translate')->table($contacts), ['translation' => 'Kontakty']);
        $this->assertSame([], CustomTranslations::for('pl'));

        $page->callAction(TestAction::make('translate')->table($contacts), ['translation' => 'Klienci'])
            ->assertActionVisible(TestAction::make('revert')->table($contacts))
            ->callAction(TestAction::make('revert')->table($contacts));
        $this->assertSame([], CustomTranslations::for('pl'));

        // Renaming one form of a word points at the others.
        $page->searchTable('Companies')
            ->mountAction(TestAction::make('translate')->table(md5('Companies')))
            ->assertMountedActionModalSee('companies, Company, company');
    }

    public function test_a_text_that_is_not_listed_can_be_added_and_downloaded(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::test(Translations::class)
            ->set('language', 'pl')
            ->assertActionHidden('download')
            ->callAction('add', ['english' => 'Customer since', 'translation' => 'Klient od'])
            ->assertHasNoFormErrors()
            ->assertActionVisible('download')
            ->callAction('download')
            ->assertFileDownloaded('pl.json', CustomTranslations::encode(['Customer since' => 'Klient od']));

        $this->assertSame('Klient od', __('Customer since', [], 'pl'));
    }

    public function test_it_is_for_super_admins_only(): void
    {
        $this->actingAs($this->userWithRole('manager'))
            ->get(Translations::getUrl(panel: 'administration'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('super_admin'))
            ->get(Translations::getUrl(panel: 'administration'))
            ->assertOk()
            ->assertSee('Contacts');
    }
}
