<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class DashboardTabMemoryTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_the_dashboard_opens_on_the_last_tab_visited(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);

        $tabs = Livewire::test(Dashboard::class)->instance()->dashboardTabs;
        $second = $tabs[1];

        Livewire::test(Dashboard::class)->call('$set', 'tab', $second->id);

        $this->assertSame($second->id, session('screens')['dashboard.tab'] ?? null);
        Livewire::test(Dashboard::class)->assertSet('tab', $second->id);
    }
}
