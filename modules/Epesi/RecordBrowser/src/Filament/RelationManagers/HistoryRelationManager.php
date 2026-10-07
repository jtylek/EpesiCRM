<?php

namespace Epesi\Modules\RecordBrowser\Filament\RelationManagers;

use App\Filament\Concerns\TranslatesRelationManagerLabels;
use App\Models\User;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
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
use Illuminate\Support\Str;
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
                    ->getStateUsing(fn (Activity $record): string => self::causerName($record)),
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
                    ->getStateUsing(function (Activity $record) use ($fields, $table): string|HtmlString {
                        $changed = self::changed($record);

                        if ($changed->isEmpty()) {
                            return $record->description;
                        }

                        $old = $record->properties->get('old', collect());

                        // The field's label and the values as the View page
                        // shows them ("Status: Open → In Progress"), not the
                        // column and what it stores ("status: 0 → 1").
                        $format = fn (string $key, mixed $value): string => isset($fields[$key])
                            ? $fields[$key]->formatLoggedValue($value, $table)
                            : self::stringify($value);

                        // Old value on red, new on green, as a diff marks them
                        // (colors in RecordBrowserServiceProvider). A created
                        // record had no old values, so it shows the new ones
                        // alone rather than a red "-" before each. HTML, so
                        // every piece is escaped: labels and values are user data.
                        $created = $record->event === 'created';
                        $seen = [];

                        return new HtmlString($changed
                            ->map(function ($value, $key) use ($fields, $format, $old, $record, $created, &$seen): ?string {
                                $field = $fields[$key] ?? null;

                                // A Customer field's two logged columns
                                // (`{name}_type`/`{name}_id`), and a Currency
                                // field's (`{name}`/`{name}_currency`), render
                                // as one line, from whichever key comes first.
                                if (in_array($field?->type, [FieldType::Customer, FieldType::Currency], true)) {
                                    if (isset($seen[$field->name])) {
                                        return null;
                                    }

                                    $seen[$field->name] = true;
                                }

                                $label = e(__($field?->getLabel() ?? Str::headline($key)));

                                // A change told from both values at once: a
                                // long text's changed words, a note's files.
                                $change = $field?->formatLoggedChange(data_get($old, $key), $value, $record);

                                if ($change !== null) {
                                    return $label.': '.$change->toHtml();
                                }

                                return sprintf(
                                    $created
                                        ? '%1$s: <span class="epesi-history-new">%3$s</span>'
                                        : '%1$s: <span class="epesi-history-old">%2$s</span> → <span class="epesi-history-new">%3$s</span>',
                                    $label,
                                    e($format($key, data_get($old, $key))),
                                    e($format($key, $value)),
                                );
                            })
                            ->filter(fn (?string $line): bool => $line !== null)
                            ->implode(', '));
                    })
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
        $longTexts = fn (Activity $record): Collection => self::changed($record)
            ->filter(fn ($value, $key): bool => isset($fields[$key]) && $fields[$key]->type === FieldType::LongText);

        $restorable = fn (Activity $record): Collection => $this->getPageClass()::getResource()::canEdit($this->getOwnerRecord())
            ? $longTexts($record)->reject(fn ($value, $key): bool => (string) $value === (string) $this->getOwnerRecord()->getAttribute($key))
            : collect();

        return Action::make('show')
            ->iconButton()
            ->icon(Heroicon::OutlinedEye)
            ->tooltip(__('Show in full'))
            ->visible(fn (Activity $record): bool => $longTexts($record)->isNotEmpty())
            ->modalHeading(fn (Activity $record): string => self::causerName($record).', '
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
     * The owner's recordset fields by column name, custom fields included. A
     * resource not built on the engine (e.g. Notes/Attachments, with its own
     * bespoke form) can still describe its fields for History alone via a
     * static `historyFields()` returning the same Field objects; failing
     * that, history keeps the stored values under headline-cased column names.
     *
     * @return array<string, Field>
     */
    protected function loggedFields(): array
    {
        $resource = $this->getPageClass()::getResource();

        $fields = match (true) {
            is_subclass_of($resource, RecordsetResource::class) => $resource::resolvedFields(),
            method_exists($resource, 'historyFields') => $resource::historyFields(),
            default => [],
        };

        $byName = collect($fields)->keyBy(fn (Field $field): string => $field->name);

        // A Customer field logs two plain columns (`{name}_type`/`{name}_id`,
        // a morphTo pair — see Field::customer()), not one; both map to the
        // same Field so table() can render them as its one historyUsing()
        // line instead of two raw ones.
        foreach ($byName->all() as $field) {
            if ($field->type === FieldType::Customer) {
                $byName["{$field->name}_type"] = $field;
                $byName["{$field->name}_id"] = $field;
            }

            if ($field->type === FieldType::Currency) {
                $byName[$field->currencyColumn()] = $field;
            }
        }

        return $byName->all();
    }

    /**
     * The entry's new values, less those it logged unchanged.
     *
     * @return Collection<string, mixed>
     */
    private static function changed(Activity $record): Collection
    {
        $old = $record->properties->get('old', collect());

        return collect($record->properties->get('attributes', []))
            ->reject(fn ($value, $key) => self::stringify(data_get($old, $key)) === self::stringify($value));
    }

    private static function causerName(Activity $record): string
    {
        return $record->causer instanceof User ? $record->causer->displayName() : 'system';
    }

    /**
     * Attribute values logged by activitylog can be arrays (e.g. a multiselect
     * field's value) as well as scalars/null — sprintf can't interpolate an
     * array directly.
     */
    private static function stringify(mixed $value): string
    {
        if (is_array($value)) {
            return empty($value) ? '-' : implode(', ', $value);
        }

        return $value === null || $value === '' ? '-' : (string) $value;
    }
}
