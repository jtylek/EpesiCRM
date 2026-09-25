<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use App\Models\StoredFile;
use App\Models\StoredFileContent;
use App\Services\FileStorage;
use Epesi\Modules\Attachments\Filament\RelationManagers\NotesRelationManager;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\AttachmentResource;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\CreateAttachment;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\EditAttachment;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\ListAttachments;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\ContactResource;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ViewContact;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class AttachmentsTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_every_core_record_type_gets_an_attachments_relation(): void
    {
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);

        $note = Attachment::addTo($contact, '<p>Called about the offer</p>', 'Offer');

        $this->assertTrue($contact->attachments()->whereKey($note->getKey())->exists());
        $this->assertSame('Offer', $note->label());
    }

    public function test_the_notes_tab_is_shown_on_a_contact_and_lists_its_notes(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);

        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);
        Attachment::addTo($contact, 'First note', 'Hello');

        $this->get(ViewContact::getUrl(['record' => $contact]))
            ->assertOk()
            ->assertSee('Notes');

        Livewire::test(NotesRelationManager::class, ['ownerRecord' => $contact, 'pageClass' => ViewContact::class])
            ->assertOk()
            ->assertSee('Hello');
    }

    public function test_the_tab_opens_every_note_page_as_part_of_its_record(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);
        $note = Attachment::addTo($contact, '<p>Went well</p>', 'Meeting recap');
        $owner = ['attachable_type' => 'contact', 'attachable_id' => $contact->id];

        Livewire::test(NotesRelationManager::class, ['ownerRecord' => $contact, 'pageClass' => ViewContact::class])
            ->assertActionHasUrl(TestAction::make('create')->table(), AttachmentResource::getUrl('create', $owner))
            ->assertActionHasUrl(TestAction::make('view')->table($note), AttachmentResource::getUrl('view', ['record' => $note, ...$owner]))
            ->assertActionHasUrl(TestAction::make('edit')->table($note), AttachmentResource::getUrl('edit', ['record' => $note, ...$owner]));

        $this->get(AttachmentResource::getUrl('create', $owner))
            ->assertOk()
            ->assertSee('New note');

        $this->get(AttachmentResource::getUrl('view', ['record' => $note, ...$owner]))
            ->assertOk()
            ->assertSee(['Meeting recap', 'Went well', 'Smith'])
            ->assertSee(AttachmentResource::getUrl('edit', ['record' => $note, ...$owner]));
    }

    public function test_the_notes_list_shows_every_note_you_can_reach(): void
    {
        $author = $this->userWithRole('employee');
        $this->actingAs($author);
        $private = Contact::create(['last_name' => 'Hidden', 'first_name' => 'Hal', 'permission' => RecordPermission::Private]);
        $public = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);
        Attachment::addTo($private, 'On a private contact', 'Secret plan');
        Attachment::addTo($public, 'On a public contact', 'Open plan');
        Attachment::create(['title' => 'Loose note', 'note' => 'On nothing', 'permission' => RecordPermission::Public]);

        Livewire::test(ListAttachments::class)
            ->assertSee(['Secret plan', 'Open plan', 'Loose note', 'Contact: '.$public->full_name]);

        $this->actingAs($this->userWithRole('employee'));

        Livewire::test(ListAttachments::class)
            ->assertSee(['Open plan', 'Loose note'])
            ->assertDontSee('Secret plan');
    }

    public function test_a_note_started_from_the_list_can_be_attached_to_a_record(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);

        Livewire::test(CreateAttachment::class)
            ->fillForm([
                'attach_to_type' => 'contact',
                'attach_to_id' => $contact->id,
                'title' => 'Call back',
                'note' => '<p>Tomorrow</p>',
                'permission' => RecordPermission::Public->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $note = $contact->attachments()->sole();
        $this->assertSame('Call back', $note->title);

        Livewire::test(CreateAttachment::class)
            ->fillForm(['title' => 'On nothing', 'permission' => RecordPermission::Public->value])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(AttachmentResource::getUrl('view', ['record' => Attachment::query()->where('title', 'On nothing')->sole()]));
    }

    public function test_a_note_is_created_on_its_page_attached_to_the_record_it_was_opened_from(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);

        Livewire::withQueryParams(['attachable_type' => 'contact', 'attachable_id' => $contact->id])
            ->test(CreateAttachment::class)
            ->fillForm([
                'title' => 'Meeting recap',
                'note' => '<p>Went well</p>',
                'permission' => RecordPermission::Public->value,
                'sticky' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(ViewContact::getUrl(['record' => $contact, 'tab' => 'notes::tab']));

        $note = $contact->attachments()->sole();
        $this->assertSame('Meeting recap', $note->title);
        $this->assertTrue($note->sticky);
    }

    public function test_a_note_is_edited_on_its_page_and_saves_to_its_view_page(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);
        $note = Attachment::addTo($contact, 'First note', 'Old title');
        $owner = ['attachable_type' => 'contact', 'attachable_id' => $contact->id];

        Livewire::withQueryParams($owner)
            ->test(EditAttachment::class, ['record' => $note->getKey()])
            ->fillForm(['title' => 'New title'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(AttachmentResource::getUrl('view', ['record' => $note, ...$owner]));

        $this->assertSame('New title', $note->fresh()->title);
    }

    public function test_a_note_is_made_sticky_from_the_list_by_whoever_may_edit_it(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);
        $public = Attachment::addTo($contact, 'Anyone may edit', 'Public');
        $readOnly = Attachment::addTo($contact, 'Only its author may edit', 'Read-only', RecordPermission::PublicReadOnly);

        $this->actingAs($this->userWithRole('employee'));

        Livewire::test(NotesRelationManager::class, ['ownerRecord' => $contact, 'pageClass' => ViewContact::class])
            ->call('updateTableColumnState', 'sticky', (string) $public->getKey(), true)
            ->call('updateTableColumnState', 'sticky', (string) $readOnly->getKey(), true)
            ->filterTable('sticky')
            ->assertCanSeeTableRecords([$public])
            ->assertCanNotSeeTableRecords([$readOnly]);

        $this->assertTrue($public->fresh()->sticky);
        $this->assertFalse($readOnly->fresh()->sticky);
    }

    public function test_the_view_page_shows_sticky_as_a_switch(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $note = Attachment::create(['title' => 'Pinned', 'note' => '<p>Body</p>', 'sticky' => true]);

        $this->get(AttachmentResource::getUrl('view', ['record' => $note]))
            ->assertOk()
            ->assertSee(['role="switch"', 'aria-checked="true"'], escape: false);
    }

    public function test_the_view_page_links_each_record_the_note_is_on(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);
        $note = Attachment::addTo($contact, 'First note');

        $this->get(AttachmentResource::getUrl('view', ['record' => $note]))
            ->assertOk()
            ->assertSee('Contact: Ann Smith')
            ->assertSee('href="'.e(ContactResource::getUrl('view', ['record' => $contact])).'"', escape: false);
    }

    public function test_a_note_page_opened_from_a_record_needs_a_record_the_note_is_on(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);
        $other = Contact::create(['last_name' => 'Jones', 'first_name' => 'Bob']);
        $note = Attachment::addTo($contact, 'First note');

        $this->get(AttachmentResource::getUrl('view', ['record' => $note, 'attachable_type' => 'contact', 'attachable_id' => $other->id]))->assertNotFound();
        $this->get(AttachmentResource::getUrl('create', ['attachable_type' => 'user', 'attachable_id' => 1]))->assertNotFound();
        $this->get(AttachmentResource::getUrl('create', ['attachable_type' => 'contact', 'attachable_id' => 999]))->assertNotFound();
        $this->get(AttachmentResource::getUrl('edit', ['record' => $note, 'attachable_type' => 'contact', 'attachable_id' => $other->id]))->assertNotFound();
    }

    public function test_someone_elses_private_note_is_hidden_and_not_downloadable(): void
    {
        $author = $this->userWithRole('employee');
        $other = $this->userWithRole('employee');
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);

        $this->actingAs($author);
        $file = app(FileStorage::class)->put('%PDF secret', 'secret.pdf');
        $note = Attachment::create([
            'title' => 'Private',
            'permission' => RecordPermission::Private,
            'files' => [$file->id],
        ]);
        $note->attachTo($contact);

        $this->get(route('epesi.attachments.download', ['attachment' => $note->id, 'file' => $file->id]))
            ->assertOk()
            ->assertDownload('secret.pdf');

        $this->actingAs($other);
        $this->assertSame(0, $contact->attachments()->count());
        $this->get(route('epesi.attachments.download', ['attachment' => $note->id, 'file' => $file->id]))
            ->assertNotFound();
    }

    public function test_guests_cannot_download(): void
    {
        $this->get(route('epesi.attachments.download', ['attachment' => 1, 'file' => 1]))->assertForbidden();
    }

    public function test_the_same_file_on_two_notes_is_stored_once_and_leaves_with_the_last(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);
        $owner = ['attachable_type' => 'contact', 'attachable_id' => $contact->id];

        foreach (['offer.pdf' => 'First', 'offer-copy.pdf' => 'Second'] as $name => $title) {
            Livewire::withQueryParams($owner)
                ->test(CreateAttachment::class)
                ->fillForm([
                    'title' => $title,
                    'permission' => RecordPermission::Public->value,
                    'files' => [UploadedFile::fake()->createWithContent($name, '%PDF the same offer')],
                ])
                ->call('create')
                ->assertHasNoFormErrors();
        }

        $first = Attachment::query()->where('title', 'First')->sole();
        $second = Attachment::query()->where('title', 'Second')->sole();
        $this->assertSame(['offer.pdf'], $first->fileList());
        $this->assertSame(['offer-copy.pdf'], $second->fileList());
        $this->assertSame(1, StoredFileContent::count());
        $this->assertCount(1, Storage::disk(FileStorage::DISK)->allFiles());

        $file = $first->storedFiles()->sole();
        $this->get(route('epesi.attachments.download', ['attachment' => $first->id, 'file' => $file->id]))
            ->assertOk()
            ->assertDownload('offer.pdf');

        // The first note's file is served through the first note only.
        $this->get(route('epesi.attachments.download', ['attachment' => $second->id, 'file' => $file->id]))
            ->assertNotFound();

        // Taken off the first note: gone from it, still kept for the second.
        Livewire::withQueryParams($owner)
            ->test(EditAttachment::class, ['record' => $first->getKey()])
            ->assertFormSet(['files' => [(string) $file->id]])
            ->fillForm(['files' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull(StoredFile::query()->find($file->id));
        $this->assertSame(1, StoredFileContent::count());

        $second->forceDelete();

        $this->assertSame(0, StoredFile::count());
        $this->assertSame(0, StoredFileContent::count());
        $this->assertSame([], Storage::disk(FileStorage::DISK)->allFiles());
    }

    public function test_a_note_cannot_take_a_file_it_was_not_given(): void
    {
        $author = $this->userWithRole('employee');
        $this->actingAs($author);
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);
        $secret = app(FileStorage::class)->put('%PDF secret', 'secret.pdf');
        Attachment::create(['title' => 'Private', 'permission' => RecordPermission::Private, 'files' => [$secret->id]])->attachTo($contact);

        $this->actingAs($this->userWithRole('employee'));
        $mine = Attachment::addTo($contact, 'Mine', 'Mine');

        Livewire::withQueryParams(['attachable_type' => 'contact', 'attachable_id' => $contact->id])
            ->test(EditAttachment::class, ['record' => $mine->getKey()])
            ->fillForm(['files' => [(string) $secret->id]])
            ->call('save')
            ->assertHasFormErrors(['files']);

        $this->assertSame([], $mine->fresh()->files ?? []);
    }
}
