<?php

namespace Epesi\Modules\PriorityList;

use Epesi\Modules\PriorityList\Filament\Pages\PriorityListManagement;
use Epesi\Modules\PriorityList\Filament\Widgets\PriorityListWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

class PriorityListPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'epesi-priority-list';
    }

    public function register(Panel $panel): void
    {
        if ($panel->getId() === 'administration') {
            $panel->pages([PriorityListManagement::class]);

            return;
        }

        $panel->widgets([PriorityListWidget::class]);
    }

    public function boot(Panel $panel): void {}
}
