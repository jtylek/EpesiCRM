<?php

use Epesi\Modules\MigrationWizard\Import\LegacyBatchReader;
use PHPUnit\Framework\TestCase;

final class MigrationWizardLegacyBatchReaderTest extends TestCase
{
    public function test_batches_are_bounded_ordered_and_resumable(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE legacy_contacts (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO legacy_contacts VALUES (1, 'One'), (3, 'Three'), (7, 'Seven')");
        $reader = new LegacyBatchReader;
        $first = $reader->read($pdo, 'legacy_contacts', 'id', 0, 2, ['id', 'name']);
        self::assertSame([1, 3], array_map('intval', array_column($first['rows'], 'id')));
        self::assertSame(3, $first['last_source_id']);
        self::assertTrue($first['has_more']);
        $second = $reader->read($pdo, 'legacy_contacts', 'id', 3, 2, ['id', 'name']);
        self::assertSame([7], array_map('intval', array_column($second['rows'], 'id')));
        self::assertFalse($second['has_more']);
    }

    public function test_unsafe_identifiers_are_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        (new LegacyBatchReader)->read(new PDO('sqlite::memory:'), 'users; DROP TABLE users', 'id', 0, 10, ['id']);
    }

    public function test_limit_must_be_bounded(): void
    {
        $this->expectException(\RuntimeException::class);
        (new LegacyBatchReader)->read(new PDO('sqlite::memory:'), 'users', 'id', 0, 1001, ['id']);
    }
}
