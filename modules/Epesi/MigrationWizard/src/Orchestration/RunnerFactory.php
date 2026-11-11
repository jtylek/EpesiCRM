<?php

namespace Epesi\Modules\MigrationWizard\Orchestration;

use App\Services\LegacyImport\TimeBudget;
use Epesi\Modules\MigrationWizard\Database\LegacyTableNamespace;
use Epesi\Modules\MigrationWizard\Files\FileLedger;
use Epesi\Modules\MigrationWizard\State\MigrationLock;
use Epesi\Modules\MigrationWizard\State\MigrationState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The one place that builds the migration state machine, so the command line and the browser page drive the very
 * same runner, lock, ledger, state file and gates: there is no second migration path.
 */
final class RunnerFactory
{
    public static function directory(): string
    {
        return storage_path('app/migration-wizard');
    }

    public static function state(): MigrationState
    {
        return new MigrationState(self::directory());
    }

    public static function lock(): MigrationLock
    {
        return new MigrationLock(self::directory());
    }

    public static function make(): MigrationRunner
    {
        return new MigrationRunner(
            self::state(),
            self::lock(),
            new LegacyTableNamespace,
            DB::connection()->getPdo(),
            self::phases(),
            app(TimeBudget::class),
            base_path(),
        );
    }

    public static function phases(): LivePhases
    {
        return new LivePhases(
            base_path(),
            DB::connection()->getPdo(),
            new FileLedger(self::directory(), base_path('data')),
            static fn (string $disk) => Storage::disk($disk),
        );
    }
}
