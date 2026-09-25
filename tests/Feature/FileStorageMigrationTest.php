<?php

namespace Tests\Feature;

use App\Enums\RecordPermission;
use App\Models\StoredFile;
use App\Models\StoredFileContent;
use App\Services\FileStorage;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\Mail\Models\Mail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The migrations that moved notes' files and e-mail attachments from paths on
 * the `local` disk into the file storage, run back and forth: down() puts the
 * files back where they were, up() takes them in again, once each.
 */
class FileStorageMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notes_files_move_into_the_file_storage_and_back(): void
    {
        Storage::fake('local');
        $migration = require base_path('modules/Epesi/Attachments/database/migrations/2026_09_27_010000_move_epesi_attachment_files_to_file_storage.php');

        $storage = app(FileStorage::class);
        $note = Attachment::create([
            'title' => 'Offer',
            'permission' => RecordPermission::Public,
            'files' => [$storage->put('%PDF offer', 'offer.pdf')->id, $storage->put('%PDF offer', 'offer again.pdf')->id],
        ]);

        $migration->down();

        $row = DB::table('epesi_attachments')->find($note->id);
        $paths = json_decode($row->files, true);
        $this->assertCount(2, $paths);
        $this->assertSame(['offer.pdf', 'offer again.pdf'], array_values(json_decode($row->file_names, true)));
        $this->assertSame('%PDF offer', Storage::disk('local')->get($paths[0]));
        $this->assertSame(0, StoredFile::count());
        $this->assertSame(0, StoredFileContent::count());

        $migration->up();

        $this->assertFalse(Schema::hasColumn('epesi_attachments', 'file_names'));
        $note = $note->fresh();
        $this->assertSame(['offer.pdf', 'offer again.pdf'], $note->fileList());
        $this->assertSame(1, StoredFileContent::count());
        $this->assertSame([], Storage::disk('local')->allFiles(), 'the old copies are gone');

        // Run again (a previous run stopped half-way): nothing changes.
        Schema::table('epesi_attachments', fn ($table) => $table->json('file_names')->nullable());
        $migration->up();
        $this->assertSame(['offer.pdf', 'offer again.pdf'], $note->fresh()->fileList());
    }

    public function test_mail_attachments_move_into_the_file_storage_and_back(): void
    {
        Storage::fake('local');
        $migration = require base_path('modules/Epesi/Mail/database/migrations/2026_09_27_020000_move_epesi_mail_attachments_to_file_storage.php');

        $storage = app(FileStorage::class);
        $mails = collect(['a@x', 'b@x'])->map(function (string $id) use ($storage): Mail {
            $mail = Mail::create(['message_id' => $id, 'subject' => 'Offer', 'from' => 'ann@customer.test', 'to' => 'me@ourcompany.test']);
            $mail->attachments()->create(['name' => 'offer.pdf', 'size' => 10, 'stored_file_id' => $storage->put('%PDF offer', 'offer.pdf')->id]);

            return $mail;
        });

        $migration->down();

        $this->assertFalse(Schema::hasColumn('epesi_mail_attachments', 'stored_file_id'));
        $paths = DB::table('epesi_mail_attachments')->pluck('path');
        $this->assertCount(2, $paths);
        $this->assertStringStartsWith('mail/'.$mails[0]->id.'/', $paths[0]);
        $this->assertSame('%PDF offer', Storage::disk('local')->get($paths[1]));
        $this->assertSame(0, StoredFileContent::count());

        $migration->up();

        $this->assertFalse(Schema::hasColumn('epesi_mail_attachments', 'path'));
        $this->assertSame(1, StoredFileContent::count());
        $this->assertSame('%PDF offer', $mails[1]->attachments()->sole()->storedFile->read());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('local')->allDirectories(), 'mail/<id>/ directories removed');
    }
}
