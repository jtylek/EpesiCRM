<?php

namespace Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields;

use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldRegistry;
use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldSchema;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The destructive half of the custom-field screen.
 *
 * There are two ways to get rid of a field, and they are not the same thing:
 *
 * - turn `active` off — hidden everywhere, data untouched, reversible;
 * - Drop column — the definition, the column and everything in it are gone.
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
            ->modalDescription(fn (CustomField $record): string => $record->type === FieldType::Currency
                ? "This removes the columns {$record->modelTable()}.{$record->column} and {$record->column}_currency and every value stored in them, on every record. There is no undo."
                : "This removes the column {$record->modelTable()}.{$record->column} and every value stored in it, on every record. There is no undo.")
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
            })
            // The record is gone, so staying on its View page (/{record}) 404s
            // on the next render — send the user back to the index instead.
            ->successRedirectUrl(fn (): string => CustomFieldResource::getUrl('index'));
    }
}
