<?php

use Epesi\Modules\MigrationWizard\Import\FixtureCheckpointStore;
use PHPUnit\Framework\TestCase;

final class MigrationWizardFixtureCheckpointStoreTest extends TestCase
{
    public function test_checkpoint_is_rolled_back_with_data(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $store = new FixtureCheckpointStore;
        $store->initialize($pdo);
        $pdo->exec('CREATE TABLE fixture_contacts_imported (legacy_id INTEGER UNIQUE, display_name TEXT)');
        $pdo->beginTransaction();
        $pdo->exec("INSERT INTO fixture_contacts_imported VALUES (1, 'A')");
        $store->save($pdo, ['importer_id' => 'fixture_contacts', 'schema_fingerprint' => str_repeat('f', 64),
            'last_source_id' => 1, 'processed' => 1]);
        $pdo->rollBack();
        self::assertSame([], $store->read($pdo, 'fixture_contacts'));
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM fixture_contacts_imported')->fetchColumn());
    }

    public function test_checkpoint_cannot_be_saved_outside_transaction(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new FixtureCheckpointStore;
        $store->initialize($pdo);
        $this->expectException(\RuntimeException::class);
        $store->save($pdo, ['importer_id' => 'fixture_contacts', 'schema_fingerprint' => str_repeat('f', 64),
            'last_source_id' => 1, 'processed' => 1]);
    }
}
