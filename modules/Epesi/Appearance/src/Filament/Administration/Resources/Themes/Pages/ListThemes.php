<?php

namespace Epesi\Modules\Appearance\Filament\Administration\Resources\Themes\Pages;

use Epesi\Modules\Appearance\Filament\Administration\Resources\Themes\ThemeResource;
use Epesi\Modules\Appearance\Models\AppearanceSetting;
use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;

class ListThemes extends ListRecords
{
    protected static string $resource = ThemeResource::class;

    /**
     * Plain Livewire state, not a Filament form: one field with no other
     * settings around it doesn't need a second Schema/Form living alongside
     * the table's own. See application-name.blade.php.
     */
    public string $appName = '';

    public function mount(): void
    {
        parent::mount();

        $this->appName = AppearanceSetting::appName();
    }

    public function saveAppName(): void
    {
        $this->validate(['appName' => ['required', 'string', 'max:64']]);

        AppearanceSetting::current()->update(['app_name' => $this->appName]);

        Notification::make()->success()->title(__('Application name saved'))->send();
    }

    /**
     * The default List page body (tabs, the table, its own render hooks),
     * with the application-name field ahead of it — this resource's one
     * global, not-per-theme setting, per AI-shared/Epesi-custom-themes.md.
     */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('epesi-appearance::application-name'),
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
