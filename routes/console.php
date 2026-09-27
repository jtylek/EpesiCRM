<?php

use App\Services\DemoReset;
use App\Support\Demo;
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
