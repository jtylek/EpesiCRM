<?php

namespace App\Services;

use App\Services\Setup\Installer;
use App\Services\Setup\InstallOptions;
use App\Support\Setup\SetupState;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Puts a demo back as it was installed (`php artisan demo:reset`, every
 * night from the scheduler): empties the database, installs again with the
 * demo data, and removes the files visitors uploaded. The tables in
 * config('demo.keep_tables'), the login audit, survive it: who used the demo
 * and from where is the point of keeping one.
 *
 * Those tables are written to a file on the `local` disk first
 * (demo/keep-<time>.json), before anything is dropped, so a reset that stops
 * halfway loses nothing. Until it finishes, demo/pending names that file, and
 * the next reset restores from it instead of saving the tables again, which
 * by then hold less. The last few files are kept.
 */
class DemoReset
{
    /** Folders on the `local` disk that hold what visitors uploaded or were sent. */
    protected const UPLOAD_DIRECTORIES = ['livewire-tmp', 'mail-upload', 'mail-outgoing'];

    protected const PENDING = 'demo/pending';

    protected const KEEP_FILES = 7;

    public function __construct(
        protected Installer $installer,
    ) {}

    /** A reset stopped halfway: the database may be empty or half installed. */
    public static function interrupted(): bool
    {
        return Storage::disk('local')->exists(self::PENDING);
    }

    /**
     * @return array<string, int> rows kept, by table
     */
    public function run(): array
    {
        $disk = Storage::disk('local');
        $file = $disk->exists(self::PENDING) ? trim((string) $disk->get(self::PENDING)) : $this->saveKeptTables();
        $disk->put(self::PENDING, $file);

        $this->emptyDatabase();
        // Before the install, which stores the demo notes' files: clearing
        // afterwards deleted them, leaving every demo note's file a 404.
        $this->removeUploads();
        $this->install();
        $restored = $this->restoreKeptTables($file);

        $disk->delete(self::PENDING);

        $this->pruneKeptFiles();

        return $restored;
    }

    /**
     * @return string the file on the `local` disk
     */
    public function saveKeptTables(): string
    {
        $tables = [];

        foreach (config('demo.keep_tables', []) as $table) {
            if (Schema::hasTable($table)) {
                $tables[$table] = DB::table($table)->get()->map(fn (object $row): array => (array) $row)->all();
            }
        }

        $file = 'demo/keep-'.now()->format('Ymd-His').'.json';

        if (! Storage::disk('local')->put($file, json_encode($tables, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))) {
            throw new RuntimeException("Could not write {$file}; nothing was reset.");
        }

        return $file;
    }

    /**
     * Every table dropped, then the core migrations: what `db:wipe` and
     * `migrate` do, except that db:wipe also rebuilds an SQLite file, which
     * the tests' in-memory database can't do inside their transaction.
     */
    public function emptyDatabase(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            // SQLite ignores the switch inside a transaction (the tests'), and
            // then a parent table can't go while a child still points at it:
            // retry what's left until every table is gone.
            $tables = Schema::getTableListing(schemaQualified: false);
            while ($tables !== []) {
                $left = [];

                foreach ($tables as $table) {
                    try {
                        Schema::drop($table);
                    } catch (QueryException $e) {
                        $left[] = $table;
                    }
                }

                if (count($left) === count($tables)) {
                    throw $e;
                }

                $tables = $left;
            }
        });

        File::delete(SetupState::markerPath());
        SetupState::flush();

        Artisan::call('migrate', ['--force' => true]);
    }

    public function install(): void
    {
        $this->installer->install(new InstallOptions(
            adminName: 'Administrator',
            adminEmail: (string) config('demo.admin_email'),
            // Nobody signs in as the administrator: the demo login offers
            // only the accounts in config('demo.users').
            adminPassword: Str::password(32),
            mailMethod: InstallOptions::MAIL_LOG,
            demoData: true,
            // A developer trying demo mode keeps their .env's debug settings.
            development: ! app()->isProduction(),
        ));

        // The module pages ("Your company", regional settings) are for a
        // real administrator; the demo data already has its company.
        SetupState::markFinished();
    }

    /**
     * Back with their own ids. A reference to a row that no longer exists
     * (a user the new install doesn't have) becomes empty; login_audits
     * keeps the e-mail in its own column.
     *
     * @return array<string, int> rows restored, by table
     */
    public function restoreKeptTables(string $file): array
    {
        $tables = json_decode((string) Storage::disk('local')->get($file), true, flags: JSON_THROW_ON_ERROR);
        $restored = [];

        foreach ($tables as $table => $rows) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);
            $references = collect(Schema::getForeignKeys($table))
                ->filter(fn (array $key): bool => count($key['columns']) === 1)
                ->map(fn (array $key): array => [
                    'column' => $key['columns'][0],
                    'existing' => DB::table($key['foreign_table'])->pluck($key['foreign_columns'][0])->flip()->all(),
                ]);

            $rows = array_map(function (array $row) use ($columns, $references): array {
                $row = array_intersect_key($row, array_flip($columns));

                foreach ($references as $reference) {
                    $value = $row[$reference['column']] ?? null;

                    if ($value !== null && ! isset($reference['existing'][$value])) {
                        $row[$reference['column']] = null;
                    }
                }

                return $row;
            }, $rows);

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table($table)->insert($chunk);
            }

            $restored[$table] = count($rows);
        }

        return $restored;
    }

    protected function removeUploads(): void
    {
        foreach (FileStorage::disk()->directories() as $directory) {
            FileStorage::disk()->deleteDirectory($directory);
        }

        foreach (FileStorage::disk()->files() as $file) {
            FileStorage::disk()->delete($file);
        }

        foreach (self::UPLOAD_DIRECTORIES as $directory) {
            Storage::disk('local')->deleteDirectory($directory);
        }
    }

    protected function pruneKeptFiles(): void
    {
        $disk = Storage::disk('local');
        $files = collect($disk->files('demo'))
            ->filter(fn (string $file): bool => str_starts_with(basename($file), 'keep-'))
            ->sort()
            ->values();

        $disk->delete($files->slice(0, max(0, $files->count() - self::KEEP_FILES))->all());
    }
}
