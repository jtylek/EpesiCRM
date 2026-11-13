<?php

namespace Epesi\Modules\RecordBrowser\Filament\RelationManagers;

use App\Filament\Concerns\TranslatesRelationManagerLabels;
use Epesi\Modules\RecordBrowser\History\HistoryChanges;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Spatie\Activitylog\Models\Activity;

/**
 * The History addon, once, for every recordset — the port of Epesi's
 * `<table>_edit_history`, which is likewise a property of being a recordset
 * rather than something each one opts into.
 *
 * Page classes cannot be shared between resources (Filament asks the page class
 * for resource-level middleware while building routes), but relation managers
 * can: nothing resolves a resource from them, they are handed their owner record
 * at render time. So this is the one copy, replacing the per-resource
 * ActivitiesRelationManager every hand-written resource used to carry — five of
 * which had already drifted into two different versions.
 *
 * The `changes` column is the `<tab>_edit_history_data` half of Epesi's history:
 * without it the tab says only *that* a record was edited, not what changed.
 * A long text shows only the words that changed; the Show action has the whole
 * change and the text as it read after it.
 *
 * Reads spatie/laravel-activitylog through the model's `activities()` relation;
 * RecordsetResource::getRelations() only adds it to models that have one.
 */
class HistoryRelationManager extends RelationManager
{
    use TranslatesRelationManagerLabels;

    protected static string $relationship = 'activities';

    protected static ?string $title = 'History';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        $fields = $this->loggedFields();

        return $table
            ->heading(null)
            ->recordTitleAttribute('description')
            ->defaultSort('created_at', 'desc')
            // Event, By and When shrink to their content (a 1% width on an
            // auto-layout table); Changes, last, takes the rest of the row.
            ->columns([
                TextColumn::make('event')
                    ->toggleable(false)
                    ->width('1%'),
                TextColumn::make('causer.name')
                    ->toggleable(false)
                    ->label('By')
                    ->width('1%')
                    ->getStateUsing(fn (Activity $record): string => HistoryChanges::causerName($record)),
                TextColumn::make('created_at')
                    ->toggleable(false)
                    ->label('When')
                    ->width('1%')
                    ->dateTime()
                    ->formatStateUsing(fn (Activity $record): ?string => RegionalSetting::display($record->created_at))
                    ->sortable(),
                TextColumn::make('changes')
                    ->toggleable(false)
                    ->label('Changes')
                    ->getStateUsing(fn (Activity $record): string|HtmlString => HistoryChanges::summary($record, $fields, $table))
                    ->wrap(),
            ])
            ->filters([])
            ->headerActions([])
            ->recordActions([
                $this->showAction($fields, $table),
            ])
            ->toolbarActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    /**
     * The whole of an entry's long-text changes, which the table cuts to the
     * words around each change: every word of the diff, and the text as it
     * read after the edit. Only on entries that change a long text; every
     * entry holds the whole text, so nothing more is stored for it.
     *
     * Its footer can restore that text: a normal edit of the record, logged
     * like any other, so what it replaces stays in History and can be
     * restored in turn. Offered to whoever may edit the record, and only
     * where the text differs from the current one.
     *
     * @param  array<string, Field>  $fields
     */
    protected function showAction(array $fields, Table $table): Action
    {
        $longTexts = fn (Activity $record): Collection => HistoryChanges::changed($record)
            ->filter(fn ($value, $key): bool => isset($fields[$key]) && $fields[$key]->type === FieldType::LongText);

        $restorable = fn (Activity $record): Collection => $this->getPageClass()::getResource()::canEdit($this->getOwnerRecord())
            ? $longTexts($record)->reject(fn ($value, $key): bool => (string) $value === (string) $this->getOwnerRecord()->getAttribute($key))
            : collect();

        return Action::make('show')
            ->iconButton()
            ->icon(Heroicon::OutlinedEye)
            ->tooltip(__('Show in full'))
            ->visible(fn (Activity $record): bool => $longTexts($record)->isNotEmpty())
            ->modalHeading(fn (Activity $record): string => HistoryChanges::causerName($record).', '
                .$record->created_at?->setTimezone(FilamentTimezone::get())->translatedFormat($table->getDefaultDateTimeDisplayFormat()))
            ->schema(fn (Activity $record): array => $longTexts($record)
                ->map(function ($value, $key) use ($fields, $record): Section {
                    $created = $record->event === 'created';

                    return Section::make(__($fields[$key]->getLabel()))
                        ->schema([
                            TextEntry::make("changes_{$key}")
                                ->hiddenLabel()
                                ->state($fields[$key]->formatLoggedChange(data_get($record->properties->get('old', collect()), $key), $value, $record, whole: true))
                                ->visible(! $created),
                            TextEntry::make("version_{$key}")
                                ->label(__('As created'))
                                ->state($fields[$key]->formatLoggedVersion($value))
                                ->prose()
                                ->visible($created),
                        ]);
                })
                ->values()
                ->all())
            ->modalSubmitAction(false)
            ->extraModalFooterActions(function (Action $action, Activity $record) use ($fields, $restorable): array {
                $restore = $restorable($record);

                return $restore
                    ->map(fn ($value, $key): Action => $action->makeModalSubmitAction("restore_{$key}", ['restore' => $key])
                        ->label($restore->count() > 1
                            ? __('Restore :field', ['field' => __($fields[$key]->getLabel())])
                            : __('Restore this version'))
                        ->icon(Heroicon::OutlinedArrowUturnLeft)
                        ->color('primary'))
                    ->values()
                    ->all();
            })
            // Only the footer's Restore submits; its argument names the field.
            // Asked again here, since arguments come from the browser.
            ->action(function (Activity $record, array $arguments) use ($restorable): void {
                $key = $arguments['restore'] ?? null;
                $restore = $restorable($record);

                if (! is_string($key) || ! $restore->has($key)) {
                    return;
                }

                $this->getOwnerRecord()->forceFill([$key => $restore->get($key)])->save();

                Notification::make()
                    ->success()
                    ->title(__('Version restored'))
                    ->body(__('The text it replaced is kept in History.'))
                    ->send();

                // The page shows the record too: have it read it again.
                $this->dispatch('refresh-page');
            })
            ->modalCancelActionLabel(__('Close'))
            ->modalWidth(Width::FourExtraLarge);
    }

    /**
     * The owner's recordset fields by column name (see HistoryChanges::fieldsFor()).
     *
     * @return array<string, Field>
     */
    protected function loggedFields(): array
    {
        return HistoryChanges::fieldsFor($this->getPageClass()::getResource());
    }
}
