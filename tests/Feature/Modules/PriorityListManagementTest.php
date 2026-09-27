<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\PriorityList\Filament\Pages\PriorityListManagement;
use Epesi\Modules\PriorityList\PriorityList;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Administration → Priority list types: turning a discovered record type on
 * or off, with no code needed to make it a candidate in the first place.
 */
class PriorityListManagementTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');
    }

    public function test_only_a_super_admin_can_open_it(): void
    {
        $this->get(PriorityListManagement::getUrl())->assertOk();

        $this->actingAs($this->userWithRole('manager'))
            ->get(PriorityListManagement::getUrl())
            ->assertForbidden();
    }

    public function test_it_lists_registered_and_discovered_types_with_their_state(): void
    {
        Livewire::test(PriorityListManagement::class)
            ->assertTableActionExists('toggle', record: 'task')
            ->assertTableActionExists('toggle', record: 'company');
    }

    public function test_toggling_a_discovered_type_turns_it_on_and_back_off(): void
    {
        $this->assertFalse(PriorityList::isEnabled('company'));

        Livewire::test(PriorityListManagement::class)
            ->callTableAction('toggle', 'company')
            ->assertNotified('Company is now enabled');

        $this->assertTrue(PriorityList::isEnabled('company'));

        Livewire::test(PriorityListManagement::class)
            ->callTableAction('toggle', 'company')
            ->assertNotified('Company is now disabled');

        $this->assertFalse(PriorityList::isEnabled('company'));
    }

    public function test_a_registered_type_can_be_turned_off_too(): void
    {
        $this->assertTrue(PriorityList::isEnabled('task'));

        Livewire::test(PriorityListManagement::class)
            ->callTableAction('toggle', 'task')
            ->assertNotified('Task is now disabled');

        $this->assertFalse(PriorityList::isEnabled('task'));
    }
}
