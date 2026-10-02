<?php

namespace Epesi\Modules\StickyNotes;

use Illuminate\Support\ServiceProvider;

class StickyNotesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'epesi-sticky-notes');
    }
}
