<?php

namespace App\Console\Commands;

use App\Services\Setup\SystemUpdate;
use Illuminate\Console\Command;
use Throwable;

/**
 * Epesi's update.php on the command line: after unpacking a new release over
 * an installation, runs the database migrations of the core app and of every
 * enabled module (see SystemUpdate). Administration → Database update does
 * the same in the browser.
 */
class EpesiUpdate extends Command
{
    protected $signature = 'epesi:update
        {--pretend : only list what would run}';

    protected $description = 'Update the database after installing a new release (core and module migrations)';

    public function handle(SystemUpdate $update): int
    {
        $pending = $update->pending();

        if ($pending === []) {
            $this->components->info('The database is up to date.');

            return self::SUCCESS;
        }

        foreach ($pending as $label => $names) {
            $this->components->twoColumnDetail("<fg=yellow>{$label}</>", count($names).' pending');

            foreach ($names as $name) {
                $this->line('  '.$name);
            }
        }

        if ($this->option('pretend')) {
            return self::SUCCESS;
        }

        try {
            $output = $update->run();
        } catch (Throwable $e) {
            $this->components->error('The update stopped: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line($output);
        $this->components->info('The database is up to date.');

        return self::SUCCESS;
    }
}
