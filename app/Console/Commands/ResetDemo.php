<?php

namespace App\Console\Commands;

use App\Services\DemoReset;
use App\Support\Demo;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Throwable;

/**
 * `php artisan demo:reset`: a demo (DEMO_MODE=true) back to freshly installed
 * with the demo data, keeping its login audit (App\Services\DemoReset). The
 * scheduler runs it every day at config('demo.reset_at') (routes/console.php);
 * a deploy runs it once more at the end.
 *
 * It empties the whole database, so it refuses to run unless demo mode is
 * on, and asks first in production unless given --force.
 */
class ResetDemo extends Command
{
    use ConfirmableTrait;

    protected $signature = 'demo:reset
        {--force : don\'t ask in production (the scheduler passes it)}';

    protected $description = 'Empty the demo\'s database and install it again with the demo data, keeping the login audit';

    public function handle(DemoReset $reset): int
    {
        if (! Demo::enabled()) {
            $this->components->error('This is not a demo (DEMO_MODE isn\'t true in .env). demo:reset would delete everything in the database, so it only runs on a demo.');

            return self::FAILURE;
        }

        if (! $this->confirmToProceed('This deletes everything in the database except the login audit.')) {
            return self::FAILURE;
        }

        // Down for something else (a deploy, which brings it up itself), or
        // still down from a reset that stopped halfway, which this one finishes.
        $downForSomethingElse = $this->laravel->isDownForMaintenance() && ! DemoReset::interrupted();

        if (! $this->laravel->isDownForMaintenance()) {
            $this->callSilently('down', ['--render' => 'epesi.demo-resetting', '--retry' => 60]);
        }

        try {
            $kept = $reset->run();
        } catch (Throwable $e) {
            report($e);
            $this->components->error('The reset stopped: '.$e->getMessage());
            $this->components->warn('The demo stays in maintenance mode. Running demo:reset again finishes it with the saved login audit; the scheduler tries every 15 minutes.');

            return self::FAILURE;
        } finally {
            // Half installed, the demo would only show errors; its
            // maintenance page reloads until a retry has finished.
            if (! $downForSomethingElse && ! DemoReset::interrupted()) {
                $this->callSilently('up');
            }
        }

        foreach ($kept as $table => $rows) {
            $this->components->twoColumnDetail("Kept {$table}", (string) $rows);
        }

        $this->components->info('The demo is reset.');

        return self::SUCCESS;
    }
}
