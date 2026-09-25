<?php

namespace Epesi\Modules\Shoutbox;

use Epesi\Modules\Shoutbox\Filament\Pages\Shoutbox;
use Epesi\Modules\Shoutbox\Filament\Widgets\ShoutboxWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

class ShoutboxPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'epesi-shoutbox';
    }

    public function register(Panel $panel): void
    {
        $panel
            // A dashboard widget: the port of the Shoutbox applet.
            ->widgets([ShoutboxWidget::class])
            // The full log, beyond the widget's latest SHOWN messages.
            ->pages([Shoutbox::class]);
    }

    public function boot(Panel $panel): void {}
}
