<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\ViewTask;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Epesi\Modules\Watchdog\Filament\Livewire\DatabaseNotifications;
use Epesi\Modules\Watchdog\Filament\Pages\Watched;
use Epesi\Modules\Watchdog\Models\Subscription;
use Epesi\Modules\Watchdog\Watchdog;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class WatchdogTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_the_author_of_a_record_watches_it_and_others_changes_notify_them(): void
    {
        $author = $this->userWithRole('employee');
        $colleague = $this->userWithRole('employee');

        $this->actingAs($author);
        $task = Task::create(['title' => 'Call the bank', 'permission' => RecordPermission::Public]);
        $this->assertTrue(Watchdog::isSubscribed($author, $task));

        $this->actingAs($colleague);
        $task->update(['title' => 'Call the bank today']);

        $this->assertSame(1, Watchdog::unseenCount($author, $task));
        $this->assertSame(1, $author->notifications()->count());
        $this->assertSame(0, $colleague->notifications()->count(), 'nobody is told about their own change');

        Watchdog::markSeen($author, $task);
        $this->assertSame(0, Watchdog::unseenCount($author, $task));
    }

    public function test_an_update_logs_and_reports_only_what_changed(): void
    {
        $author = $this->userWithRole('employee');
        $colleague = $this->userWithRole('employee', ['name' => 'Ann']);

        $this->actingAs($author);
        $created = Task::create(['title' => 'Call the bank', 'permission' => RecordPermission::Public]);

        $this->actingAs($colleague);

        // Straight after create(): the columns left out (status, priority,
        // timeless) must already hold their defaults, not null.
        $created->update(['title' => 'Call the bank today']);
        $this->assertSame(['title'], array_keys($created->activities()->latest('id')->first()->properties['attributes']));

        // As a page would have it: loaded fresh from the database.
        $task = Task::query()->withoutGlobalScopes()->findOrFail($created->id);
        $task->update(['title' => 'Call the bank tomorrow']);
        $this->assertSame(['title'], array_keys($task->activities()->latest('id')->first()->properties['attributes']));

        $this->assertSame('Ann updated: Title.', $author->notifications()->latest('id')->first()->data['body']);
    }

    public function test_notifications_reach_the_bell_without_a_queue_worker(): void
    {
        // The default install queues on the database; Filament's database
        // notification is ShouldQueue, so it must not be left in `jobs`.
        config(['queue.default' => 'database']);

        $author = $this->userWithRole('employee');
        $colleague = $this->userWithRole('employee');

        $this->actingAs($author);
        $task = Task::create(['title' => 'Call the bank', 'permission' => RecordPermission::Public]);

        $this->actingAs($colleague);
        $task->update(['title' => 'Call the bank today']);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => $author->getMorphClass(),
            'notifiable_id' => $author->id,
        ]);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_opening_a_record_marks_it_read_and_the_header_toggle_subscribes(): void
    {
        $author = $this->userWithRole('employee');
        $viewer = $this->userWithRole('employee');

        $this->actingAs($author);
        $task = Task::create(['title' => 'Offer', 'permission' => RecordPermission::Public]);

        $this->actingAs($viewer);
        $task->update(['title' => 'Offer v2']);

        $this->actingAs($author);
        $this->assertSame(1, Watchdog::unseenCount($author, $task));
        $this->assertSame(1, $author->unreadNotifications()->count());
        $this->get(ViewTask::getUrl(['record' => $task]))->assertOk()->assertSee('Watching');
        $this->assertSame(0, Watchdog::unseenCount($author, $task));
        $this->assertSame(0, $author->unreadNotifications()->count(), 'the bell agrees');

        $this->actingAs($viewer);
        Livewire::test(ViewTask::class, ['record' => $task->getKey()])
            ->callAction('watchdogToggle');
        $this->assertTrue(Watchdog::isSubscribed($viewer, $task));
    }

    public function test_its_links_open_the_records_history_in_any_language(): void
    {
        $author = $this->userWithRole('employee');
        $colleague = $this->userWithRole('employee');
        RegionalSetting::query()->create(['user_id' => $author->id, 'language' => 'pl']);

        $this->actingAs($author);
        $task = Task::create(['title' => 'Call the bank', 'permission' => RecordPermission::Public]);

        $this->actingAs($colleague);
        $task->update(['title' => 'Call the bank today']);

        $url = Watchdog::url($task);
        $this->assertSame(ViewTask::getUrl(['record' => $task, 'tab' => 'history::tab'], panel: 'main'), $url);
        $this->assertSame($url, $author->notifications()->sole()->data['actions'][0]['url']);

        // The tab reads "Historia" in Polish and keeps its key all the same.
        $this->actingAs($author)->get($url)->assertOk()->assertSee('Historia')->assertSee('history::tab', false);
    }

    public function test_watching_a_record_type_never_reveals_private_records(): void
    {
        $author = $this->userWithRole('employee');
        $watcher = $this->userWithRole('employee');
        Watchdog::subscribeToCategory($watcher, 'task');

        $this->actingAs($author);
        $secret = Task::create(['title' => 'Secret', 'permission' => RecordPermission::Private]);
        Task::create(['title' => 'Open', 'permission' => RecordPermission::Public]);

        $this->assertSame(1, $watcher->notifications()->count());
        $this->assertFalse(Watchdog::isSubscribed($watcher, $secret));
    }

    public function test_a_change_heard_of_through_a_record_type_is_on_the_watched_page_too(): void
    {
        $author = $this->userWithRole('employee');
        $other = $this->userWithRole('employee', ['name' => 'Ann']);
        $watcher = $this->userWithRole('employee');

        $this->actingAs($author);
        $task = Task::create(['title' => 'Budget', 'permission' => RecordPermission::Public]);

        // A record that was there before its type was watched.
        Watchdog::subscribeToCategory($watcher, 'task');

        $this->actingAs($other);
        $task->update(['title' => 'Budget 2027']);

        $this->assertSame(1, $watcher->notifications()->count());
        $this->assertSame(1, Watchdog::unseenCount($watcher, $task));

        $this->actingAs($watcher);
        $subscription = Subscription::query()->where('user_id', $watcher->id)->sole();
        Livewire::test(Watched::class)
            ->assertCanSeeTableRecords([$subscription])
            ->assertTableColumnStateSet('changed_by', 'Ann', $subscription)
            ->assertTableColumnStateSet('changes', 'Title', $subscription);
    }

    public function test_the_watched_page_lists_subscriptions_with_new_changes(): void
    {
        $author = $this->userWithRole('employee');
        $other = $this->userWithRole('employee', ['name' => 'Ann']);

        $this->actingAs($author);
        $task = Task::create(['title' => 'Budget', 'permission' => RecordPermission::Public]);

        $this->actingAs($other);
        $task->update(['title' => 'Budget 2027']);

        $this->actingAs($author);
        $quiet = Task::create(['title' => 'Nothing new here', 'permission' => RecordPermission::Public]);
        $this->get(Watched::getUrl())->assertOk();

        $changed = Subscription::query()->where('user_id', $author->id)->where('subscribable_id', $task->id)->sole();
        $unchanged = Subscription::query()->where('user_id', $author->id)->where('subscribable_id', $quiet->id)->sole();

        // The Type column names the record type, so Record does not repeat it.
        Livewire::test(Watched::class)
            ->assertTableColumnStateSet('record', 'Budget 2027', $changed)
            ->assertTableColumnStateSet('changed_by', 'Ann', $changed)
            ->assertTableColumnStateSet('changes', 'Title', $changed)
            ->assertCanSeeTableRecords([$changed])
            ->assertCanNotSeeTableRecords([$unchanged])
            ->filterTable('unseen', null)
            ->assertCanSeeTableRecords([$changed, $unchanged]);

        $this->assertSame('1', Watched::getNavigationBadge());
    }

    public function test_the_watched_page_skips_your_own_edits_and_marks_records_read(): void
    {
        $author = $this->userWithRole('employee');
        $other = $this->userWithRole('employee', ['name' => 'Ann']);

        $this->actingAs($author);
        $changed = Task::create(['title' => 'Budget', 'permission' => RecordPermission::Public]);
        $ownEdit = Task::create(['title' => 'Offer', 'permission' => RecordPermission::Public]);
        $ownEdit->update(['title' => 'Offer v2']);

        $this->actingAs($other);
        $changed->update(['title' => 'Budget 2027']);

        $this->actingAs($author);
        $subscription = fn (Task $task): Subscription => Subscription::query()
            ->where('user_id', $author->id)->where('subscribable_id', $task->id)->sole();

        Livewire::test(Watched::class)
            ->assertCanSeeTableRecords([$subscription($changed)])
            ->assertCanNotSeeTableRecords([$subscription($ownEdit)])
            ->callAction(TestAction::make('markRead')->table($subscription($changed)))
            ->assertCanNotSeeTableRecords([$subscription($changed)])
            ->assertDispatched('databaseNotificationsSent');

        $this->assertSame(0, Watchdog::unseenCount($author, $changed));
        $this->assertSame(0, $author->unreadNotifications()->count(), 'the bell agrees');
    }

    public function test_reading_a_notification_in_the_bell_marks_its_change_seen(): void
    {
        $author = $this->userWithRole('employee');
        $other = $this->userWithRole('employee');

        $this->actingAs($author);
        $task = Task::create(['title' => 'Budget', 'permission' => RecordPermission::Public]);

        $this->actingAs($other);
        $task->update(['title' => 'Budget 2027']);
        $task->update(['title' => 'Budget 2028']);

        $this->actingAs($author);
        [$older, $newer] = $author->notifications()->get()
            ->sortBy(fn ($notification): int => $notification->data['watchdog']['activity_id'])
            ->values()
            ->all();
        $this->assertSame(2, Watchdog::unseenCount($author, $task));

        // One change read: the later one is still new, in both places.
        Livewire::test(DatabaseNotifications::class)
            ->call('markNotificationAsRead', $older->id)
            ->assertDispatched('refresh-sidebar');
        $this->assertSame(1, Watchdog::unseenCount($author, $task));
        $this->assertNull($newer->fresh()->read_at);

        Livewire::test(DatabaseNotifications::class)->call('markAllNotificationsAsRead');
        $this->assertSame(0, Watchdog::unseenCount($author, $task));
        $this->assertNull(Watched::getNavigationBadge());
    }

    public function test_dismissing_notifications_in_the_bell_marks_their_changes_seen(): void
    {
        $author = $this->userWithRole('employee');
        $other = $this->userWithRole('employee');

        $this->actingAs($author);
        $budget = Task::create(['title' => 'Budget', 'permission' => RecordPermission::Public]);
        $offer = Task::create(['title' => 'Offer', 'permission' => RecordPermission::Public]);

        $this->actingAs($other);
        $budget->update(['title' => 'Budget 2027']);
        $offer->update(['title' => 'Offer v2']);

        $this->actingAs($author);
        $budgetNotification = $author->notifications()->get()
            ->first(fn ($notification): bool => $notification->data['watchdog']['subject_id'] === $budget->id);

        Livewire::test(DatabaseNotifications::class)->call('removeNotification', $budgetNotification->id);
        $this->assertSame(0, Watchdog::unseenCount($author, $budget));
        $this->assertSame(1, Watchdog::unseenCount($author, $offer));

        Livewire::test(DatabaseNotifications::class)->call('clearNotifications');
        $this->assertSame(0, Watchdog::unseenCount($author, $offer));
        $this->assertSame(0, $author->notifications()->count());
    }

    public function test_the_panel_uses_the_bell_that_marks_changes_seen(): void
    {
        $this->actingAs($this->userWithRole('employee'))
            ->get(Watched::getUrl())
            ->assertOk()
            ->assertSeeLivewire(DatabaseNotifications::class)
            ->assertDontSeeLivewire(\Filament\Livewire\DatabaseNotifications::class);
    }
}
