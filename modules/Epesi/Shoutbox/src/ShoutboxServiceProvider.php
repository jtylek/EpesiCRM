<?php

namespace Epesi\Modules\Shoutbox;

use Epesi\Modules\Shoutbox\Models\Message;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class ShoutboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Relation::morphMap(['shoutbox_message' => Message::class]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'epesi-shoutbox');
    }
}
