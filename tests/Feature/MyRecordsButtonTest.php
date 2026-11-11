<?php

namespace Tests\Feature;

use App\Enums\RecordPermission;
use App\Enums\RecordStatus;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\ListAttachments;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ListContacts;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\ListTasks;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\Mail\Filament\Resources\Mails\Pages\ListMails;
use Epesi\Modules\Mail\Services\MailArchiver;
use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\Fakes\Eml;
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
            ->call('removeTableFilters')
            ->assertSee('3 records');

        $this->assertSame(['label' => 'My records', 'active' => false], $list->instance()->getMyRecordsButton());
        $this->assertSame(['value' => 'all'], $list->instance()->getInactiveToggle());

        $list->call('toggleMyRecords')
            ->assertCanSeeTableRecords([$mine, $done])
            ->assertCanNotSeeTableRecords([$theirs])
            ->assertSee('2 records');

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

    public function test_notes_offer_all_my_and_my_encrypted_notes(): void
    {
        $me = $this->userWithRole('manager');
        $this->actingAs($me);

        $plain = Attachment::create(['title' => 'Plain', 'note' => 'x', 'permission' => RecordPermission::Public]);
        $secret = Attachment::create(['title' => 'Secret', 'note' => 'a
b', 'legacy_encrypted' => true, 'permission' => RecordPermission::Public]);

        $list = Livewire::test(ListAttachments::class)->call('removeTableFilters');

        $this->assertSame(['all', 'mine', 'encrypted'], array_keys($list->instance()->getMyRecordsButton()['options']));
        $this->assertSame('all', $list->instance()->getMyRecordsButton()['value']);

        $list->call('setMyRecordsMode', 'encrypted')
            ->assertCanSeeTableRecords([$secret])
            ->assertCanNotSeeTableRecords([$plain]);
        $this->assertSame('encrypted', $list->instance()->getMyRecordsButton()['value']);

        $list->call('setMyRecordsMode', 'mine')
            ->assertCanSeeTableRecords([$secret, $plain]);
        $this->assertSame('mine', $list->instance()->getMyRecordsButton()['value']);

        $list->call('setMyRecordsMode', 'encrypted')->call('setMyRecordsMode', 'all');
        $this->assertSame('all', $list->instance()->getMyRecordsButton()['value']);
    }

    public function test_my_records_on_e_mails_is_what_i_sent_received_or_archived_and_starts_on(): void
    {
        $me = $this->userWithRole('employee');
        $colleague = $this->userWithRole('employee');
        $contact = Contact::create(['first_name' => 'Me', 'last_name' => 'Myself', 'user_id' => $me->id, 'permission' => RecordPermission::Public]);
        $contact->syncCollection('emails', [['kind' => 'work', 'value' => 'me@ourcompany.test']]);

        $archiver = app(MailArchiver::class);
        $received = $archiver->archive(Eml::make(subject: 'Received, filed by a colleague', to: 'me@ourcompany.test', messageId: 'a@test'), $colleague);
        $theirs = $archiver->archive(Eml::make(subject: 'Theirs', to: 'someone@ourcompany.test', messageId: 'b@test'), $colleague);
        $filed = $archiver->archive(Eml::make(subject: 'Filed by me', to: 'someone@ourcompany.test', messageId: 'c@test'), $me);

        $this->actingAs($me);

        $list = Livewire::test(ListMails::class)
            ->assertCanSeeTableRecords([$received, $filed])
            ->assertCanNotSeeTableRecords([$theirs]);

        $this->assertSame(['label' => 'My records', 'active' => true], $list->instance()->getMyRecordsButton());

        $list->call('toggleMyRecords')->assertCanSeeTableRecords([$received, $filed, $theirs]);
        $this->assertFalse($list->instance()->getMyRecordsButton()['active']);

        $list->call('setMyRecordsMode', 'mine')->assertCanNotSeeTableRecords([$theirs]);
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

    public function test_a_resource_can_opt_out_of_my_records(): void
    {
        $user = $this->userWithRole('manager');
        Contact::create(['first_name' => 'Me', 'last_name' => 'Myself', 'user_id' => $user->id]);
        $this->actingAs($user);

        // Declared only on RecordsetResource, so this switches it for every resource until restored.
        $property = new \ReflectionProperty(RecordsetResource::class, 'recordsetMyRecords');
        $property->setValue(null, false);

        try {
            foreach ([ListContacts::class, ListTasks::class] as $page) {
                $list = Livewire::test($page);

                $this->assertNull($list->instance()->getMyRecordsButton(), $page);
                $this->assertNull($list->instance()->getTable()->getFilter(ListRecords::MY_RECORDS_FILTER), $page);
                $this->assertArrayNotHasKey(ListRecords::MY_RECORDS_FILTER, array_filter((array) $list->get('tableFilters')), $page);
            }
        } finally {
            $property->setValue(null, true);
        }

        foreach ([ListContacts::class, ListTasks::class] as $page) {
            $this->assertNotNull(Livewire::test($page)->instance()->getMyRecordsButton(), $page);
        }
        $this->assertNotNull(Livewire::test(ListContacts::class)->instance()->getTable()->getFilter(ListRecords::MY_RECORDS_FILTER));
    }
}
