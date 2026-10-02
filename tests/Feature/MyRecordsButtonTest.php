<?php

namespace Tests\Feature;

use App\Enums\RecordPermission;
use App\Enums\RecordStatus;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\ListAttachments;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ListContacts;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\ListTasks;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/** The "My active records" / "My records" quick filter on RecordBrowser lists. */
class MyRecordsButtonTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('main');
    }

    public function test_my_records_sets_employees_and_a_second_click_resets_and_inactive_has_its_own_switch(): void
    {
        $user = $this->userWithRole('manager');
        $me = Contact::create(['first_name' => 'Me', 'last_name' => 'Myself', 'user_id' => $user->id]);
        $other = Contact::create(['first_name' => 'Other', 'last_name' => 'Person']);
        $this->actingAs($user);

        $mine = Task::create(['title' => 'Mine', 'permission' => RecordPermission::Public]);
        $mine->employees()->attach($me);
        $done = Task::create(['title' => 'Done', 'status' => RecordStatus::Closed, 'permission' => RecordPermission::Public]);
        $done->employees()->attach($me);
        $theirs = Task::create(['title' => 'Theirs', 'permission' => RecordPermission::Public]);
        $theirs->employees()->attach($other);

        $list = Livewire::test(ListTasks::class)
            ->call('removeTableFilters');

        $this->assertSame(['label' => 'My records', 'active' => false], $list->instance()->getMyRecordsButton());
        $this->assertSame(['value' => 'all'], $list->instance()->getInactiveToggle());

        $list->call('toggleMyRecords')
            ->assertCanSeeTableRecords([$mine, $done])
            ->assertCanNotSeeTableRecords([$theirs]);

        $this->assertTrue($list->instance()->getMyRecordsButton()['active']);

        $list->call('setStatusMode', 'active')
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$done, $theirs]);

        $this->assertSame('active', $list->instance()->getInactiveToggle()['value']);

        $list->call('toggleMyRecords')
            ->assertCanSeeTableRecords([$mine, $theirs])
            ->assertCanNotSeeTableRecords([$done]);

        $list->call('setStatusMode', 'inactive')
            ->assertCanSeeTableRecords([$done])
            ->assertCanNotSeeTableRecords([$mine, $theirs]);

        $list->call('setMyRecordsMode', 'mine')
            ->assertCanSeeTableRecords([$done])
            ->assertCanNotSeeTableRecords([$mine, $theirs]);
    }

    public function test_notes_say_my_records_and_filter_by_who_edited(): void
    {
        $this->actingAs($this->userWithRole('manager'));

        $button = Livewire::test(ListAttachments::class)->instance()->getMyRecordsButton();

        $this->assertSame('My records', $button['label']);
    }

    public function test_contacts_get_a_my_records_filter_for_records_i_created_or_changed(): void
    {
        $me = $this->userWithRole('manager');
        $other = $this->userWithRole('manager');

        $this->actingAs($other);
        $theirs = Contact::create(['first_name' => 'Theirs', 'last_name' => 'Untouched', 'permission' => RecordPermission::Public]);
        $changed = Contact::create(['first_name' => 'Changed', 'last_name' => 'ByMe', 'permission' => RecordPermission::Public]);

        $this->actingAs($me);
        $created = Contact::create(['first_name' => 'Created', 'last_name' => 'ByMe', 'permission' => RecordPermission::Public]);
        $changed->update(['last_name' => 'EditedByMe']);

        $list = Livewire::test(ListContacts::class)->call('removeTableFilters');

        $this->assertSame(['label' => 'My records', 'active' => false], $list->instance()->getMyRecordsButton());

        $list->call('toggleMyRecords')
            ->assertCanSeeTableRecords([$created, $changed])
            ->assertCanNotSeeTableRecords([$theirs]);

        $this->assertTrue($list->instance()->getMyRecordsButton()['active']);

        $list->call('toggleMyRecords')->assertCanSeeTableRecords([$theirs]);
    }
}
