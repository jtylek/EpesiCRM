<?php

namespace Tests\Feature;

use App\Filament\UserSettings\Pages\QuickAccessSettings;
use App\Support\QuickAccess;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * The top bar's Quick Access icons (quick-access.blade.php) and the user
 * setting that picks them (QuickAccessSettings).
 */
class QuickAccessTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userWithRole('super_admin', ['email' => 'admin@example.com']);
    }

    public function test_the_default_modules_are_in_the_top_bar_with_tooltips(): void
    {
        Filament::setCurrentPanel('main');

        $this->actingAs($this->userWithRole('employee'))
            ->get('/')
            ->assertOk()
            ->assertSee('epesi-quick-access')
            ->assertSee('aria-label="Dashboard"', false)
            ->assertSee('/companies', false)
            ->assertSee('Phone Calls');
    }

    public function test_the_limit_is_enforced_on_what_is_saved(): void
    {
        Filament::setCurrentPanel('user-settings');

        $this->actingAs($this->userWithRole('super_admin'));

        // Every item the sidebar has fits today; the page itself refuses to go beyond MAX.
        $keys = array_slice(array_keys(QuickAccess::available()), 0, QuickAccess::MAX);

        Livewire::test(QuickAccessSettings::class)
            ->fillForm(['items' => $keys])
            ->call('save')
            ->assertNotified(__('Quick Access saved'));

        $this->assertSame($keys, QuickAccess::selected());

        QuickAccess::save(array_merge($keys, ['one-too-many']));

        $this->assertCount(QuickAccess::MAX, QuickAccess::selected());
    }

    public function test_the_chosen_modules_are_saved(): void
    {
        Filament::setCurrentPanel('user-settings');

        $this->actingAs($this->userWithRole('employee'));

        Livewire::test(QuickAccessSettings::class)
            ->fillForm(['items' => ['tasks', 'contacts']])
            ->call('save')
            ->assertNotified(__('Quick Access saved'));

        $this->assertSame(['tasks', 'contacts'], QuickAccess::selected());
    }
}
