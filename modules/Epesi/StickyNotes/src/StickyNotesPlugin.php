<?php

namespace Epesi\Modules\StickyNotes;

use Epesi\Modules\StickyNotes\Filament\Widgets\StickyNotesWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

class StickyNotesPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'epesi-sticky-notes';
    }

    public function register(Panel $panel): void
    {
        // A dashboard applet only: the default Notes tab holds it.
        $panel->widgets([StickyNotesWidget::class]);
    }

    public function boot(Panel $panel): void {}
}
