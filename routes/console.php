<?php

use App\Services\DemoReset;
use App\Support\Demo;
use App\Support\Optimize\FrameworkCaches;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Demo mode (App\Support\Demo): the demo data back every night, keeping the
// login audit. A reset that stopped halfway leaves the demo in maintenance
// mode and is tried again every quarter of an hour until it finishes, which
// is why both run even in maintenance mode.
Schedule::command('demo:reset', ['--force'])
    ->dailyAt((string) config('demo.reset_at'))
    ->timezone((string) config('demo.timezone'))
    ->when(fn (): bool => Demo::enabled())
    ->evenInMaintenanceMode()
    ->withoutOverlapping();

Schedule::command('demo:reset', ['--force'])
    ->everyFifteenMinutes()
    ->when(fn (): bool => Demo::enabled() && DemoReset::interrupted())
    ->evenInMaintenanceMode()
    ->withoutOverlapping();

// Builds Laravel's and Filament's caches on an installation, rebuilds them
// after a change, and picks memcached when it works (FrameworkCaches,
// CacheStore). No withoutOverlapping(): its lock lives in the cache store,
// and this task is what switches away from a memcached that stopped answering.
// Cron's own once-a-minute rule (CronRunner) keeps two runs apart.
Schedule::command('epesi:optimize')
    ->everyMinute()
    ->when(fn (): bool => app(FrameworkCaches::class)->wanted());
