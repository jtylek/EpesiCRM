<?php

namespace Epesi\Modules\Store;

use Epesi\Modules\Store\Models\StoreSetting;
use Epesi\Modules\Store\Services\Registration;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class StoreServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'epesi-store');

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // Once a day: is there a newer epesi (the super administrators hear
            // about it in the bell), plus the diagnostics if they opted in.
            $schedule->call(fn () => app(Registration::class)->checkIn())
                ->name('Epesi Store: check for a new epesi version')
                ->daily()
                ->when(fn (): bool => self::setting()?->isRegistered() ?? false);

            // While the e-mail isn't confirmed yet, so the licence key arrives
            // soon after the click without anyone opening the Store.
            $schedule->call(fn () => app(Registration::class)->refreshStatus())
                ->name('Epesi Store: pick up a confirmed registration')
                ->everyFifteenMinutes()
                ->when(fn (): bool => self::setting()?->isPending() ?? false);
        });
    }

    /** Null before the module's tables exist (a fresh install, mid-update). */
    protected static function setting(): ?StoreSetting
    {
        return rescue(fn () => StoreSetting::current(), null, report: false);
    }
}
