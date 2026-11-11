<?php

use Epesi\Modules\MigrationWizard\Import\ImportCheckpoint;
use PHPUnit\Framework\TestCase;

final class MigrationWizardImportCheckpointTest extends TestCase
{
    public function test_checkpoint_advances_monotonically(): void
    {
        $hash = str_repeat('a', 64);
        $service = new ImportCheckpoint;
        $first = $service->advance([], 'contacts', 10, 10, $hash);
        $second = $service->advance($first, 'contacts', 20, 19, $hash);
        self::assertSame(20, $second['last_source_id']);
        self::assertSame(19, $second['processed']);
    }

    public function test_checkpoint_cannot_go_backwards(): void
    {
        $hash = str_repeat('a', 64);
        $service = new ImportCheckpoint;
        $this->expectException(\RuntimeException::class);
        $service->advance($service->advance([], 'contacts', 10, 10, $hash), 'contacts', 9, 11, $hash);
    }

    public function test_schema_change_invalidates_checkpoint(): void
    {
        $service = new ImportCheckpoint;
        $first = $service->advance([], 'contacts', 10, 10, str_repeat('a', 64));
        $this->expectException(\RuntimeException::class);
        $service->advance($first, 'contacts', 20, 20, str_repeat('b', 64));
    }

    public function test_checkpoint_cannot_switch_importer(): void
    {
        $service = new ImportCheckpoint;
        $hash = str_repeat('a', 64);
        $first = $service->advance([], 'contacts', 10, 10, $hash);
        $this->expectException(\RuntimeException::class);
        $service->advance($first, 'users', 20, 20, $hash);
    }
}
