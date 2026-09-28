<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\CreateCompany;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\EditCompany;
use Epesi\Modules\CRM\Companies\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * form-keyboard-shortcuts.blade.php: Ctrl/Cmd+E cancels a Create or Edit
 * page — Ctrl/Cmd+S already saves on both by itself, Filament's own default
 * — and a Create page focuses its first field on load.
 */
class FormKeyboardShortcutsTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_a_create_page_focuses_its_first_field_and_ctrl_e_cancels(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        Livewire::test(CreateCompany::class)
            ->assertSeeHtml("document.getElementById('form')?.querySelector")
            ->assertSeeHtml("event.key.toLowerCase() === 'e'")
            ->assertSeeHtml('event.ctrlKey || event.metaKey')
            ->assertSeeHtml('nextElementSibling');
    }

    public function test_an_edit_page_does_not_steal_focus_but_ctrl_e_still_cancels(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $company = Company::create(['company_name' => 'Alpha', 'permission' => RecordPermission::Public]);

        Livewire::test(EditCompany::class, ['record' => $company->getKey()])
            ->assertDontSeeHtml("document.getElementById('form')?.querySelector")
            ->assertSeeHtml("event.key.toLowerCase() === 'e'")
            ->assertSeeHtml('event.ctrlKey || event.metaKey')
            ->assertSeeHtml('nextElementSibling');
    }

    public function test_ctrl_s_already_saves_without_anything_of_ours(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $company = Company::create(['company_name' => 'Alpha', 'permission' => RecordPermission::Public]);

        // Filament's own default keyBindings on the Save action — mousetrap's
        // cross-platform "mod" alias for Ctrl/Cmd.
        Livewire::test(CreateCompany::class)->assertSeeHtml('mod-s');
        Livewire::test(EditCompany::class, ['record' => $company->getKey()])->assertSeeHtml('mod-s');
    }
}
