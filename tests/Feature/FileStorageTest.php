<?php

namespace Tests\Feature;

use App\Models\StoredFile;
use App\Models\StoredFileContent;
use App\Services\FileStorage;
use Epesi\Modules\Attachments\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The deduplicating file storage — Epesi's Utils/FileStorage.
 */
class FileStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_same_content_is_kept_once_under_epesis_layout(): void
    {
        $storage = app(FileStorage::class);

        $offer = $storage->put('%PDF offer', 'offer.pdf');
        $copy = $storage->putUpload(UploadedFile::fake()->createWithContent('Oferta.pdf', '%PDF offer'));
        $other = $storage->put('Call Ann back', 'notes.txt');

        $this->assertSame($offer->content_id, $copy->content_id);
        $this->assertNotSame($offer->content_id, $other->content_id);
        $this->assertSame(['offer.pdf', 'Oferta.pdf'], [$offer->name, $copy->name]);
        $this->assertSame(3, StoredFile::count());
        $this->assertSame(2, StoredFileContent::count());

        // sha512, the first five hex digits a directory each: Epesi's
        // data/Utils_FileStorage layout.
        $hash = hash('sha512', '%PDF offer');
        $this->assertSame($hash, $offer->content->hash);
        Storage::disk(FileStorage::DISK)->assertExists(implode('/', str_split(substr($hash, 0, 5))).'/'.substr($hash, 5));
        $this->assertCount(2, Storage::disk(FileStorage::DISK)->allFiles());

        $this->assertSame('%PDF offer', $copy->read());
        $this->assertSame(10, $copy->size());

        // Plain text: the bytes can't tell, the name can.
        $this->assertSame('text/plain', $other->mimeType());
    }

    public function test_a_content_goes_with_its_last_file(): void
    {
        $storage = app(FileStorage::class);
        $one = $storage->put('%PDF offer', 'offer.pdf');
        $two = $one->copy('offer (copy).pdf');
        $path = $one->content->path();

        $one->delete();

        Storage::disk(FileStorage::DISK)->assertExists($path);
        $this->assertSame('%PDF offer', $two->read());

        $two->delete();

        Storage::disk(FileStorage::DISK)->assertMissing($path);
        $this->assertSame(0, StoredFileContent::count());
        $this->assertSame([], Storage::disk(FileStorage::DISK)->allDirectories(), 'no empty directories left behind');
    }

    public function test_a_content_whose_file_went_missing_is_written_again(): void
    {
        $storage = app(FileStorage::class);
        $first = $storage->put('%PDF offer', 'offer.pdf');
        Storage::disk(FileStorage::DISK)->delete($first->content->path());

        $second = $storage->put('%PDF offer', 'offer.pdf');

        $this->assertSame($first->content_id, $second->content_id);
        $this->assertTrue($first->isOnDisk());
    }

    public function test_putting_a_file_again_reuses_the_use_that_already_holds_it(): void
    {
        $storage = app(FileStorage::class);
        $path = tempnam(sys_get_temp_dir(), 'fs');
        file_put_contents($path, '%PDF offer');

        $first = $storage->putFile($path, 'offer.pdf');
        $again = $storage->putFile($path, 'offer.pdf', [$first]);
        $renamed = $storage->putFile($path, 'other.pdf', [$first]);

        $this->assertSame($first->getKey(), $again->getKey());
        $this->assertNotSame($first->getKey(), $renamed->getKey());
        $this->assertSame(2, StoredFile::count());

        unlink($path);
    }

    public function test_prune_deletes_only_old_unreferenced_files(): void
    {
        $storage = app(FileStorage::class);
        $held = $storage->put('held', 'held.txt');
        $orphan = $storage->put('orphan', 'orphan.txt');
        $young = $storage->put('young', 'young.txt');

        Attachment::create(['title' => 'Note', 'files' => [(string) $held->getKey()]]);
        StoredFile::query()->whereKey([$held->getKey(), $orphan->getKey()])->update(['created_at' => now()->subDays(2)]);

        $this->artisan('filestorage:prune')->assertSuccessful();
        $this->assertSame(3, StoredFile::count());

        $this->artisan('filestorage:prune', ['--force' => true])->assertSuccessful();

        $this->assertNotNull(StoredFile::find($held->getKey()));
        $this->assertNotNull(StoredFile::find($young->getKey()));
        $this->assertNull(StoredFile::find($orphan->getKey()));
        $this->assertSame(2, StoredFileContent::count());
    }
}
