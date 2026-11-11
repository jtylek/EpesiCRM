<?php

namespace Tests\Feature;

use App\Models\StoredFile;
use App\Models\StoredFileContent;
use App\Services\FileStorage;
use Closure;
use Epesi\Modules\MigrationWizard\Files\FileLedger;
use Epesi\Modules\MigrationWizard\Files\LegacyAreaCheck;
use Epesi\Modules\MigrationWizard\Files\MovingLegacyFileTransfer;
use FilesystemIterator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Tests\TestCase;

/**
 * The in-place migration's file transfer: files are MOVED into the file storage, a
 * ledger entry exists before the source is touched, the source goes only after the
 * destination hash verified, and a crash at any point leaves the file recoverable.
 */
class MigrationWizardMovingTransferTest extends TestCase
{
    use RefreshDatabase;

    private string $base;

    private string $data;

    private FileLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = storage_path('framework/testing/moving-'.bin2hex(random_bytes(6)));
        $this->data = $this->base.'/data';
        mkdir($this->data.'/CRM_Mail/attachments/1', 0775, true);
        mkdir($this->data.'/CRM_Mail/attachments/2', 0775, true);
        mkdir($this->data.'/Utils_FileStorage', 0775, true);
        $this->ledger = new FileLedger($this->base.'/state', $this->data);
    }

    protected function tearDown(): void
    {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            ($entry->isDir() && ! $entry->isLink()) ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->base);
        parent::tearDown();
    }

    private function transfer(?FileStorage $storage = null): MovingLegacyFileTransfer
    {
        return new MovingLegacyFileTransfer($this->ledger, $storage ?? new FileStorage);
    }

    private function place(string $relative, string $content): string
    {
        $path = $this->data.'/'.$relative;
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $content);

        return $relative;
    }

    private function store(string $relative, string $name = 'a.pdf', iterable $reuse = [], ?string $createdOn = '2025-03-01 08:55:00', ?MovingLegacyFileTransfer $transfer = null): ?StoredFile
    {
        return ($transfer ?? $this->transfer())->store($this->data, [$relative], $name, $reuse, 'mail', 1, $createdOn);
    }

    private function stored(string $content): string
    {
        return FileStorage::pathFor(hash('sha512', $content));
    }

    private function disks(): Closure
    {
        return static fn (string $disk) => Storage::disk($disk);
    }

    /** A ledger entry as an interrupted move leaves it. */
    private function movingEntry(string $relative, string $content): void
    {
        $this->ledger->record([
            'source' => $relative, 'size' => strlen($content), 'sha256' => hash('sha256', $content), 'sha512' => hash('sha512', $content),
            'disk' => FileStorage::DISK, 'destination' => $this->stored($content), 'owner_type' => 'mail', 'owner_id' => '1',
            'created_destination' => true, 'status' => FileLedger::MOVING, 'error' => null,
        ]);
    }

    public function test_a_file_is_moved_into_the_storage_after_its_ledger_entry_and_keeps_the_mail_date(): void
    {
        $rel = $this->place('CRM_Mail/attachments/1/aaa', 'offer');

        $file = $this->store($rel);

        self::assertInstanceOf(StoredFile::class, $file);
        self::assertFileDoesNotExist($this->data.'/'.$rel);
        self::assertSame('offer', Storage::disk(FileStorage::DISK)->get($this->stored('offer')));
        self::assertSame('offer', $file->read());
        self::assertSame('2025-03-01 08:55:00', $file->fresh()->created_at->format('Y-m-d H:i:s'));
        $entry = $this->ledger->find($rel);
        self::assertSame(FileLedger::MOVED, $entry['status']);
        self::assertSame(hash('sha256', 'offer'), $entry['sha256']);
        self::assertSame(hash('sha512', 'offer'), $entry['sha512']);
        self::assertSame($this->stored('offer'), $entry['destination']);
        self::assertTrue($entry['created_destination']);
        self::assertSame(['checked' => 1, 'problems' => [], 'absent' => []], $this->ledger->verify($this->disks(), $this->data));
    }

    public function test_the_ledger_entry_exists_before_the_source_is_touched_and_a_failed_store_leaves_the_source(): void
    {
        $rel = $this->place('CRM_Mail/attachments/1/aaa', 'offer');
        $storage = new class($this->ledger, $this->data, $rel) extends FileStorage
        {
            public ?string $seen = null;

            public bool $sourceThere = false;

            public function __construct(private FileLedger $ledger, private string $data, private string $rel) {}

            public function storeWith(string $hash, string $name, Closure $write): StoredFile
            {
                $this->seen = $this->ledger->find($this->rel)['status'] ?? null;
                $this->sourceThere = is_file($this->data.'/'.$this->rel);
                throw new RuntimeException('disk full');
            }
        };

        try {
            $this->store($rel, transfer: $this->transfer($storage));
            self::fail('the failure was swallowed');
        } catch (RuntimeException $e) {
            self::assertSame('disk full', $e->getMessage());
        }

        self::assertSame(FileLedger::MOVING, $storage->seen);
        self::assertTrue($storage->sourceThere);
        self::assertSame('offer', file_get_contents($this->data.'/'.$rel));
        self::assertSame(FileLedger::FAILED, $this->ledger->find($rel)['status']);
    }

    public function test_a_crash_after_the_rename_is_resolved_from_the_ledger_without_losing_the_file(): void
    {
        $rel = $this->place('CRM_Mail/attachments/1/aaa', 'offer');
        $storage = new class extends FileStorage
        {
            public function storeWith(string $hash, string $name, Closure $write): StoredFile
            {
                $write(FileStorage::pathFor($hash)); // the rename happens...
                throw new RuntimeException('killed'); // ...and the process dies before the row and the ledger update
            }
        };

        try {
            $this->store($rel, transfer: $this->transfer($storage));
            self::fail('expected the simulated crash');
        } catch (RuntimeException $e) {
            self::assertSame('killed', $e->getMessage());
        }
        self::assertFileDoesNotExist($this->data.'/'.$rel);
        self::assertSame(FileLedger::MOVING, $this->ledger->find($rel)['status']);
        self::assertSame('offer', Storage::disk(FileStorage::DISK)->get($this->stored('offer')));
        self::assertSame([['source' => $rel, 'problem' => 'incomplete_move']], $this->ledger->verify($this->disks(), $this->data)['problems']);

        // The resume finds the file on the storage side, verifies it and finishes.
        $file = $this->store($rel);
        self::assertSame('offer', $file->read());
        self::assertSame(FileLedger::MOVED, $this->ledger->find($rel)['status']);
        self::assertSame(1, StoredFileContent::count());
    }

    public function test_a_crash_after_the_copy_leaves_both_sides_and_the_resume_finishes_the_move(): void
    {
        $rel = $this->place('CRM_Mail/attachments/1/aaa', 'offer');
        Storage::disk(FileStorage::DISK)->put($this->stored('offer'), 'offer');
        $this->movingEntry($rel, 'offer');

        $this->store($rel);

        self::assertFileDoesNotExist($this->data.'/'.$rel);
        $entry = $this->ledger->find($rel);
        self::assertSame(FileLedger::MOVED, $entry['status'], 'our own earlier copy is not a de-duplication');
        self::assertTrue($entry['created_destination']);
    }

    public function test_a_half_written_copy_of_an_interrupted_attempt_is_replaced_not_trusted(): void
    {
        $rel = $this->place('CRM_Mail/attachments/1/aaa', 'offer');
        Storage::disk(FileStorage::DISK)->put($this->stored('offer'), 'off'); // truncated
        $this->movingEntry($rel, 'offer');

        $file = $this->store($rel);

        self::assertSame('offer', $file->read());
        self::assertFileDoesNotExist($this->data.'/'.$rel);
        self::assertSame(FileLedger::MOVED, $this->ledger->find($rel)['status']);
    }

    public function test_content_already_stored_is_verified_and_then_the_source_is_deleted(): void
    {
        $rel = $this->place('CRM_Mail/attachments/1/aaa', 'offer');
        app(FileStorage::class)->put('offer', 'earlier.pdf');

        $file = $this->store($rel);

        self::assertSame('offer', $file->read());
        self::assertFileDoesNotExist($this->data.'/'.$rel);
        $entry = $this->ledger->find($rel);
        self::assertSame(FileLedger::DEDUPLICATED, $entry['status']);
        self::assertFalse($entry['created_destination']);
        self::assertSame(1, StoredFileContent::count());
        self::assertSame(2, StoredFile::count());
    }

    public function test_a_damaged_stored_copy_never_costs_the_source(): void
    {
        $rel = $this->place('CRM_Mail/attachments/1/aaa', 'offer');
        app(FileStorage::class)->put('offer', 'earlier.pdf');
        Storage::disk(FileStorage::DISK)->put($this->stored('offer'), 'damaged!');

        try {
            $this->store($rel);
            self::fail('a damaged stored copy was accepted as a duplicate');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('does not match its source hash', $e->getMessage());
        }

        self::assertSame('offer', file_get_contents($this->data.'/'.$rel));
        self::assertSame(FileLedger::FAILED, $this->ledger->find($rel)['status']);
        self::assertSame('damaged!', Storage::disk(FileStorage::DISK)->get($this->stored('offer')), 'a file we did not create is left alone');
    }

    public function test_a_missing_source_is_recorded_and_reported_as_absent_without_blocking(): void
    {
        self::assertNull($this->store('CRM_Mail/attachments/1/gone'));

        $entry = $this->ledger->find('CRM_Mail/attachments/1/gone');
        self::assertSame(FileLedger::MISSING, $entry['status']);
        $verified = $this->ledger->verify($this->disks(), $this->data);
        self::assertSame([], $verified['problems'], 'a file the legacy never had is not a problem the administrator could fix');
        self::assertSame(['CRM_Mail/attachments/1/gone'], $verified['absent']);
    }

    public function test_the_first_available_candidate_wins(): void
    {
        $this->place('CRM_Mail/attachments/1/aaa', 'old layout');
        $file = $this->transfer()->store($this->data, ['Utils_FileStorage/a/b/c', 'CRM_Mail/attachments/1/aaa'], 'x.txt', [], 'mail', 1);

        self::assertSame('old layout', $file->read());
        self::assertNull($this->ledger->find('Utils_FileStorage/a/b/c'), 'a candidate that is not there leaves no entry when another one served');
        self::assertSame(FileLedger::MOVED, $this->ledger->find('CRM_Mail/attachments/1/aaa')['status']);
    }

    public function test_a_source_used_twice_resolves_from_the_ledger_and_reuses_the_stored_use(): void
    {
        $rel = $this->place('Utils_FileStorage/a/b/shared', 'shared');
        $first = $this->store($rel, 'note.txt');

        $again = $this->store($rel, 'note.txt', [$first]);
        self::assertSame($first->getKey(), $again->getKey());

        $other = $this->store($rel, 'other name.txt', [$first]);
        self::assertNotSame($first->getKey(), $other->getKey());
        self::assertSame('shared', $other->read());
        self::assertSame(1, StoredFileContent::count());
        self::assertSame(FileLedger::MOVED, $this->ledger->find($rel)['status']);
    }

    public function test_a_gone_source_whose_stored_copy_is_gone_too_is_reported_missing(): void
    {
        $rel = $this->place('CRM_Mail/attachments/1/aaa', 'offer');
        $this->store($rel);
        Storage::disk(FileStorage::DISK)->delete($this->stored('offer'));

        self::assertNull($this->store($rel));
        self::assertSame(FileLedger::MISSING, $this->ledger->find($rel)['status']);
    }

    public function test_links_and_paths_outside_the_data_root_are_refused(): void
    {
        file_put_contents($this->base.'/outside.txt', 'secret');
        mkdir($this->base.'/elsewhere/attachments/9', 0775, true);
        file_put_contents($this->base.'/elsewhere/attachments/9/f', 'secret too');
        if (! @symlink($this->base.'/outside.txt', $this->data.'/CRM_Mail/attachments/1/link')
            || ! @symlink($this->base.'/elsewhere', $this->data.'/Linked')) {
            $this->markTestSkipped('Symbolic links cannot be created here.');
        }

        try {
            self::assertNull($this->store('CRM_Mail/attachments/1/link'));
            self::assertNull($this->store('Linked/attachments/9/f'));

            self::assertSame(FileLedger::UNREADABLE, $this->ledger->find('CRM_Mail/attachments/1/link')['status']);
            self::assertSame(FileLedger::UNREADABLE, $this->ledger->find('Linked/attachments/9/f')['status']);
            self::assertSame('secret', file_get_contents($this->base.'/outside.txt'));
            self::assertSame('secret too', file_get_contents($this->base.'/elsewhere/attachments/9/f'));
            self::assertSame(0, StoredFile::count());
        } finally {
            @unlink($this->data.'/CRM_Mail/attachments/1/link');
            @unlink($this->data.'/Linked');
        }

        foreach (['../outside.txt', '/etc/passwd', 'CRM_Mail/../../x'] as $bad) {
            try {
                $this->store($bad);
                self::fail("accepted {$bad}");
            } catch (RuntimeException $e) {
                self::assertSame('Invalid legacy file path.', $e->getMessage());
            }
        }
    }

    public function test_the_area_check_lists_unreferenced_files_and_the_moved_one_is_gone(): void
    {
        $this->place('Utils_FileStorage/a/b/used', 'used');
        $this->place('Utils_FileStorage/a/b/orphan', 'nobody references me');
        self::assertNotNull($this->store('Utils_FileStorage/a/b/used'));

        $check = new LegacyAreaCheck;
        $areas = $check->migratedAreas($this->data, ['attachments'], $this->ledger->verify($this->disks(), $this->data));
        self::assertSame(['data/Utils_FileStorage'], $areas);
        self::assertSame(1, $check->leftovers($this->data, $areas)['data/Utils_FileStorage']['count']);

        self::assertFileDoesNotExist($this->data.'/Utils_FileStorage/a/b/used');
        self::assertSame('nobody references me', file_get_contents($this->data.'/Utils_FileStorage/a/b/orphan'));
    }
}
