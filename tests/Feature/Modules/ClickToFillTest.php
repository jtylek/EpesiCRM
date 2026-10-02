<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\CreateCompany;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\EditCompany;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\ViewCompany;
use Epesi\Modules\CRM\Companies\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class ClickToFillTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_click_to_fill_is_available_on_create_and_edit_but_not_view(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $company = Company::create(['company_name' => 'Alpha', 'permission' => RecordPermission::Public]);

        foreach ([CreateCompany::getUrl(), EditCompany::getUrl(['record' => $company])] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('Click 2 Fill')
                ->assertSee("\$dispatch('epesi-click-to-fill-toggle')", false)
                ->assertSee('x-on:epesi-click-to-fill-toggle.window="open = !open"', false)
                ->assertSee('Scan text')
                // Asset injection must preserve JavaScript escapes as well as its contents.
                ->assertSee(file_get_contents(base_path('modules/Epesi/RecordBrowser/resources/js/click-to-fill.js')), false)
                ->assertSee('fi-color-primary', false)
                ->assertSee("{ 'fi-color': selected.includes(index) }", false);
        }

        $this->get(ViewCompany::getUrl(['record' => $company]))->assertOk()->assertDontSee('Click 2 Fill');
    }
}
