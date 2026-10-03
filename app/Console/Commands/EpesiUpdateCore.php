<?php

namespace App\Console\Commands;

use App\Services\Update\CoreUpdater;
use App\Services\Update\UpdateException;
use Illuminate\Console\Command;

/**
 * Applies a core release zip over this installation from the command line —
 * what Administration → Epesi Store → "Update epesi" does after downloading
 * one (see CoreUpdater). For hosts where the web server can't write to the
 * application's folders, or to update from a zip downloaded by hand. The
 * database is migrated afterwards with `php artisan epesi:update`.
 */
class EpesiUpdateCore extends Command
{
    protected $signature = 'epesi:update-core {zip : a release zip built by epesi:package}';

    protected $description = 'Update the application files from a release zip (run epesi:update afterwards)';

    public function handle(CoreUpdater $updater): int
    {
        try {
            $result = $updater->apply((string) $this->argument('zip'));
        } catch (UpdateException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("epesi {$result['from']} → {$result['to']}: {$result['added']} files added, {$result['replaced']} replaced, {$result['removed']} removed.");
        $this->line("Backup of the replaced files: {$result['backup']}");
        $this->line('Now run: php artisan epesi:update');

        return self::SUCCESS;
    }
}
