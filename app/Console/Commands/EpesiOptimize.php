<?php

namespace App\Console\Commands;

use App\Support\Optimize\FrameworkCaches;
use Illuminate\Console\Command;

/**
 * Cron's every-minute check of the caches (App\Support\Optimize\FrameworkCaches)
 * and of memcached (App\Support\Optimize\CacheStore). Run by hand, it does the
 * same: run it twice after changing files when you can't wait for cron.
 */
class EpesiOptimize extends Command
{
    protected $signature = 'epesi:optimize
        {--clear : remove the caches; cron builds them again unless caching is switched off}';

    protected $description = 'Keeps the caches that speed up every page current, and uses memcached when it works';

    public function handle(FrameworkCaches $caches): int
    {
        if ($this->option('clear')) {
            $caches->forget();
            $this->components->info(__('Removed the caches.'));

            return self::SUCCESS;
        }

        $this->line($caches->refresh());

        return self::SUCCESS;
    }
}
