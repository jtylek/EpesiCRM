<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\AgendaWidget;
use App\Models\DashboardApplet;
use App\Models\DashboardTab;
use App\Models\User;
use Epesi\Modules\CRM\PhoneCalls\Filament\Widgets\PhoneCallsWidget;
use Epesi\Modules\CRM\Tasks\Filament\Widgets\TasksWidget;
use Epesi\Modules\Mail\Filament\Widgets\UnreadMailWidget;
use Epesi\Modules\PriorityList\Filament\Widgets\PriorityListWidget;
use Epesi\Modules\Reminders\Filament\Widgets\MyRemindersWidget;
use Epesi\Modules\Shoutbox\Filament\Widgets\ShoutboxWidget;
use Epesi\Modules\StickyNotes\Filament\Widgets\StickyNotesWidget;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Epesi's Base_Dashboard: each user's own tabs of applets in three columns,
 * dragged into place (wire:sort calls moveApplet), added from "Add applet",
 * set up or removed from each one's gear.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_a_first_visit_gets_the_default_tabs_and_applets(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);

        $this->assertSame([
            [PriorityListWidget::class],
            [ShoutboxWidget::class],
            [MyRemindersWidget::class],
        ], $this->columns());

        $this->assertSame(['Main', 'Agenda', 'Notes'], $this->tabNames($user));

        $agenda = DashboardTab::query()->where('name', 'Agenda')->sole();

        $this->assertSame([
            [AgendaWidget::class],
            [TasksWidget::class],
            [PhoneCallsWidget::class],
        ], $this->columns($agenda));

        $notes = DashboardTab::query()->where('name', 'Notes')->sole();

        $this->assertSame([[StickyNotesWidget::class], [], []], $this->columns($notes));
    }

    public function test_the_default_dashboard_leaves_out_applets_that_are_not_installed(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);

        config(['dashboard.default' => [
            'Mine' => [[AgendaWidget::class, 'Epesi\Modules\Gone\GoneWidget'], [], [TasksWidget::class]],
            'Nothing left' => [['Epesi\Modules\Gone\GoneWidget']],
        ]]);

        $this->assertSame([[AgendaWidget::class], [], [TasksWidget::class]], $this->columns());
        $this->assertSame(['Mine'], $this->tabNames($user));

        // With nothing to place, there is still a tab to add applets to.
        $someoneElse = $this->userWithRole('employee');
        $this->actingAs($someoneElse);
        config(['dashboard.default' => []]);

        $this->assertSame([[], [], []], $this->columns());
        $this->assertSame(['Dashboard'], $this->tabNames($someoneElse));
    }

    public function test_an_applet_moves_between_and_within_columns(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $this->columns();

        $shoutbox = $this->applet(ShoutboxWidget::class);
        $priorityList = $this->applet(PriorityListWidget::class);

        Livewire::test(Dashboard::class)
            ->call('moveApplet', (string) $shoutbox->id, 0, '2')
            ->call('moveApplet', (string) $priorityList->id, 99, '2');

        $this->assertSame(ShoutboxWidget::class, $this->columns()[2][0]);
        $this->assertSame(PriorityListWidget::class, last($this->columns()[2]));

        Livewire::test(Dashboard::class)->call('moveApplet', (string) $priorityList->id, 0, '2');

        $this->assertSame([PriorityListWidget::class, ShoutboxWidget::class], array_slice($this->columns()[2], 0, 2));
    }

    public function test_each_user_keeps_their_own_dashboard(): void
    {
        $alice = $this->userWithRole('employee');
        $bob = $this->userWithRole('employee');

        $this->actingAs($bob);
        $default = $this->columns();

        $this->actingAs($alice);
        $this->columns();
        Livewire::test(Dashboard::class)->call('moveApplet', (string) $this->applet(ShoutboxWidget::class)->id, 0, '2');

        $this->assertSame(ShoutboxWidget::class, $this->columns()[2][0]);

        $this->actingAs($bob);
        $this->assertSame($default, $this->columns());

        // Nor can Alice's page move, set up or remove Bob's applets.
        $bobs = DashboardApplet::query()->where('user_id', $bob->id)->where('widget', TasksWidget::class)->sole();

        $this->actingAs($alice);
        Livewire::test(Dashboard::class)->call('moveApplet', (string) $bobs->id, 0, '2');
        Livewire::test(Dashboard::class)->call('openAppletSettings', $bobs->id)->assertNotFound();

        $this->assertSame($bobs->only(['col', 'pos']), $bobs->fresh()->only(['col', 'pos']));
    }

    public function test_an_applet_can_be_added_more_than_once_and_opens_its_settings(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $this->columns();

        Livewire::test(Dashboard::class)
            ->callAction('addApplet', ['widget' => TasksWidget::class])
            ->assertActionMounted('configureApplet')
            ->fillForm(['settings.subtitle' => 'Sales'])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $copies = DashboardApplet::query()->where('widget', TasksWidget::class)->orderBy('id')->get();

        $this->assertCount(2, $copies);
        $this->assertNull($copies[0]->settings);
        $this->assertSame('Sales', $copies[1]->settings['subtitle']);
        $this->assertEquals([0, 1, 2], $copies[1]->settings['statuses']);

        // An applet the user may not see isn't offered.
        Livewire::test(Dashboard::class)
            ->callAction('addApplet', ['widget' => UnreadMailWidget::class])
            ->assertHasFormErrors(['widget' => 'in']);
    }

    public function test_settings_are_saved_and_reach_the_widget(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $this->columns();
        $tasks = $this->applet(TasksWidget::class);

        Livewire::test(Dashboard::class)
            ->call('openAppletSettings', $tasks->id)
            ->assertActionMounted('configureApplet')
            ->assertSchemaStateSet(['settings.related' => 'employee', 'settings.statuses' => [0, 1, 2]])
            ->fillForm(['settings.subtitle' => 'Sales', 'settings.related' => 'any'])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $page = Livewire::test(Dashboard::class)->instance();
        $properties = $page->getAppletProperties($tasks->fresh());

        $this->assertSame($tasks->id, $properties['appletId']);
        $this->assertSame('Sales', $properties['appletSettings']['subtitle']);
        $this->assertSame('any', $properties['appletSettings']['related']);

        Livewire::withoutLazyLoading()
            ->test(TasksWidget::class, $properties)
            ->assertSee('Tasks - Sales');
    }

    public function test_an_applet_is_removed_from_its_settings(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $this->columns();
        $shoutbox = $this->applet(ShoutboxWidget::class);

        Livewire::test(Dashboard::class)
            ->callAction([
                TestAction::make('configureApplet')->arguments(['applet' => $shoutbox->id]),
                TestAction::make('removeApplet'),
            ]);

        $this->assertModelMissing($shoutbox);
        $this->assertNotContains(ShoutboxWidget::class, array_merge(...$this->columns()));
    }

    public function test_tabs_are_added_renamed_reordered_and_deleted_with_their_applets(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);
        $this->columns();
        [$main, $agenda] = DashboardTab::query()->orderBy('pos')->get()->all();
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(Dashboard::class)
            ->callAction('manageTabs', ['tabs' => [
                ['id' => null, 'name' => 'Sales'],
                ['id' => $agenda->id, 'name' => 'Agenda'],
                ['id' => $main->id, 'name' => 'Mine'],
            ]])
            ->assertHasNoFormErrors();

        // Notes was left out of the form but stays, last.
        $this->assertSame(['Sales', 'Agenda', 'Mine', 'Notes'], $this->tabNames($user));
        $sales = DashboardTab::query()->where('name', 'Sales')->sole();
        $this->assertSame(0, $sales->applets()->count());

        // An applet moves to another tab from its settings.
        $tasks = $this->applet(TasksWidget::class);

        Livewire::test(Dashboard::class)
            ->call('openAppletSettings', $tasks->id)
            ->fillForm(['tab' => $sales->id])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertTrue($tasks->fresh()->tab->is($sales));
        $this->assertSame([[], [TasksWidget::class], []], $this->columns($sales));
        $this->assertSame([[AgendaWidget::class], [], [PhoneCallsWidget::class]], $this->columns($agenda));

        // Deleting a tab deletes its applets.
        Livewire::test(Dashboard::class)
            ->callAction('manageTabs', ['tabs' => [
                ['id' => $agenda->id, 'name' => 'Agenda'],
                ['id' => $main->id, 'name' => 'Mine'],
            ]])
            ->assertHasNoFormErrors();

        $this->assertSame(['Agenda', 'Mine', 'Notes'], $this->tabNames($user));
        $this->assertModelMissing($tasks);

        $undoRepeaterFake();
    }

    public function test_main_agenda_and_notes_are_system_tabs_that_cannot_be_deleted(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);
        $this->columns();

        $this->assertSame(['main', 'agenda', 'notes'], DashboardTab::query()->orderBy('pos')->pluck('key')->all());

        $undoRepeaterFake = Repeater::fake();

        // A form that leaves them out, or one that names only a new tab, deletes none of them.
        Livewire::test(Dashboard::class)
            ->callAction('manageTabs', ['tabs' => [['id' => null, 'name' => 'Sales']]])
            ->assertHasNoFormErrors();

        $this->assertSame(['Sales', 'Main', 'Agenda', 'Notes'], $this->tabNames($user));
        $this->assertSame(1, DashboardApplet::query()->where('widget', StickyNotesWidget::class)->count());

        $undoRepeaterFake();
    }

    public function test_the_tabs_form_offers_no_delete_button_for_a_system_tab(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $this->columns();
        DashboardTab::query()->create(['user_id' => auth()->id(), 'name' => 'Sales', 'pos' => 9]);

        $page = Livewire::test(Dashboard::class)->mountAction('manageTabs')->instance();
        $schema = (new \ReflectionMethod($page, 'getMountedActionSchema'))->invoke($page);
        $repeater = collect($schema->getFlatComponents(withHidden: true))->first(fn ($component): bool => $component instanceof Repeater);
        $visible = [];

        foreach (array_keys($repeater->getRawState()) as $key) {
            $visible[$repeater->getRawState()[$key]['name']] = $repeater->getAction('delete')(['item' => $key])->isVisible();
        }

        $this->assertSame(['Main' => false, 'Agenda' => false, 'Notes' => false, 'Sales' => true], $visible);
    }

    public function test_the_notes_applet_is_only_on_the_notes_tab(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);
        $this->columns();
        $main = DashboardTab::query()->where('key', 'main')->sole();
        $notes = DashboardTab::query()->where('key', 'notes')->sole();

        // Add applet is not there on the Notes tab.
        Livewire::test(Dashboard::class)
            ->set('tab', $notes->id)
            ->assertActionHidden('addApplet');

        // On another tab it is, without Notes among the choices.
        $onMain = Livewire::test(Dashboard::class)
            ->set('tab', $main->id)
            ->assertActionVisible('addApplet');

        $offered = (new \ReflectionMethod(Dashboard::class, 'availableApplets'))->invoke($onMain->instance());

        $this->assertTrue($offered->has(PriorityListWidget::class));
        $this->assertFalse($offered->has(StickyNotesWidget::class));

        // The Notes applet can't be moved to another tab, nor another applet onto the Notes tab.
        $tasks = $this->applet(TasksWidget::class);

        Livewire::test(Dashboard::class)
            ->call('openAppletSettings', $tasks->id)
            ->fillForm(['tab' => $notes->id])
            ->callMountedAction();

        $this->assertFalse($tasks->fresh()->tab->is($notes));
    }

    public function test_the_dashboard_renders_its_columns_as_sortable_lists(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $this->columns();
        $shoutbox = $this->applet(ShoutboxWidget::class);

        // wire:sort:item hands moveApplet its attribute text as is.
        $this->get('/')
            ->assertOk()
            ->assertSee('wire:sort.ghost="moveApplet"', escape: false)
            ->assertSee('wire:sort:item="'.$shoutbox->id.'"', escape: false)
            ->assertSeeLivewire(ShoutboxWidget::class)
            ->assertSeeLivewire(PriorityListWidget::class)
            ->assertSeeLivewire(MyRemindersWidget::class)
            // Only the tab on show is mounted.
            ->assertDontSeeLivewire(TasksWidget::class)
            ->assertDontSee('No applets on this tab');
    }

    /**
     * The widget classes on a tab (else the one on show), column by column.
     *
     * @return array<int, list<class-string>>
     */
    private function columns(?DashboardTab $tab = null): array
    {
        $page = Livewire::test(Dashboard::class);

        if ($tab) {
            $page->set('tab', $tab->id);
        }

        return array_map(
            fn (array $applets): array => array_map(fn (DashboardApplet $applet): string => $applet->widget, $applets),
            $page->instance()->getAppletColumns(),
        );
    }

    private function applet(string $widget): DashboardApplet
    {
        return DashboardApplet::query()->where('user_id', auth()->id())->where('widget', $widget)->firstOrFail();
    }

    /**
     * @return list<string>
     */
    private function tabNames(User $user): array
    {
        return DashboardTab::query()->where('user_id', $user->id)->orderBy('pos')->pluck('name')->all();
    }
}
