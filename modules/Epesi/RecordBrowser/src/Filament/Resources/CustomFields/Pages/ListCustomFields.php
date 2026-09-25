<?php

namespace Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Pages;

use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\CustomFieldResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;

class ListCustomFields extends ListRecords
{
    protected static string $resource = CustomFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->syncAction(),
        ];
    }

    /**
     * The definitions table is the source of truth and the schema is derived
     * from it; this is the button that makes that true — the repair path when a
     * column is missing, and how definitions copied from another install
     * materialise here.
     */
    protected function syncAction(): Action
    {
        return Action::make('sync')
            ->label('Repair columns')
            ->icon(Heroicon::OutlinedWrenchScrewdriver)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(__('Creates any column a field definition needs but the database is missing. Existing columns and data are untouched.'))
            ->action(function (): void {
                Artisan::call('customfields:sync');

                Notification::make()->success()->title(__('Columns checked'))->body(trim(Artisan::output()))->send();
            });
    }
}
