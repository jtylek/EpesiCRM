<?php

namespace Tests\Feature;

use App\Support\Setup\EnvFile;
use Epesi\Modules\MigrationWizard\Detection\LegacyDatabaseConnection;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

/**
 * On a legacy site that is not Epesi 3 yet, the application database is the one in data/config.php (the
 * default .env names a SQLite file that does not exist). Configuration only: nothing here opens a connection.
 */
class MigrationWizardLegacyConnectionTest extends TestCase
{
    protected string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = storage_path('framework/testing/legacy-connection-'.uniqid());
        File::ensureDirectoryExists($this->base.'/data');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);

        parent::tearDown();
    }

    protected function writeConfig(string $extra = ''): void
    {
        File::put($this->base.'/data/config.php', "<?php\ndefine('DATABASE_HOST','127.0.0.1');\ndefine('DATABASE_PORT','3399');\ndefine('DATABASE_USER','legacy_user');\ndefine('DATABASE_PASSWORD','p#ss word');\ndefine('DATABASE_NAME','legacy_db');\n".$extra);
    }

    public function test_a_sqlite_default_is_replaced_by_the_legacy_database(): void
    {
        $this->writeConfig();
        config(['database.default' => 'sqlite']);

        $this->assertTrue(LegacyDatabaseConnection::apply($this->base));

        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('127.0.0.1', config('database.connections.mysql.host'));
        $this->assertSame('3399', config('database.connections.mysql.port'));
        $this->assertSame('legacy_db', config('database.connections.mysql.database'));
        $this->assertSame('legacy_user', config('database.connections.mysql.username'));
        $this->assertSame('p#ss word', config('database.connections.mysql.password'));
        $this->assertSame('legacy_db', config(LegacyDatabaseConnection::APPLIED));
        $this->assertTrue(LegacyDatabaseConnection::apply($this->base), 'applying twice is harmless');
    }

    public function test_an_explicitly_configured_server_connection_is_left_alone(): void
    {
        $this->writeConfig();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'chosen']);

        $this->assertFalse(LegacyDatabaseConnection::apply($this->base));

        $this->assertSame('chosen', config('database.connections.mysql.database'));
        $this->assertNull(config(LegacyDatabaseConnection::APPLIED));
    }

    public function test_nothing_changes_without_a_readable_legacy_config(): void
    {
        config(['database.default' => 'sqlite']);

        $this->assertFalse(LegacyDatabaseConnection::apply($this->base));
        $this->assertSame('sqlite', config('database.default'));
    }

    public function test_an_unsupported_legacy_driver_is_not_applied(): void
    {
        $this->writeConfig("define('DATABASE_DRIVER','postgres');\n");
        config(['database.default' => 'sqlite']);

        $this->assertFalse(LegacyDatabaseConnection::apply($this->base));
        $this->assertSame('sqlite', config('database.default'));
    }

    public function test_persist_writes_the_legacy_database_to_env_only_while_the_override_is_in_force(): void
    {
        $path = $this->base.'/.env';
        File::put($path, "APP_ENV=production\nDB_CONNECTION=sqlite\n");

        config(['database.default' => 'sqlite']);
        LegacyDatabaseConnection::persist(new EnvFile($path));
        $this->assertStringContainsString('DB_CONNECTION=sqlite', File::get($path), 'the test fake / a configured site keeps its .env');

        $this->writeConfig();
        LegacyDatabaseConnection::apply($this->base);
        LegacyDatabaseConnection::persist(new EnvFile($path));
        LegacyDatabaseConnection::persist(new EnvFile($path)); // idempotent

        $env = File::get($path);
        $this->assertStringContainsString('APP_ENV=production', $env);
        $this->assertStringContainsString('DB_CONNECTION=mysql', $env);
        $this->assertStringContainsString('DB_HOST=127.0.0.1', $env);
        $this->assertStringContainsString('DB_PORT=3399', $env);
        $this->assertStringContainsString('DB_DATABASE=legacy_db', $env);
        $this->assertStringContainsString('DB_USERNAME=legacy_user', $env);
        $this->assertStringContainsString('DB_PASSWORD="p#ss word"', $env);
        $this->assertSame(1, substr_count($env, 'DB_DATABASE='));
        $this->assertStringNotContainsString('sqlite', $env);
    }

    public function test_an_unwritable_env_is_refused_before_anything_changes(): void
    {
        $this->writeConfig();
        config(['database.default' => 'sqlite']);
        LegacyDatabaseConnection::apply($this->base);

        $this->expectException(RuntimeException::class);
        LegacyDatabaseConnection::assertPersistable(new EnvFile($this->base.'/missing-dir/.env'));
    }

    public function test_the_writable_check_is_a_no_op_when_the_override_is_not_in_force(): void
    {
        LegacyDatabaseConnection::assertPersistable(new EnvFile($this->base.'/missing-dir/.env'));

        $this->assertNull(config(LegacyDatabaseConnection::APPLIED));
    }
}
