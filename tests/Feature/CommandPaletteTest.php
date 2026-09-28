<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * The "/" quick switcher (command-palette.blade.php, MainPanelProvider): its
 * trigger and every sidebar item are in the page, ready for Alpine to filter
 * and jump to without a round trip.
 */
class CommandPaletteTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function setUp(): void
    {
        parent::setUp();

        // With no users yet, every page goes to the setup wizard.
        $this->userWithRole('super_admin', ['email' => 'admin@example.com']);

        Filament::setCurrentPanel('main');
    }

    public function test_the_slash_key_opens_the_command_palette(): void
    {
        $this->actingAs($this->userWithRole('employee'))
            ->get('/')
            ->assertOk()
            ->assertSee("event.key !== '/'", false)
            ->assertSee("id: 'command-palette'", false)
            ->assertSee('Jump to…');
    }

    public function test_the_command_palette_lists_the_sidebar_items(): void
    {
        $this->actingAs($this->userWithRole('employee'))
            ->get('/')
            ->assertOk()
            ->assertSee('Contacts')
            ->assertSee('Companies');
    }
}
