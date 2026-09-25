<?php

namespace App\Services\Setup;

use App\Support\Setup\EnvFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Epesi's setup.php database page: try a connection, save it to .env, create
 * the tables. Shared by `php artisan epesi:install` and the setup wizard.
 */
class DatabaseSetup
{
    /**
     * The .env keys for a connection, from the values entered in setup.
     * SQLite needs only the file; the rest need a server and a login.
     *
     * @param  array<string, mixed>  $input  host, port, database, username, password
     * @return array<string, string>
     */
    public function settings(string $connection, array $input): array
    {
        $settings = ['DB_CONNECTION' => $connection];

        if ($this->isSqlite($connection)) {
            return $settings + ['DB_DATABASE' => (string) ($input['database'] ?? '')];
        }

        return $settings + [
            'DB_HOST' => (string) ($input['host'] ?? ''),
            'DB_PORT' => (string) ($input['port'] ?? ''),
            'DB_DATABASE' => (string) ($input['database'] ?? ''),
            'DB_USERNAME' => (string) ($input['username'] ?? ''),
            'DB_PASSWORD' => (string) ($input['password'] ?? ''),
        ];
    }

    public function isSqlite(string $connection): bool
    {
        return config("database.connections.{$connection}.driver", $connection) === 'sqlite';
    }

    /**
     * Makes these settings the running application's default connection and
     * opens it. Throws the database's own error when it can't connect.
     *
     * @param  array<string, string>  $settings
     */
    public function connect(array $settings): void
    {
        $connection = $settings['DB_CONNECTION'];

        // A new SQLite database is just an empty file.
        if ($this->isSqlite($connection) && ($file = $settings['DB_DATABASE'] ?? '') !== '' && ! is_file($file)) {
            touch($file);
        }

        $keys = ['DB_HOST' => 'host', 'DB_PORT' => 'port', 'DB_DATABASE' => 'database', 'DB_USERNAME' => 'username', 'DB_PASSWORD' => 'password'];

        foreach ($keys as $env => $key) {
            if (array_key_exists($env, $settings)) {
                config(["database.connections.{$connection}.{$key}" => $settings[$env]]);
            }
        }

        config(['database.default' => $connection]);
        DB::purge($connection);
        DB::connection()->getPdo();
    }

    /**
     * @param  array<string, string>  $settings
     */
    public function save(array $settings): void
    {
        (new EnvFile)->set($settings);
    }

    /**
     * Creates the application's tables; throws with the migrator's output
     * when it fails.
     */
    public function migrate(): void
    {
        if (Artisan::call('migrate', ['--force' => true]) !== 0) {
            throw new SetupException(trim(Artisan::output()) ?: 'Creating the database tables failed.');
        }
    }
}
