<?php

namespace Epesi\Modules\MigrationWizard\Detection;

use App\Services\Setup\DatabaseSetup;
use App\Support\Setup\EnvFile;
use Illuminate\Container\Container;
use RuntimeException;
use Throwable;

/**
 * On a legacy site that is not yet Epesi 3 the application database is the LEGACY database named in
 * data/config.php: the migration renames the legacy tables to legacy_<name> in it and installs the Epesi 3
 * schema into the very same database. Administrators unpack the Epesi 3 files over the old folder
 * (Softaculous, cPanel) and never edit .env, whose auto-created defaults name a SQLite file that does not exist.
 *
 * apply() points the default connection (and the `mysql` connection) at the config's credentials. It only
 * edits configuration (the connection opens lazily, nothing is queried, no Eloquent), reads the file with the
 * literal-only LegacyConfigReader (never includes or evaluates legacy PHP) and leaves an explicitly configured
 * MySQL/MariaDB default alone (LivePhases then refuses a mismatching database). persist() writes the same
 * settings to .env when the install phase begins, so the finished site boots on this database without apply().
 */
final class LegacyDatabaseConnection
{
    /** Config key set to the database name while the override is in force. */
    public const APPLIED = 'migration-wizard.legacy_connection';

    /** Applies the override; returns whether it is in force. Never throws. */
    public static function apply(string $basePath): bool
    {
        if (config(self::APPLIED) !== null) {
            return true;
        }
        $default = (string) config('database.default');
        if (config("database.connections.{$default}.driver", $default) !== 'sqlite') {
            return false;
        }
        try {
            $settings = self::settings($basePath);
        } catch (Throwable) {
            return false;
        }

        config([
            'database.connections.mysql' => array_merge((array) config('database.connections.mysql'), [
                'url' => null,
                'host' => $settings['host'],
                'port' => (string) $settings['port'],
                'database' => $settings['database'],
                'username' => $settings['username'],
                'password' => $settings['password'],
                'unix_socket' => '',
            ]),
            'database.default' => 'mysql',
            self::APPLIED => $settings['database'],
        ]);
        app('db')->purge('mysql');

        return true;
    }

    /** @return array{host: string, port: int, database: string, username: string, password: string} */
    public static function settings(string $basePath): array
    {
        $config = (new LegacyConfigReader)->read($basePath.'/data/config.php');
        if (! in_array(strtolower($config['DATABASE_DRIVER'] ?? 'mysql'), ['mysql', 'mysqli'], true)) {
            throw new RuntimeException('Only MySQL/MariaDB legacy databases are supported.');
        }

        return [
            'host' => $config['DATABASE_HOST'],
            'port' => $config['DATABASE_PORT'],
            'database' => $config['DATABASE_NAME'],
            'username' => $config['DATABASE_USER'],
            'password' => $config['DATABASE_PASSWORD'] ?? '',
        ];
    }

    /** Whether the install phase has to save the connection to .env (only while the override is in force). */
    public static function inForce(): bool
    {
        // LivePhases also runs in plain unit tests without an application: no config there means no override.
        $container = Container::getInstance();

        return $container->bound('config') && $container->make('config')->get(self::APPLIED) !== null;
    }

    /** Refuses early (before anything is changed) when .env cannot be written. */
    public static function assertPersistable(?EnvFile $env = null): void
    {
        if (self::inForce() && ! ($env ?? new EnvFile)->writable()) {
            throw new RuntimeException('The .env file is not writable, so the database settings cannot be saved for the finished Epesi 3 site; nothing was changed.');
        }
    }

    /**
     * Saves the legacy database as the application's database in .env (a MariaDB server gets the `mariadb`
     * connection). A no-op unless the override is in force, so a configured site and the SQLite test fake never
     * have their .env touched. Idempotent.
     */
    public static function persist(?EnvFile $env = null, ?DatabaseSetup $setup = null): void
    {
        if (! self::inForce()) {
            return;
        }
        $setup ??= new DatabaseSetup;
        $connection = self::serverConnection();
        $values = $setup->settings($connection, [
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => config('database.connections.mysql.database'),
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
        ]);
        if ($env !== null) {
            $env->set($values);

            return;
        }
        $setup->save($values); // .env, then config:clear so no cached config keeps serving the old values
    }

    /** `mariadb` when the server says so (Laravel treats it as its own driver), otherwise `mysql`. */
    private static function serverConnection(): string
    {
        try {
            $version = (string) app('db')->connection('mysql')->selectOne('select version() as version')?->version;
        } catch (Throwable) {
            return 'mysql';
        }
        if (stripos($version, 'mariadb') === false || config('database.connections.mariadb') === null) {
            return 'mysql';
        }
        config(['database.connections.mariadb' => array_merge((array) config('database.connections.mariadb'), (array) config('database.connections.mysql'), ['driver' => 'mariadb'])]);
        config(['database.default' => 'mariadb']);
        app('db')->purge('mariadb');

        return 'mariadb';
    }
}
