<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Models\DashboardApplet;
use Epesi\Modules\Mail\Filament\Widgets\UnreadMailWidget;
use Epesi\Modules\Reminders\Filament\Widgets\MyRemindersWidget;
use Epesi\Modules\Shoutbox\Filament\Widgets\ShoutboxWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Applets are dragged within and between the dashboard's columns (wire:sort
 * calls moveApplet), and each user's arrangement is their own.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_applets_start_spread_across_the_columns(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $columns = $this->columns();

        $this->assertCount(Dashboard::COLUMNS, $columns);
        $this->assertSame([MyRemindersWidget::class], $columns[0]);
        $this->assertSame([ShoutboxWidget::class], $columns[1]);
    }

    public function test_an_applet_moves_between_and_within_columns(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        Livewire::test(Dashboard::class)
            ->call('moveApplet', ShoutboxWidget::class, 0, '2')
            ->call('moveApplet', MyRemindersWidget::class, 5, '2');

        $this->assertSame([[], [], [ShoutboxWidget::class, MyRemindersWidget::class]], $this->columns());

        Livewire::test(Dashboard::class)->call('moveApplet', MyRemindersWidget::class, 0, '2');

        $this->assertSame([[], [], [MyRemindersWidget::class, ShoutboxWidget::class]], $this->columns());
    }

    public function test_each_user_keeps_their_own_arrangement(): void
    {
        $alice = $this->userWithRole('employee');
        $bob = $this->userWithRole('employee');

        $this->actingAs($bob);
        $default = $this->columns();

        $this->actingAs($alice);
        Livewire::test(Dashboard::class)->call('moveApplet', ShoutboxWidget::class, 0, '0');

        $this->assertSame([ShoutboxWidget::class, MyRemindersWidget::class], $this->columns()[0]);

        $this->actingAs($bob);
        $this->assertSame($default, $this->columns());
        $this->assertSame(0, DashboardApplet::query()->where('user_id', $bob->id)->count());
    }

    public function test_an_applet_the_user_cannot_see_is_not_placed(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        // No mail account with IMAP, so no Mail applet.
        Livewire::test(Dashboard::class)
            ->call('moveApplet', UnreadMailWidget::class, 0, '0')
            ->call('moveApplet', 'App\\Nonexistent\\Widget', 0, '0');

        $this->assertSame(0, DashboardApplet::count());
    }

    public function test_the_dashboard_renders_its_columns_as_sortable_lists(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        // wire:sort:item hands moveApplet its attribute text as is, so it has
        // to be the bare class name, not a quoted JS string.
        $this->get('/')
            ->assertOk()
            ->assertSee('wire:sort.ghost="moveApplet"', escape: false)
            ->assertSee('wire:sort:item="'.ShoutboxWidget::class.'"', escape: false)
            ->assertSeeLivewire(MyRemindersWidget::class)
            ->assertSeeLivewire(ShoutboxWidget::class);
    }

    /**
     * @return array<int, list<class-string>>
     */
    private function columns(): array
    {
        return array_map(array_keys(...), Livewire::test(Dashboard::class)->instance()->getAppletColumns());
    }
}
