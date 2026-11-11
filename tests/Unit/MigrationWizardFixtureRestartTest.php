<?php

use Epesi\Modules\MigrationWizard\Import\FixtureImportRehearsal;
use Epesi\Modules\MigrationWizard\Import\FixtureCheckpointStore;
use PHPUnit\Framework\TestCase;

final class MigrationWizardFixtureRestartTest extends TestCase
{
    public function test_restart_from_persisted_checkpoint_after_connection_reopen(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'epesi-fixture-');
        if ($file === false) {
            self::fail('Unable to allocate disposable fixture database.');
        }
        try {
            $source = new PDO('sqlite::memory:');
            $source->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $source->exec('CREATE TABLE fixture_contacts (id INTEGER PRIMARY KEY, name TEXT)');
            $source->exec("INSERT INTO fixture_contacts VALUES (1, 'A'), (3, 'B'), (8, 'C')");
            $target = new PDO('sqlite:'.$file);
            $target->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $target->exec('CREATE TABLE fixture_contacts_imported (legacy_id INTEGER UNIQUE, display_name TEXT)');
            $store = new FixtureCheckpointStore;
            $store->initialize($target);
            $target->beginTransaction();
            $target->exec("INSERT INTO fixture_contacts_imported VALUES (1, 'A')");
            $store->save($target, ['importer_id' => 'fixture_contacts',
                'schema_fingerprint' => str_repeat('f', 64), 'last_source_id' => 1, 'processed' => 1]);
            $target->commit();
            unset($target);
            $reopened = new PDO('sqlite:'.$file);
            $reopened->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $mapping = ['source_table' => 'fixture_contacts', 'target_table' => 'fixture_contacts_imported',
                'id_column' => 'id'];
            $result = (new FixtureImportRehearsal)->run($source, $reopened, $mapping, 1);
            self::assertSame(3, $result['processed']);
            self::assertSame(8, $result['checkpoint']['last_source_id']);
            self::assertSame(3, (int) $reopened->query('SELECT COUNT(*) FROM fixture_contacts_imported')->fetchColumn());
            self::assertSame(1, (int) $reopened->query('SELECT COUNT(*) FROM fixture_migration_checkpoint')->fetchColumn());
            self::assertSame(3, (int) $reopened->query('SELECT processed FROM fixture_migration_checkpoint')->fetchColumn());
        } finally {
            @unlink($file);
        }
    }
}
