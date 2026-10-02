<?php

namespace Epesi\Modules\RecordBrowser\Filament\Widgets;

use App\Filament\Dashboard\Applet;
use App\Filament\Dashboard\IsApplet;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

abstract class RecordsetApplet extends TableWidget implements Applet
{
    use IsApplet;

    /** @var class-string<resource>|null */
    protected static ?string $resource = null;

    protected static string|BackedEnum|null $appletIcon = null;

    protected int|string|array $columnSpan = 1;

    protected function getAppletHeading(): string
    {
        $subtitle = $this->appletSetting('subtitle');

        return static::getAppletCaption().(filled($subtitle) ? ' - '.$subtitle : '');
    }

    protected function getAppletIcon(): string|BackedEnum
    {
        return static::$appletIcon
            ?? (static::$resource ? static::$resource::getNavigationIcon() : null)
            ?? Heroicon::OutlinedTableCells;
    }

    protected function getAppletCreateLabel(): string
    {
        return __('Create');
    }

    /** @return array<Action> */
    protected function getAppletHeaderActions(): array
    {
        $actions = [];
        $resource = static::$resource;

        if ($resource) {
            if ($resource::hasPage('create')) {
                $actions[] = Action::make('create')
                    ->label(fn (): string => $this->getAppletCreateLabel())
                    ->tooltip(fn (): string => $this->getAppletCreateLabel())
                    ->icon(Heroicon::OutlinedPlus)
                    ->iconButton()
                    ->color('gray')
                    ->url(fn (): string => $resource::getUrl('create'))
                    ->visible(fn (): bool => $resource::canCreate());
            }

            $actions[] = Action::make('fullscreen')
                ->label('Fullscreen')
                ->tooltip(__('Fullscreen'))
                ->icon(Heroicon::OutlinedArrowsPointingOut)
                ->iconButton()
                ->color('gray')
                ->url(fn (): string => $resource::getUrl());
        }

        $actions[] = $this->configureAppletAction();

        return $actions;
    }

    protected function makeTable(): Table
    {
        return parent::makeTable()
            ->heading(fn () => view('epesi-recordbrowser::dashboard.applet-heading', [
                'heading' => $this->getAppletHeading(),
                'icon' => $this->getAppletIcon(),
            ]))
            ->headerActions($this->getAppletHeaderActions())
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(10);
    }
}
