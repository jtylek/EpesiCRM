<?php

use Epesi\Modules\MigrationWizard\Import\MariaDbCheckpointStore;
use PHPUnit\Framework\TestCase;

final class MigrationWizardMariaDbCheckpointStoreTest extends TestCase
{
    public function test_rejects_non_mysql_connection_before_creating_schema(): void
    {
        $this->expectException(\RuntimeException::class);
        (new MariaDbCheckpointStore)->initialize(new PDO('sqlite::memory:'));
    }

    public function test_rejects_checkpoint_write_outside_transaction(): void
    {
        $this->expectException(\RuntimeException::class);
        (new MariaDbCheckpointStore)->save(new PDO('sqlite::memory:'), [
            'importer_id' => 'contacts', 'schema_fingerprint' => str_repeat('a', 64),
            'last_source_id' => 1, 'processed' => 1,
        ]);
    }
}
