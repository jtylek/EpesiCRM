<?php

use Epesi\Modules\MigrationWizard\Import\MariaDbCheckpointStore;
use PHPUnit\Framework\TestCase;

/**
 * Opt-in real MariaDB integration test.
 * Requires MIGRATION_TEST_MARIADB_DSN, _USER, _PASSWORD and a disposable schema.
 */
final class MigrationWizardMariaDbCheckpointIntegrationTest extends TestCase
{
    public function test_checkpoint_survives_reconnect_and_rolls_back_with_data(): void
    {
        $dsn = getenv('MIGRATION_TEST_MARIADB_DSN');
        if (! $dsn) {
            self::markTestSkipped('Disposable MariaDB DSN is not configured.');
        }
        $user = getenv('MIGRATION_TEST_MARIADB_USER') ?: '';
        $password = getenv('MIGRATION_TEST_MARIADB_PASSWORD') ?: '';
        $connect = static function () use ($dsn, $user, $password): PDO {
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
                throw new RuntimeException('Expected MySQL/MariaDB.');
            }
            return $pdo;
        };
        $pdo = $connect();
        $store = new MariaDbCheckpointStore;
        $store->initialize($pdo);
        $pdo->exec('CREATE TABLE IF NOT EXISTS epesi_migration_fixture_rows (
            legacy_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, display_name VARCHAR(100) NOT NULL
        ) ENGINE=InnoDB');
        $id = 'fixture_'.bin2hex(random_bytes(6));
        $rowId = random_int(100000000, 2000000000);
        $hash = str_repeat('a', 64);
        try {
            $pdo->beginTransaction();
            $insert = $pdo->prepare('INSERT INTO epesi_migration_fixture_rows (legacy_id, display_name)
                VALUES (:id, :name)');
            $insert->execute([':id' => $rowId, ':name' => 'committed']);
            $store->save($pdo, ['importer_id' => $id, 'schema_fingerprint' => $hash,
                'last_source_id' => $rowId, 'processed' => 1]);
            $pdo->commit();
            unset($pdo);
            $pdo = $connect();
            $saved = $store->read($pdo, $id);
            self::assertSame($rowId, (int) $saved['last_source_id']);
            self::assertSame(1, (int) $saved['processed']);

            $pdo->beginTransaction();
            $insert = $pdo->prepare('INSERT INTO epesi_migration_fixture_rows (legacy_id, display_name) VALUES (:id, :name)');
            $insert->execute([':id' => $rowId + 1, ':name' => 'rolled back']);
            $store->save($pdo, ['importer_id' => $id, 'schema_fingerprint' => $hash,
                'last_source_id' => $rowId + 1, 'processed' => 2]);
            $pdo->rollBack();
            self::assertSame($rowId, (int) $store->read($pdo, $id)['last_source_id']);
            $count = $pdo->prepare('SELECT COUNT(*) FROM epesi_migration_fixture_rows WHERE legacy_id = :id');
            $count->execute([':id' => $rowId + 1]);
            self::assertSame(0, (int) $count->fetchColumn());
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $deleteCheckpoint = $pdo->prepare('DELETE FROM epesi_migration_checkpoints WHERE importer_id = :id');
            $deleteCheckpoint->execute([':id' => $id]);
            $deleteRows = $pdo->prepare('DELETE FROM epesi_migration_fixture_rows WHERE legacy_id IN (:first, :second)');
            $deleteRows->execute([':first' => $rowId, ':second' => $rowId + 1]);
        }
    }
}
