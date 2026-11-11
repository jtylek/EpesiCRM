<?php

use Epesi\Modules\MigrationWizard\Files\FileLedger;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MigrationWizardFileLedgerTest extends TestCase
{
    private string $root;

    private string $ledgerDir;

    protected function setUp(): void
    {
        parent::setUp();
        $base = sys_get_temp_dir().'/epesi-ledger-'.bin2hex(random_bytes(6));
        $this->root = $base.'/data';
        $this->ledgerDir = $base.'/state';
        mkdir($this->root.'/CRM_Contacts_Photo', 0700, true);
        file_put_contents($this->root.'/CRM_Contacts_Photo/a.jpg', 'photo-a');
        Storage::fake('ledger-test');
    }

    protected function tearDown(): void
    {
        $base = dirname($this->root);
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            ($entry->isDir() && ! $entry->isLink()) ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($base);
        parent::tearDown();
    }

    private function import(FileLedger $ledger, string $source = 'CRM_Contacts_Photo/a.jpg', string $destination = 'contacts/1/a.jpg'): array
    {
        return $ledger->import($this->root, $source, Storage::disk('ledger-test'), 'ledger-test', $destination, 'contact', 1);
    }

    private function disks(): Closure
    {
        return static fn (string $name) => Storage::disk($name);
    }

    public function test_copy_is_recorded_with_hash_destination_and_owner(): void
    {
        $ledger = new FileLedger($this->ledgerDir);
        $entry = $this->import($ledger);
        self::assertSame(FileLedger::COPIED, $entry['status']);
        self::assertSame(hash('sha256', 'photo-a'), $entry['sha256']);
        self::assertSame(7, $entry['size']);
        self::assertSame(['contact', '1'], [$entry['owner_type'], $entry['owner_id']]);
        self::assertSame('photo-a', Storage::disk('ledger-test')->get('contacts/1/a.jpg'));
        self::assertSame(['checked' => 1, 'problems' => [], 'absent' => []], $ledger->verify($this->disks(), $this->root));
    }

    public function test_importing_again_is_idempotent_and_survives_a_new_ledger_instance(): void
    {
        $this->import(new FileLedger($this->ledgerDir));
        $second = new FileLedger($this->ledgerDir);
        $this->import($second);
        self::assertCount(1, $second->entries());
        self::assertCount(1, file($this->ledgerDir.'/file-ledger.jsonl'));
    }

    public function test_a_missing_and_an_unsafe_source_are_both_recorded_but_only_the_unsafe_one_is_a_problem(): void
    {
        $ledger = new FileLedger($this->ledgerDir);
        self::assertSame(FileLedger::MISSING, $this->import($ledger, 'CRM_Contacts_Photo/gone.jpg', 'x/gone.jpg')['status']);

        file_put_contents(dirname($this->root).'/outside.txt', 'secret');
        if (@symlink(dirname($this->root).'/outside.txt', $this->root.'/link.txt')) {
            self::assertSame(FileLedger::UNREADABLE, $this->import($ledger, 'link.txt', 'x/link.txt')['status']);
            self::assertFalse(Storage::disk('ledger-test')->exists('x/link.txt'));
        }
        $verified = $ledger->verify($this->disks());
        self::assertSame(['CRM_Contacts_Photo/gone.jpg'], $verified['absent'], 'a file the legacy never had is reported, not a problem');
        $problems = array_column($verified['problems'], 'problem', 'source');
        self::assertArrayNotHasKey('CRM_Contacts_Photo/gone.jpg', $problems);
    }

    public function test_path_traversal_and_destination_collisions_are_rejected(): void
    {
        $ledger = new FileLedger($this->ledgerDir);
        foreach (['../outside.txt', '/etc/passwd', 'C:/x', 'a/../../b', ''] as $bad) {
            try {
                $this->import($ledger, $bad);
                self::fail("accepted {$bad}");
            } catch (RuntimeException $e) {
                self::assertSame('Invalid legacy file path.', $e->getMessage());
            }
        }
        $this->import($ledger);
        file_put_contents($this->root.'/CRM_Contacts_Photo/b.jpg', 'photo-b');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already recorded at this destination');
        $this->import($ledger, 'CRM_Contacts_Photo/b.jpg', 'contacts/1/a.jpg');
    }

    public function test_verify_reports_changed_source_and_damaged_or_missing_destination(): void
    {
        $ledger = new FileLedger($this->ledgerDir);
        $this->import($ledger);
        Storage::disk('ledger-test')->put('contacts/1/a.jpg', 'tampered');
        file_put_contents($this->root.'/CRM_Contacts_Photo/a.jpg', 'changed');
        $problems = array_column($ledger->verify($this->disks(), $this->root)['problems'], 'problem');
        self::assertContains('source_changed', $problems);
        self::assertContains('destination_hash_mismatch', $problems);

        Storage::disk('ledger-test')->delete('contacts/1/a.jpg');
        self::assertContains('destination_missing', array_column($ledger->verify($this->disks())['problems'], 'problem'));
    }

    public function test_a_corrupt_ledger_fails_closed(): void
    {
        mkdir($this->ledgerDir, 0700, true);
        file_put_contents($this->ledgerDir.'/file-ledger.jsonl', "{not json\n");
        $this->expectException(RuntimeException::class);
        (new FileLedger($this->ledgerDir))->entries();
    }

    /** A moved file's ledger entry, as MovingLegacyFileTransfer writes it. */
    private function moveEntry(FileLedger $ledger, string $source, string $content, string $status, string $destination = 'moved/x', bool $created = true, bool $storeIt = true): array
    {
        if ($storeIt) {
            Storage::disk('ledger-test')->put($destination, $content);
        }

        return $ledger->record([
            'source' => $source, 'size' => strlen($content), 'sha256' => hash('sha256', $content), 'sha512' => hash('sha512', $content),
            'disk' => 'ledger-test', 'destination' => $destination, 'owner_type' => 'mail', 'owner_id' => '1',
            'created_destination' => $created, 'status' => $status, 'error' => null,
        ]);
    }

    public function test_moved_and_deduplicated_entries_verify_without_a_source_and_a_moving_one_is_incomplete(): void
    {
        $ledger = new FileLedger($this->ledgerDir, $this->root);
        $this->moveEntry($ledger, 'CRM_Mail/attachments/1/a', 'same', FileLedger::MOVED);
        $this->moveEntry($ledger, 'CRM_Mail/attachments/2/a', 'same', FileLedger::DEDUPLICATED, created: false, storeIt: false);

        self::assertSame(['checked' => 2, 'problems' => [], 'absent' => []], $ledger->verify($this->disks(), $this->root));

        $this->moveEntry($ledger, 'CRM_Mail/attachments/3/a', 'other', FileLedger::MOVING, 'moved/y');
        $problems = $ledger->verify($this->disks(), $this->root)['problems'];
        self::assertSame([['source' => 'CRM_Mail/attachments/3/a', 'problem' => 'incomplete_move']], $problems);

        Storage::disk('ledger-test')->put('moved/x', 'damaged');
        self::assertContains('destination_hash_mismatch', array_column($ledger->verify($this->disks(), $this->root)['problems'], 'problem'));
    }

    public function test_the_ledger_has_no_way_to_move_files_back(): void
    {
        self::assertFalse(method_exists(FileLedger::class, 'remove'));
        self::assertFalse(defined(FileLedger::class.'::REMOVED'));
    }
}
