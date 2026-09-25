<?php

namespace Epesi\Modules\RecordBrowser\Filament\RelationManagers;

use App\Filament\Concerns\TranslatesRelationManagerLabels;
use App\Models\User;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
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
            ->recordTitleAttribute('description')
            ->defaultSort('created_at', 'desc')
            // Event, By and When shrink to their content (a 1% width on an
            // auto-layout table); Changes, last, takes the rest of the row.
            ->columns([
                TextColumn::make('event')
                    ->width('1%'),
                TextColumn::make('causer.name')
                    ->label('By')
                    ->width('1%')
                    ->getStateUsing(fn (Activity $record): string => $record->causer instanceof User
                        ? $record->causer->displayName()
                        : 'system'),
                TextColumn::make('created_at')
                    ->label('When')
                    ->width('1%')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('changes')
                    ->label('Changes')
                    ->getStateUsing(function (Activity $record) use ($fields, $table): string|HtmlString {
                        $old = $record->properties->get('old', collect());
                        $attributes = $record->properties->get('attributes', collect());

                        if (empty($attributes)) {
                            return $record->description;
                        }

                        $changed = collect($attributes)
                            ->reject(fn ($value, $key) => self::stringify(data_get($old, $key)) === self::stringify($value));

                        if ($changed->isEmpty()) {
                            return $record->description;
                        }

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

                        return new HtmlString($changed
                            ->map(fn ($value, $key) => sprintf(
                                $created
                                    ? '%1$s: <span class="epesi-history-new">%3$s</span>'
                                    : '%1$s: <span class="epesi-history-old">%2$s</span> → <span class="epesi-history-new">%3$s</span>',
                                e(__(isset($fields[$key]) ? $fields[$key]->getLabel() : Str::headline($key))),
                                e($format($key, data_get($old, $key))),
                                e($format($key, $value)),
                            ))
                            ->implode(', '));
                    })
                    ->wrap(),
            ])
            ->filters([])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    /**
     * The owner's recordset fields by column name, custom fields included —
     * empty for a resource not built on the engine, whose history keeps
     * the stored values under headline-cased column names.
     *
     * @return array<string, Field>
     */
    protected function loggedFields(): array
    {
        $resource = $this->getPageClass()::getResource();

        if (! is_subclass_of($resource, RecordsetResource::class)) {
            return [];
        }

        return collect($resource::resolvedFields())->keyBy(fn (Field $field): string => $field->name)->all();
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
