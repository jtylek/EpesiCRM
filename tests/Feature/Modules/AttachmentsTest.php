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
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\ContactResource;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ViewContact;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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
                'attach_to' => [['type' => 'contact', 'id' => $contact->id]],
                'title' => 'Call back',
                'note' => '<p>Tomorrow</p>',
                'permission' => RecordPermission::Public->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $note = $contact->attachments()->sole();
        $this->assertSame('Call back', $note->title);

    }

    public function test_a_note_started_from_the_list_needs_a_record_to_be_attached_to(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        // The form starts with one row to fill in; left as it is, or emptied
        // of every row, the note is not saved.
        // Refused with an alert too, and the warning on the label's line.
        Livewire::test(CreateAttachment::class)
            ->fillForm(['title' => 'On nothing', 'permission' => RecordPermission::Public->value])
            ->call('create')
            ->assertHasFormErrors()
            ->assertNotified(__('The note was not saved'))
            ->fillForm(['attach_to' => []])
            ->call('create')
            ->assertHasFormErrors(['attach_to'])
            ->assertSeeHtml('<span class="epesi-attach-to-error">The attached to field is required.</span>');

        $this->assertSame(0, Attachment::query()->count());
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

    public function test_the_records_a_note_is_on_can_be_changed_when_editing_it(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $smith = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);
        $jones = Contact::create(['last_name' => 'Jones', 'first_name' => 'Bob']);
        $acme = Company::create(['company_name' => 'Acme']);
        $note = Attachment::addTo($smith, 'First note', 'Moving');

        // The form starts from where the note is now.
        Livewire::test(EditAttachment::class, ['record' => $note->getKey()])
            ->assertFormSet(fn (array $state): bool => array_values($state['attach_to']) === [['type' => 'contact', 'id' => $smith->id]]);

        // Moved to another record, and on to a second one of another type.
        Livewire::test(EditAttachment::class, ['record' => $note->getKey()])
            ->fillForm(['attach_to' => [
                ['type' => 'contact', 'id' => $jones->id],
                ['type' => 'company', 'id' => $acme->id],
            ]])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(AttachmentResource::getUrl('view', ['record' => $note]));

        $this->assertFalse($smith->attachments()->exists());
        $this->assertTrue($jones->attachments()->whereKey($note->getKey())->exists());
        $this->assertTrue($acme->attachments()->whereKey($note->getKey())->exists());

        // Saved again as it is: nothing is duplicated.
        Livewire::test(EditAttachment::class, ['record' => $note->getKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(2, $note->links()->count());

        // Not taken off every record: a note is on at least one, and it says so.
        Livewire::test(EditAttachment::class, ['record' => $note->getKey()])
            ->fillForm(['attach_to' => []])
            ->call('save')
            ->assertHasFormErrors(['attach_to'])
            ->assertNotified(__('The note was not saved'));

        $this->assertSame(2, $note->links()->count());
    }

    public function test_the_button_next_to_the_label_adds_a_row_for_another_record(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);
        $note = Attachment::addTo($contact, 'First note', 'Adding');

        Livewire::test(EditAttachment::class, ['record' => $note->getKey()])
            ->assertFormSet(fn (array $state): bool => count($state['attach_to']) === 1)
            ->callAction(TestAction::make('attachToRecord')->schemaComponent('attach_to'))
            ->assertFormSet(fn (array $state): bool => count($state['attach_to']) === 2);
    }

    public function test_a_note_moved_off_the_record_it_was_opened_from_saves_to_its_own_page(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $smith = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);
        $jones = Contact::create(['last_name' => 'Jones', 'first_name' => 'Bob']);
        $note = Attachment::addTo($smith, 'First note', 'Moving');
        $owner = ['attachable_type' => 'contact', 'attachable_id' => $smith->id];

        // Opened from Smith's tab, the field is there, starting with Smith.
        Livewire::withQueryParams($owner)
            ->test(EditAttachment::class, ['record' => $note->getKey()])
            ->assertFormFieldVisible('attach_to')
            ->fillForm(['attach_to' => [['type' => 'contact', 'id' => $jones->id]]])
            ->call('save')
            ->assertHasNoFormErrors()
            // Smith no longer has this note, so its page can't carry Smith.
            ->assertRedirect(AttachmentResource::getUrl('view', ['record' => $note]));

        // Kept on Smith, and put on Jones too: back through Smith's tab, as before.
        $kept = Attachment::addTo($smith, 'Second note', 'Staying');

        Livewire::withQueryParams($owner)
            ->test(EditAttachment::class, ['record' => $kept->getKey()])
            ->fillForm(['attach_to' => [['type' => 'contact', 'id' => $smith->id], ['type' => 'contact', 'id' => $jones->id]]])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(AttachmentResource::getUrl('view', ['record' => $kept, ...$owner]));

        $this->assertSame(2, $kept->links()->count());
    }

    public function test_editing_never_touches_a_link_to_a_record_you_cannot_see_and_refuses_choosing_one(): void
    {
        $author = $this->userWithRole('employee');
        $this->actingAs($author);
        $hidden = Contact::create(['last_name' => 'Hidden', 'first_name' => 'Hal', 'permission' => RecordPermission::Private]);
        $smith = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);
        $jones = Contact::create(['last_name' => 'Jones', 'first_name' => 'Bob']);
        $note = Attachment::addTo($hidden, 'On two records', 'Shared');
        $note->attachTo($smith);

        $this->actingAs($this->userWithRole('employee'));

        // The other one's form shows only what they can see...
        Livewire::test(EditAttachment::class, ['record' => $note->getKey()])
            ->assertFormSet(fn (array $state): bool => array_values($state['attach_to']) === [['type' => 'contact', 'id' => $smith->id]])
            // ...can't be pointed at Hidden...
            ->fillForm(['attach_to' => [['type' => 'contact', 'id' => $hidden->id]]])
            ->call('save')
            ->assertHasFormErrors();

        // ...and moving the note off what they see leaves Hidden as it was.
        Livewire::test(EditAttachment::class, ['record' => $note->getKey()])
            ->fillForm(['attach_to' => [['type' => 'contact', 'id' => $jones->id]]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(
            [$hidden->id, $jones->id],
            $note->links()->pluck('attachable_id')->map(fn ($id): int => (int) $id)->all(),
        );
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

    public function test_previewable_files_can_be_viewed_inline_others_still_download(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);

        $pdf = app(FileStorage::class)->put('%PDF pdf content', 'report.pdf');
        $doc = app(FileStorage::class)->put('binary doc content', 'report.docx');
        $note = Attachment::create([
            'title' => 'Files',
            'permission' => RecordPermission::Public,
            'files' => [$pdf->id, $doc->id],
        ]);
        $note->attachTo($contact);

        $this->assertTrue($pdf->isPreviewable());
        $this->assertFalse($doc->isPreviewable());

        $response = $this->get(route('epesi.attachments.download', ['attachment' => $note->id, 'file' => $pdf->id, 'preview' => 1]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));

        // Not previewable: ?preview=1 is ignored, it still forces a download.
        $this->get(route('epesi.attachments.download', ['attachment' => $note->id, 'file' => $doc->id, 'preview' => 1]))
            ->assertOk()
            ->assertDownload('report.docx');
    }

    public function test_a_markdown_file_opens_as_rendered_html_instead_of_a_download(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);

        $md = app(FileStorage::class)->put("# Title\n\nSome **bold** text.", 'notes.md');
        $note = Attachment::create(['title' => 'Files', 'permission' => RecordPermission::Public, 'files' => [$md->id]]);
        $note->attachTo($contact);

        $this->get(route('epesi.attachments.markdown', ['attachment' => $note->id, 'file' => $md->id]))
            ->assertOk()
            ->assertSee('<h1>Title</h1>', false)
            ->assertSee('<strong>bold</strong>', false);
    }

    /**
     * league/commonmark's own defaults (`html_input: allow`, `allow_unsafe_links:
     * true`) would let a script tag or a javascript: link embedded in someone's
     * uploaded markdown run in this app's own origin with the viewer's session —
     * MarkdownController must override both.
     */
    public function test_markdown_view_strips_raw_html_to_avoid_stored_xss(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);

        $md = app(FileStorage::class)->put(
            "<script>alert(1)</script>\n\n[click me](javascript:alert(1))",
            'evil.md',
        );
        $note = Attachment::create(['title' => 'Files', 'permission' => RecordPermission::Public, 'files' => [$md->id]]);
        $note->attachTo($contact);

        $response = $this->get(route('epesi.attachments.markdown', ['attachment' => $note->id, 'file' => $md->id]))
            ->assertOk();

        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertDontSee('href="javascript:alert(1)"', false);
    }

    public function test_someone_elses_private_notes_markdown_file_is_hidden(): void
    {
        $author = $this->userWithRole('employee');
        $other = $this->userWithRole('employee');
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);

        $this->actingAs($author);
        $md = app(FileStorage::class)->put('# Secret', 'secret.md');
        $note = Attachment::create(['title' => 'Private', 'permission' => RecordPermission::Private, 'files' => [$md->id]]);
        $note->attachTo($contact);

        $this->actingAs($other);
        $this->get(route('epesi.attachments.markdown', ['attachment' => $note->id, 'file' => $md->id]))
            ->assertNotFound();
    }

    public function test_the_view_page_shows_each_file_as_a_badge_with_view_only_where_it_can_be_viewed_and_a_link_of_its_own(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);
        $pdf = app(FileStorage::class)->put('%PDF pdf content', 'report.pdf');
        $doc = app(FileStorage::class)->put('binary doc content', 'report.docx');
        $note = Attachment::create(['title' => 'Files', 'permission' => RecordPermission::Public, 'files' => [$pdf->id, $doc->id]]);
        $note->attachTo($contact);

        $response = $this->get(AttachmentResource::getUrl('view', ['record' => $note]))
            ->assertOk()
            ->assertSee(['report.pdf', 'report.docx'])
            // One View, for the PDF only; a Download and a Get link for each.
            ->assertSee('/attachments/'.$note->id.'/files/'.$pdf->id.'?preview=1', escape: false)
            ->assertSee('/attachments/'.$note->id.'/files/'.$doc->id.'/shared?', escape: false);

        $this->assertSame(1, substr_count($response->getContent(), 'title="View"'));
        $this->assertSame(2, substr_count($response->getContent(), 'data-file-preview '), 'only the PDF opens in the pop-up: its name and View');
    }

    public function test_the_notes_tab_shows_a_files_count_badge_then_each_ones_chip(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);
        $pdf = app(FileStorage::class)->put('%PDF pdf content', 'report.pdf');
        $doc = app(FileStorage::class)->put('binary doc content', 'report.docx');
        $withFiles = Attachment::create(['title' => 'Files', 'permission' => RecordPermission::Public, 'files' => [$pdf->id, $doc->id]]);
        $withFiles->attachTo($contact);
        Attachment::addTo($contact, 'No files here', 'Plain');

        Livewire::test(NotesRelationManager::class, ['ownerRecord' => $contact, 'pageClass' => ViewContact::class])
            ->assertSee('2 files')
            ->assertSeeHtml('/attachments/'.$withFiles->id.'/files/'.$pdf->id.'?preview=1')
            ->assertSeeHtml('/attachments/'.$withFiles->id.'/files/'.$doc->id)
            ->assertSee(['report.pdf', 'report.docx']);
    }

    public function test_the_notes_list_also_shows_a_files_count_badge_then_each_ones_chip(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);
        $pdf = app(FileStorage::class)->put('%PDF pdf content', 'report.pdf');
        $doc = app(FileStorage::class)->put('binary doc content', 'report.docx');
        $withFiles = Attachment::create(['title' => 'Files', 'permission' => RecordPermission::Public, 'files' => [$pdf->id, $doc->id]]);
        $withFiles->attachTo($contact);
        Attachment::addTo($contact, 'No files here', 'Plain');

        // The badge says what the chips are, so there is no "Files" label,
        // and the note without files gets no files line at all.
        Livewire::test(ListAttachments::class)
            ->assertSee('2 files')
            ->assertSee(['report.pdf', 'report.docx'])
            ->assertDontSeeHtml('epesi-note-meta-label">Files<');
    }

    public function test_the_notes_list_puts_files_then_attached_to_then_edited_on_under_the_note(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);
        $pdf = app(FileStorage::class)->put('%PDF pdf content', 'report.pdf');
        $note = Attachment::create(['title' => 'Order', 'permission' => RecordPermission::Public, 'files' => [$pdf->id]]);
        $note->attachTo($contact);

        $html = Livewire::test(ListAttachments::class)->html();

        $files = strpos($html, 'report.pdf');
        $attachedTo = strpos($html, 'Attached to');
        $editedOn = strpos($html, 'Edited on');

        $this->assertNotFalse($files);
        $this->assertLessThan($attachedTo, $files);
        $this->assertLessThan($editedOn, $attachedTo);
    }

    public function test_get_link_serves_a_private_notes_file_without_login_or_the_usual_permission_check(): void
    {
        $author = $this->userWithRole('employee');
        $this->actingAs($author);
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);
        $file = app(FileStorage::class)->put('spreadsheet content', 'figures.xlsx');
        $note = Attachment::create([
            'title' => 'Private',
            'permission' => RecordPermission::Private,
            'files' => [$file->id],
        ]);
        $note->attachTo($contact);

        $url = URL::temporarySignedRoute('epesi.attachments.shared', now()->addWeek(), [
            'attachment' => $note->id,
            'file' => $file->id,
        ]);

        // Nobody's signed in from here on: the whole point of the link.
        auth()->logout();

        $this->get($url)->assertOk()->assertDownload('figures.xlsx');
    }

    public function test_get_link_rejects_a_url_without_a_valid_signature(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);
        $file = app(FileStorage::class)->put('spreadsheet content', 'figures.xlsx');
        $note = Attachment::create(['title' => 'Note', 'permission' => RecordPermission::Public, 'files' => [$file->id]]);
        $note->attachTo($contact);

        auth()->logout();

        $this->get(route('epesi.attachments.shared', ['attachment' => $note->id, 'file' => $file->id]))
            ->assertForbidden();
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
