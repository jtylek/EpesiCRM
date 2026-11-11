<?php

use Epesi\Modules\MigrationWizard\Import\FixtureImportRehearsal;
use PHPUnit\Framework\TestCase;

final class MigrationWizardFixtureImportRehearsalTest extends TestCase
{
    public function test_synthetic_import_is_batched_and_idempotent(): void
    {
        $source = new PDO('sqlite::memory:');
        $target = new PDO('sqlite::memory:');
        foreach ([$source, $target] as $pdo) {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        $source->exec('CREATE TABLE fixture_contacts (id INTEGER PRIMARY KEY, name TEXT)');
        $source->exec("INSERT INTO fixture_contacts VALUES (1, 'A'), (3, 'B'), (8, 'C')");
        $target->exec('CREATE TABLE fixture_contacts_imported (legacy_id INTEGER UNIQUE, display_name TEXT)');
        $service = new FixtureImportRehearsal;
        $mapping = ['source_table' => 'fixture_contacts', 'target_table' => 'fixture_contacts_imported', 'id_column' => 'id'];
        $result = $service->run($source, $target, $mapping, 2);
        self::assertSame(3, $result['processed']);
        self::assertSame(8, $result['checkpoint']['last_source_id']);
        $again = $service->run($source, $target, $mapping, 2);
        self::assertSame(3, $again['processed']);
        self::assertSame(8, $again['checkpoint']['last_source_id']);
        self::assertSame(3, (int) $target->query('SELECT COUNT(*) FROM fixture_contacts_imported')->fetchColumn());
        self::assertSame(3, (int) $target->query('SELECT processed FROM fixture_migration_checkpoint')->fetchColumn());
    }

    public function test_production_table_names_are_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        (new FixtureImportRehearsal)->run(new PDO('sqlite::memory:'), new PDO('sqlite::memory:'),
            ['source_table' => 'contact_data_1', 'target_table' => 'contacts', 'id_column' => 'id']);
    }
}
