<?php

namespace Epesi\Modules\Roundcube\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Roundcube's database tables, created or brought up to date through the
 * application's own database connection, from Roundcube's own SQL files.
 *
 * It does what Roundcube's `bin/initdb.sh --dir=SQL --update` does (read
 * rcmail_utils::db_init() and db_update()), without starting a PHP process
 * for it: shared hosts disable proc_open() and exec(), and a web server's PHP
 * isn't a command line. The table prefix is applied by the same rules as
 * rcube_db::fix_table_names().
 */
class RoundcubeSchema
{
    /** What Roundcube assumes for tables from before it kept a version. */
    protected const LAST_VERSION_WITHOUT_SYSTEM_TABLE = 2012080700;

    /** The first schema update that writes its version to the system table. */
    protected const FIRST_VERSIONED_UPDATE = 2013011000;

    protected Connection $connection;

    public function __construct(?Connection $connection = null)
    {
        $this->connection = $connection ?? DB::connection();
    }

    /**
     * @param  string  $directory  Roundcube's SQL/ folder
     * @return string what was done, for the progress line
     */
    public function migrate(string $directory, string $prefix = ''): string
    {
        $provider = $this->provider();

        if (! Schema::connection($this->connection->getName())->hasTable($prefix.'system')) {
            $this->script($this->read($directory.DIRECTORY_SEPARATOR.$provider.'.initial.sql'), $prefix);

            return 'Created Roundcube\'s database tables.';
        }

        $current = $this->version($prefix);
        $updates = $this->updates($directory.DIRECTORY_SEPARATOR.$provider, $current);

        foreach ($updates as $version => $file) {
            $this->script($this->read($file), $prefix);

            if ($version >= self::FIRST_VERSIONED_UPDATE) {
                $this->saveVersion($prefix, $version);
            }
        }

        return $updates === []
            ? 'Roundcube\'s database tables are up to date.'
            : 'Updated Roundcube\'s database tables to '.array_key_last($updates).'.';
    }

    /** The name of Roundcube's SQL files for this connection's database. */
    protected function provider(): string
    {
        return match ($this->connection->getDriverName()) {
            'mysql', 'mariadb' => 'mysql',
            'pgsql' => 'postgres',
            'sqlite' => 'sqlite',
            default => throw new RuntimeException('Roundcube can\'t use the "'.$this->connection->getDriverName().'" database driver.'),
        };
    }

    protected function read(string $file): string
    {
        $sql = is_file($file) ? file_get_contents($file) : false;

        if ($sql === false || $sql === '') {
            throw new RuntimeException("Roundcube's schema file {$file} is missing or empty.");
        }

        return $sql;
    }

    protected function version(string $prefix): int
    {
        $version = $this->connection->table($prefix.'system')->where('name', 'roundcube-version')->value('value');

        return $version ? (int) $version : self::LAST_VERSION_WITHOUT_SYSTEM_TABLE;
    }

    protected function saveVersion(string $prefix, int $version): void
    {
        $table = $this->connection->table($prefix.'system');

        if ($table->where('name', 'roundcube-version')->update(['value' => (string) $version]) === 0) {
            $table->insert(['name' => 'roundcube-version', 'value' => (string) $version]);
        }
    }

    /**
     * @return array<int, string> the update files newer than the current version, oldest first
     */
    protected function updates(string $directory, int $current): array
    {
        if (! is_dir($directory)) {
            throw new RuntimeException("Roundcube's database updates for this driver are missing: {$directory}.");
        }

        $updates = [];

        foreach (scandir($directory) ?: [] as $file) {
            if (preg_match('/^(\d+)\.sql$/', $file, $match) && (int) $match[1] > $current) {
                $updates[(int) $match[1]] = $directory.DIRECTORY_SEPARATOR.$file;
            }
        }

        ksort($updates);

        return $updates;
    }

    /**
     * Roundcube's rcube_db::exec_script(): a statement ends at a line that
     * ends with a semicolon; comments and empty lines are skipped.
     */
    protected function script(string $sql, string $prefix): void
    {
        $buffer = '';

        foreach (explode("\n", $this->prefixTables($sql, $prefix)) as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }

            if (str_ends_with($trimmed, ';')) {
                $statement = $buffer.substr(rtrim($line), 0, -1);
                $buffer = '';

                if ($statement !== '') {
                    $this->connection->unprepared($statement);
                }
            } else {
                $buffer .= $line."\n";
            }
        }
    }

    /** Roundcube's rcube_db::fix_table_names(). */
    protected function prefixTables(string $sql, string $prefix): string
    {
        if ($prefix === '') {
            return $sql;
        }

        return (string) preg_replace_callback(
            '/((TABLE|TRUNCATE( TABLE)?|(?<!ON )UPDATE|INSERT INTO|FROM'
            .'| ON(?! (DELETE|UPDATE))|REFERENCES|CONSTRAINT|FOREIGN KEY|INDEX|UNIQUE( INDEX)?)'
            .'\s+(IF (NOT )?EXISTS )?[`"]*)([^`"\( \r\n]+)/',
            function (array $matches) use ($prefix): string {
                // A schema prefix (ends with a dot) doesn't go on these.
                if (str_ends_with($prefix, '.')) {
                    if (preg_match('/(CONSTRAINT|UNIQUE|INDEX)[\s\t`"]*$/', $matches[1])) {
                        $prefix = '';
                    } elseif (in_array($last = substr($matches[1], -1), ['`', '"'], true)) {
                        $prefix = substr($prefix, 0, -1).$last.'.'.$last;
                    }
                }

                return $matches[1].$prefix.$matches[count($matches) - 1];
            },
            $sql,
        );
    }
}
