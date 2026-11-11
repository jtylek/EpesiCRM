<?php

use Epesi\Modules\MigrationWizard\Checks\DiskSpaceEstimator;
use Epesi\Modules\MigrationWizard\Detection\LegacyInventory;
use Epesi\Modules\MigrationWizard\Files\LegacyAreaCheck;
use Epesi\Modules\MigrationWizard\Import\InPlaceLegacyImport;
use PHPUnit\Framework\TestCase;

final class MigrationWizardFileAreasTest extends TestCase
{
    private string $base;

    private string $data;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/epesi-areas-'.bin2hex(random_bytes(6));
        $this->data = $this->base.'/data';
        mkdir($this->data.'/CRM_Mail/attachments/1', 0775, true);
        mkdir($this->data.'/Utils_FileStorage/a/b', 0775, true);
        mkdir($this->data.'/Other', 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            ($entry->isDir() && ! $entry->isLink()) ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->base);
        parent::tearDown();
    }

    private const CLEAN = ['checked' => 2, 'problems' => []];

    private const DONE = ['mail', 'attachments'];

    public function test_an_area_is_migrated_when_its_importer_completed_and_the_ledger_is_clean(): void
    {
        $check = new LegacyAreaCheck;

        self::assertSame(['data/CRM_Mail', 'data/Utils_FileStorage'], $check->migratedAreas($this->data, self::DONE, self::CLEAN));
        self::assertSame(['data/CRM_Mail'], $check->migratedAreas($this->data, ['mail'], self::CLEAN), 'the notes importer did not complete');
        self::assertSame([], $check->migratedAreas($this->data, [], self::CLEAN), 'no importer completed: nothing counts as migrated');
        self::assertSame([], $check->migratedAreas($this->data, self::DONE, null), 'no ledger, nothing is migrated');
        self::assertSame([], $check->migratedAreas($this->data, self::DONE, ['checked' => 1, 'problems' => [['source' => 'x', 'problem' => 'missing']]]));
    }

    public function test_files_nobody_references_do_not_stop_an_area_and_are_listed_with_count_and_size(): void
    {
        file_put_contents($this->data.'/Utils_FileStorage/a/b/orphan', 'xyz');
        file_put_contents($this->data.'/Utils_FileStorage/loose', 'ab');
        file_put_contents($this->data.'/CRM_Mail/encryption.key', 'key');
        $check = new LegacyAreaCheck;

        $areas = $check->migratedAreas($this->data, self::DONE, self::CLEAN);
        self::assertSame(['data/CRM_Mail', 'data/Utils_FileStorage'], $areas);
        $left = $check->leftovers($this->data, $areas);
        self::assertSame(['data/Utils_FileStorage'], array_keys($left), 'the mail key is by design, not a leftover');
        self::assertSame(2, $left['data/Utils_FileStorage']['count']);
        self::assertSame(5, $left['data/Utils_FileStorage']['bytes']);
        self::assertSame(['data/Utils_FileStorage/a/b/orphan', 'data/Utils_FileStorage/loose'], $check->listRemaining($this->data, 'data/Utils_FileStorage'));
    }

    public function test_a_link_is_listed_as_left_and_an_area_that_is_a_link_is_not_migrated(): void
    {
        file_put_contents($this->base.'/outside', 'x');
        if (! @symlink($this->base.'/outside', $this->data.'/CRM_Mail/attachments/1/link')) {
            self::markTestSkipped('Symbolic links cannot be created here.');
        }
        $check = new LegacyAreaCheck;
        self::assertSame(1, $check->leftovers($this->data, ['data/CRM_Mail'])['data/CRM_Mail']['count']);
        unlink($this->data.'/CRM_Mail/attachments/1/link');

        rmdir($this->data.'/Utils_FileStorage/a/b');
        rmdir($this->data.'/Utils_FileStorage/a');
        rmdir($this->data.'/Utils_FileStorage');
        symlink($this->base, $this->data.'/Utils_FileStorage');
        self::assertSame(['data/CRM_Mail'], $check->migratedAreas($this->data, self::DONE, self::CLEAN));
        unlink($this->data.'/Utils_FileStorage');
        mkdir($this->data.'/Utils_FileStorage');
    }

    public function test_only_the_mail_key_stays_by_design(): void
    {
        self::assertTrue(LegacyAreaCheck::staysByDesign('data/CRM_Mail/encryption.key'));
        self::assertFalse(LegacyAreaCheck::staysByDesign('data/CRM_Mail/attachments/1/a'));
        self::assertFalse(LegacyAreaCheck::staysByDesign('data/Utils_FileStorage/encryption.key'));
    }

    public function test_the_disk_estimate_counts_copied_data_once_and_moved_data_as_its_largest_file(): void
    {
        file_put_contents($this->data.'/Other/a', str_repeat('a', 1000));
        file_put_contents($this->data.'/CRM_Mail/attachments/1/m1', str_repeat('m', 5000));
        file_put_contents($this->data.'/CRM_Mail/attachments/1/m2', str_repeat('m', 3000));
        file_put_contents($this->data.'/Utils_FileStorage/a/b/f', str_repeat('f', 4000));

        $report = (new DiskSpaceEstimator)->inspect($this->base);

        self::assertSame(13000, $report['source_bytes']);
        self::assertSame(1000, $report['copied_bytes']);
        self::assertSame(12000, $report['moved_bytes']);
        self::assertSame(5000, $report['largest_moved_file_bytes']);
        self::assertSame(1000 + 5000 + DiskSpaceEstimator::MARGIN_BYTES, $report['recommended_bytes']);
    }

    public function test_a_capped_inventory_still_lists_every_area_and_says_it_is_incomplete(): void
    {
        file_put_contents($this->data.'/CRM_Mail/attachments/1/a', 'x');
        file_put_contents($this->data.'/CRM_Mail/attachments/1/b', 'x');
        file_put_contents($this->data.'/Utils_FileStorage/a/b/f', 'x');

        [$areas, $incomplete] = (new LegacyInventory)->scanDataAreas($this->data, 1);

        self::assertTrue($incomplete);
        self::assertSame(['data/CRM_Mail', 'data/Other', 'data/Utils_FileStorage'], array_keys($areas));
        self::assertSame(1, $areas['data/Utils_FileStorage']['count'], 'an area past the cap is listed as non-empty');
        self::assertSame(0, $areas['data/Other']['count'], 'an empty one stays empty');
    }

    public function test_the_file_importers_need_a_ledger_to_be_approved(): void
    {
        self::assertSame(['Base_User', 'CRM_Contacts'], InPlaceLegacyImport::withoutFileModules(['Base_User', 'CRM_Mail', 'CRM_Contacts', 'Utils_Attachment', 'Utils_FileStorage']));
    }
}
