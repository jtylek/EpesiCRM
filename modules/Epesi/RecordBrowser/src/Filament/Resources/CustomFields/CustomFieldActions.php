<?php

namespace Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields;

use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldRegistry;
use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldSchema;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The destructive half of the custom-field screen, kept away from the ordinary
 * Delete action on purpose.
 *
 * There are three ways to get rid of a field, and they are not the same thing:
 *
 * - turn `active` off — hidden everywhere, data untouched, reversible;
 * - Delete the definition — the field is gone from every screen, the column and
 *   its data stay (recoverable only through SQL);
 * - Drop column — the column and everything in it are gone.
 */
class CustomFieldActions
{
    public static function dropColumn(): Action
    {
        return Action::make('dropColumn')
            ->label('Drop column')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->tooltip(__('Drop column and data'))
            ->visible(fn (CustomField $record): bool => ($table = $record->modelTable()) !== null
                && Schema::hasColumn($table, (string) $record->column))
            ->requiresConfirmation()
            ->modalHeading(fn (CustomField $record): string => __('Drop :field?', ['field' => $record->label]))
            ->modalDescription(fn (CustomField $record): string => "This removes the column {$record->modelTable()}.{$record->column} and every value stored in it, on every record. There is no undo.")
            ->modalSubmitActionLabel(__('Drop the column'))
            ->action(function (CustomField $record): void {
                try {
                    app(CustomFieldSchema::class)->drop($record);
                } catch (Throwable $exception) {
                    Notification::make()->danger()->title(__('Could not drop the column'))->body($exception->getMessage())->persistent()->send();

                    return;
                }

                // A definition with no column behind it renders a field that
                // silently fails to save, so the row goes with it.
                $record->delete();

                CustomFieldRegistry::refresh();

                Notification::make()->success()->title(__('Dropped :field', ['field' => $record->label]))->send();
            });
    }
}
