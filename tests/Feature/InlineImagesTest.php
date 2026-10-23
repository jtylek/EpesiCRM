<?php

namespace Tests\Feature;

use App\Enums\RecordPermission;
use App\Models\StoredFile;
use App\Models\StoredFileContent;
use App\Models\StoredFileLink;
use App\Services\FileStorage;
use App\Support\Files\InlineImages;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\AttachmentResource;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\CreateAttachment;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\EditAttachment;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\RichEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class InlineImagesTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function png(string $name = 'paste.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::PNG));
    }

    /** An image as the editor stores it, already uploaded by the signed-in user. */
    private function upload(): StoredFile
    {
        return app(InlineImages::class)->store($this->png());
    }

    private function noteWith(StoredFile $image, RecordPermission $permission = RecordPermission::Public): Attachment
    {
        $note = Attachment::create([
            'title' => 'With an image',
            'note' => '<p>See:</p><p><img data-id="'.$image->getKey().'"></p>',
            'permission' => $permission,
        ]);
        $note->attachTo(Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']));

        return $note;
    }

    public function test_an_image_pasted_into_a_note_goes_to_the_file_storage_and_the_note_keeps_only_its_id(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);

        Livewire::test(CreateAttachment::class)
            ->set('componentFileAttachments.data.note.pasted-key', $this->png())
            ->fillForm([
                'attach_to' => ['contact:'.$contact->id],
                'title' => 'Screenshot',
                'note' => '<p>Look:</p><p><img src="blob:temporary" data-id="pasted-key"></p>',
                'permission' => RecordPermission::Public->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $note = $contact->attachments()->sole();
        $image = StoredFile::query()->sole();

        $this->assertSame('paste.png', $image->name);
        $this->assertSame('image/png', $image->mimeType());
        $this->assertTrue(Storage::disk(FileStorage::DISK)->exists($image->content->path()));
        $this->assertStringContainsString('data-id="'.$image->getKey().'"', $note->note);
        $this->assertStringNotContainsString('src=', $note->note);
        $this->assertSame([['attachment', $note->getKey()]], StoredFileLink::query()->get()->map(fn (StoredFileLink $link): array => [$link->owner_type, (int) $link->owner_id])->all());

        // Shown, the address is added: on the note's own page and in the editor.
        $url = route(InlineImages::ROUTE, ['storedFile' => $image->getKey()]);
        $this->assertStringContainsString('src="'.$url.'"', $note->bodyHtml());
        Livewire::test(EditAttachment::class, ['record' => $note->getKey()])
            ->assertFormSet(fn (array $state): bool => str_contains(AttachmentResource::editorHtml($state['note']), 'src="'.$url.'"'));
    }

    public function test_every_editor_takes_images_only_on_a_model_that_tracks_them(): void
    {
        $this->assertTrue(InlineImages::supports(Attachment::class));
        $this->assertFalse(InlineImages::supports(Contact::class));
        $this->assertFalse(InlineImages::supports(null));

        $this->assertTrue(RichEditor::make('note')->model(Attachment::class)->hasFileAttachments());
        $this->assertTrue(MarkdownEditor::make('note')->model(Attachment::class)->hasFileAttachments());
        $this->assertFalse(RichEditor::make('body')->model(Contact::class)->hasFileAttachments());
        $this->assertFalse(MarkdownEditor::make('body')->model(Contact::class)->hasFileAttachments());
        // An editor's own choice still wins (Campaign templates: e-mail bodies;
        // compose takes images, embedded at sending).
        $this->assertFalse(RichEditor::make('note')->model(Attachment::class)->fileAttachments(false)->hasFileAttachments());
    }

    public function test_the_image_route_serves_a_viewer_of_the_note_and_nobody_else(): void
    {
        $author = $this->userWithRole('employee');
        $colleague = $this->userWithRole('employee');

        $this->actingAs($author);
        $public = $this->noteWith($image = $this->upload());
        $private = $this->noteWith($secret = $this->upload(), RecordPermission::Private);
        $url = fn (StoredFile $file): string => route(InlineImages::ROUTE, ['storedFile' => $file->getKey()]);

        $this->get($url($image))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith('inline', (string) $this->get($url($image))->headers->get('Content-Disposition'));
        $this->get($url($secret))->assertOk();

        $this->actingAs($colleague);
        $this->get($url($image))->assertOk();
        $this->get($url($secret))->assertNotFound();

        // A note's own file isn't an editor image: never served here.
        $file = app(FileStorage::class)->put(base64_decode(self::PNG), 'file.png');
        $public->update(['files' => [$file->getKey()]]);
        $this->get($url($file))->assertNotFound();
        $this->get(route(InlineImages::ROUTE, ['storedFile' => 999999]))->assertNotFound();

        auth()->logout();
        $this->get($url($image))->assertForbidden();
        $this->assertNotNull($private);
    }

    public function test_the_uploader_sees_an_image_before_any_record_holds_it(): void
    {
        $uploader = $this->userWithRole('employee');
        $other = $this->userWithRole('employee');

        $this->actingAs($uploader);
        $image = $this->upload();
        $url = route(InlineImages::ROUTE, ['storedFile' => $image->getKey()]);

        $this->get($url)->assertOk();
        $this->actingAs($other);
        $this->get($url)->assertNotFound();
    }

    public function test_an_image_id_typed_into_a_note_does_not_take_someone_elses_image(): void
    {
        $author = $this->userWithRole('employee');
        $thief = $this->userWithRole('employee');

        $this->actingAs($author);
        $this->noteWith($secret = $this->upload(), RecordPermission::Private);
        $unsaved = $this->upload();

        $this->actingAs($thief);
        $copy = $this->noteWith($secret);
        $copy->update(['note' => $copy->note.'<img data-id="'.$unsaved->getKey().'">']);

        $this->assertFalse($copy->inlineImageLinks()->exists());
        $this->get(route(InlineImages::ROUTE, ['storedFile' => $secret->getKey()]))->assertNotFound();
        $this->get(route(InlineImages::ROUTE, ['storedFile' => $unsaved->getKey()]))->assertNotFound();
    }

    public function test_an_image_pasted_from_a_note_one_may_see_is_shown_by_both(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $first = $this->noteWith($image = $this->upload());
        $second = $this->noteWith($image);

        $this->assertSame(2, $image->links()->count());

        $first->forceDelete();
        $this->assertNotNull($image->fresh());
        $second->forceDelete();
        $this->assertNull($image->fresh());
    }

    public function test_an_encrypted_note_refuses_images(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $contact = Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']);

        Livewire::test(CreateAttachment::class)
            ->set('componentFileAttachments.data.note.pasted-key', $this->png())
            ->fillForm([
                'attach_to' => ['contact:'.$contact->id],
                'note' => '<p><img src="blob:temporary" data-id="pasted-key"></p>',
                'encrypt_note' => true,
                'encryption_password' => 'Secret#1',
                'encryption_password_confirmation' => 'Secret#1',
                'permission' => RecordPermission::Public->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['note']);

        $this->assertSame(0, Attachment::query()->count());
        $this->assertSame(0, StoredFile::query()->count());
    }

    public function test_an_image_taken_out_of_the_text_stays_until_the_note_is_deleted_for_good(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        $note = $this->noteWith($image = $this->upload());
        $content = $image->content_id;

        $note->update(['note' => '<p>No image any more</p>']);
        $note->delete();
        $this->assertNotNull($image->fresh());

        $note->forceDelete();
        $this->assertNull($image->fresh());
        $this->assertNull(StoredFileContent::query()->find($content));
        $this->assertSame(0, StoredFileLink::query()->count());
    }

    public function test_the_daily_prune_removes_unsaved_uploads_and_images_of_vanished_records(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $stale = $this->upload();
        $fresh = $this->upload();
        $kept = $this->noteWith($this->upload());
        $vanished = $this->noteWith($orphan = $this->upload());
        $noteFile = app(FileStorage::class)->put('a note file', 'file.txt');
        StoredFileLink::query()->where('stored_file_id', $stale->getKey())->update(['created_at' => now()->subDays(2)]);
        DB::table('epesi_attachments')->where('id', $vanished->getKey())->delete();

        $this->artisan('filestorage:prune-inline-images')
            ->expectsOutputToContain('Unsaved uploads: 1, links to deleted records: 1, files deleted: 2')
            ->assertSuccessful();

        $this->assertNull($stale->fresh());
        $this->assertNull($orphan->fresh());
        $this->assertNotNull($fresh->fresh());
        $this->assertNotNull($noteFile->fresh());
        $this->assertTrue($kept->inlineImageLinks()->exists());
    }

    public function test_filestorage_prune_counts_editor_images_as_referenced(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $image = $this->noteWith($this->upload())->inlineImageLinks()->sole()->storedFile;
        StoredFile::query()->update(['created_at' => now()->subDays(2)]);

        $this->artisan('filestorage:prune', ['--force' => true])->assertSuccessful();

        $this->assertNotNull($image->fresh());
    }

    public function test_images_on_the_public_disk_are_moved_into_the_file_storage_once(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('LU4ks.png', base64_decode(self::PNG));
        $this->actingAs($this->userWithRole('employee'));
        $old = '<p>Invoice:<br><img src="/storage/LU4ks.png" data-id="LU4ks.png"></p><p><img src="/storage/gone.png" data-id="gone.png"></p>';
        $note = Attachment::create(['title' => 'Old', 'note' => $old, 'permission' => RecordPermission::Private]);
        $note->attachTo(Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann']));
        $entries = $note->activities()->count();

        $this->artisan('filestorage:move-public-images', ['--dry-run' => true])
            ->expectsOutputToContain('Would convert attachment #'.$note->getKey())
            ->expectsOutputToContain('Not on the public disk, left as it is: /storage/gone.png')
            ->assertSuccessful();
        $this->assertSame($old, $note->fresh()->note);
        $this->assertSame(0, StoredFile::query()->count());

        $this->artisan('filestorage:move-public-images')
            ->expectsOutputToContain('Converted 1 record(s), 1 history entry, 1 file(s).')
            ->assertSuccessful();

        $image = StoredFile::query()->sole();
        $html = $note->fresh()->note;
        $this->assertSame('<p>Invoice:<br><img data-id="'.$image->getKey().'"></p><p><img src="/storage/gone.png" data-id="gone.png"></p>', $html);
        $this->assertSame('LU4ks.png', $image->name);
        $this->assertTrue($image->isOnDisk());
        $this->assertFalse(Storage::disk('public')->exists('LU4ks.png'));
        $this->assertTrue($note->inlineImageLinks()->where('stored_file_id', $image->getKey())->exists());
        $this->assertStringContainsString('data-id="'.$image->getKey().'"', (string) $note->activities()->first()->properties['attributes']['note']);
        // A data change, not an edit: no History entry of its own.
        $this->assertSame($entries, $note->activities()->count());
        $this->get(route(InlineImages::ROUTE, ['storedFile' => $image->getKey()]))->assertOk();

        $this->artisan('filestorage:move-public-images')
            ->expectsOutputToContain('Converted 0 record(s), 0 history entries, 0 file(s).')
            ->assertSuccessful();
        $this->assertSame($html, $note->fresh()->note);
    }

    public function test_render_resolves_ids_and_addresses_of_the_image_route(): void
    {
        $images = app(InlineImages::class);
        $url = route(InlineImages::ROUTE, ['storedFile' => 7]);

        $this->assertSame('<p><img src="'.$url.'" data-id="7" width="20"></p>', $images->render('<p><img data-id="7" width="20"></p>'));
        $this->assertSame('<img src="'.$url.'" alt="">', $images->render('<img src="https://elsewhere.example/files/inline/7" alt="">'));
        $this->assertSame('<img src="/storage/x.png" data-id="x.png">', $images->render('<img src="/storage/x.png" data-id="x.png">'));
        $this->assertSame([7, 8], $images->ids('<img data-id="7"> ![](https://host/files/inline/8)'));
    }
}
