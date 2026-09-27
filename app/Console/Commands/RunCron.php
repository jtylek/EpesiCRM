<?php

namespace App\Console\Commands;

use App\Services\Cron\CronLog;
use App\Services\Cron\CronRunner;
use App\Support\Setup\SetupState;
use Illuminate\Console\Command;

/**
 * What cron.php runs: every scheduled task that is due, in this process
 * (see CronRunner). The server's cron calls it every minute.
 */
class RunCron extends Command
{
    protected $signature = 'epesi:cron';

    protected $description = 'Run the scheduled tasks that are due, in this process (what cron.php runs; see Administration → Cron)';

    public function handle(CronRunner $runner): int
    {
        // Cron may be set up before the wizard has run: nothing to do yet.
        if (! SetupState::isInstalled()) {
            $this->line('epesi is not set up yet.');

            return self::SUCCESS;
        }

        // Typed in a terminal, it's a run by hand, not cron.
        $results = $runner->runDue(CronLog::fromTerminal() ? 'terminal' : 'cli');

        foreach (explode("\n", CronRunner::report($results)) as $line) {
            $this->line($line);
        }

        return collect($results)->contains('status', 'failed') ? self::FAILURE : self::SUCCESS;
    }
}
