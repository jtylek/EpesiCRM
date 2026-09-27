<?php

namespace Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Pages;

use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldRegistry;
use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldSchema;
use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\CustomFieldResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

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
            // The same work as `customfields:sync`, called directly: the command
            // is only registered for the console, so Artisan::call() from a web
            // request can't find it.
            ->action(function (): void {
                $created = app(CustomFieldSchema::class)->sync();

                CustomFieldRegistry::refresh();

                Notification::make()->success()->title(__('Columns checked'))
                    ->body($created === []
                        ? __('Every field already has its column.')
                        : __('Created:').' '.implode(', ', $created))
                    ->send();
            });
    }
}
