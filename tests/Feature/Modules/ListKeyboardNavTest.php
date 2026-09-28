<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\ListCompanies;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Pages\ListCustomFields;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * list-keyboard-nav.blade.php: once "/" (CommandPaletteTest) has landed you
 * on a List page, A/F/R jump to its All/Favorites/Recent tab (only the ones
 * a resource actually has), S focuses the search field, and ↑/↓/PageUp/
 * PageDown/Enter browse the table rows without a mouse.
 */
class ListKeyboardNavTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_a_list_with_favorites_and_recent_gets_all_three_tab_shortcuts(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        Livewire::test(ListCompanies::class)
            ->assertSeeHtml("event.key.toLowerCase() === 'a'")
            ->assertSeeHtml("\$wire.set('activeTab', 'all')")
            ->assertSeeHtml("event.key.toLowerCase() === 'f'")
            ->assertSeeHtml("\$wire.set('activeTab', 'favorites')")
            ->assertSeeHtml("event.key.toLowerCase() === 'r'")
            ->assertSeeHtml("\$wire.set('activeTab', 'recent')");
    }

    public function test_a_list_with_no_extra_tabs_still_gets_search_and_row_navigation_but_no_tab_keys(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel(Filament::getPanel('administration'));

        Livewire::test(ListCustomFields::class)
            ->assertSeeHtml('.fi-ta-search-field input')
            ->assertSeeHtml('ArrowDown')
            ->assertSeeHtml('PageDown')
            ->assertDontSeeHtml("\$wire.set('activeTab'");
    }

    public function test_a_highlighted_row_opens_the_same_place_a_click_would(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $company = Company::create(['company_name' => 'Alpha', 'permission' => RecordPermission::Public]);

        Livewire::test(ListCompanies::class)
            ->assertSeeHtml("querySelector('a[href]')")
            ->assertSeeHtml('epesi-row-active')
            ->assertCanSeeTableRecords([$company]);
    }

    public function test_n_opens_the_same_create_link_the_header_button_does(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        Livewire::test(ListCompanies::class)
            ->assertSeeHtml("event.key.toLowerCase() === 'n'")
            ->assertSeeHtml("a[href\$=\\'/create\\']", false);
    }
}
