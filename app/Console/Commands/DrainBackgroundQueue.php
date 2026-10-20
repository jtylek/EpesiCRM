<?php

namespace App\Console\Commands;

use App\Support\Background\BackgroundWorkerHealth;
use Illuminate\Console\Command;
use Throwable;

class DrainBackgroundQueue extends Command
{
    protected $signature = 'epesi:background:drain
        {--max-time=45 : Maximum number of seconds to drain}
        {--tries=3 : Maximum attempts per job}
        {--timeout=30 : Maximum seconds for one job}';

    protected $description = 'Drain the optional Epesi background queue for a bounded time';

    public function handle(BackgroundWorkerHealth $health): int
    {
        if (! $health->enabled()) {
            $this->warn(__('The Epesi background worker is disabled.'));

            return self::SUCCESS;
        }

        $health->pruneRuns();
        $run = $health->beginRun();

        try {
            $exit = $this->call('queue:work', [
                'connection' => 'epesi-background',
                '--queue' => config('epesi-background.queue'),
                '--stop-when-empty' => true,
                '--max-time' => max(1, (int) $this->option('max-time')),
                '--tries' => max(1, (int) $this->option('tries')),
                '--timeout' => max(1, (int) $this->option('timeout')),
                '--no-interaction' => true,
            ]);

            $health->finishRun($run, $exit === self::SUCCESS, $exit === self::SUCCESS ? null : 'queue:work returned '.$exit);

            return $exit;
        } catch (Throwable $e) {
            $health->finishRun($run, false, $e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
