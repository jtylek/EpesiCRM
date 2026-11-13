<?php

namespace Epesi\Modules\RecordBrowser\History;

use App\Models\User;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Epesi\Modules\RecordBrowser\Recordset\LinkableRecordsets;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * How one activity_log entry reads as a change summary ("Status: Open → In
 * Progress"), shared by the record History tab and anything else that lists
 * activity entries (e.g. the User Activity Report).
 */
class HistoryChanges
{
    /**
     * The fields of a resource by column name, custom fields included. A
     * resource not built on the engine (e.g. Notes/Attachments, with its own
     * bespoke form) can still describe its fields for History alone via a
     * static `historyFields()` returning the same Field objects; failing
     * that, history keeps the stored values under headline-cased column names.
     *
     * @return array<string, Field>
     */
    public static function fieldsFor(string $resourceClass): array
    {
        $fields = match (true) {
            is_subclass_of($resourceClass, RecordsetResource::class) => $resourceClass::resolvedFields(),
            method_exists($resourceClass, 'historyFields') => $resourceClass::historyFields(),
            default => [],
        };

        $byName = collect($fields)->keyBy(fn (Field $field): string => $field->name);

        // A Customer field logs two plain columns (`{name}_type`/`{name}_id`,
        // a morphTo pair — see Field::customer()), not one; both map to the
        // same Field so summary() can render them as its one historyUsing()
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
     * The fields of the resource that owns the entry's subject, by the
     * subject's morph alias; none when no resource is registered for it.
     *
     * @return array<string, Field>
     */
    public static function fieldsForSubject(string $morphAlias): array
    {
        $resource = LinkableRecordsets::resource($morphAlias);

        return $resource === null ? [] : self::fieldsFor($resource);
    }

    /**
     * What the History `changes` column shows: the description when nothing
     * changed, else one line per changed field.
     *
     * @param  array<string, Field>  $fields
     */
    public static function summary(Activity $entry, array $fields, ?Table $table = null): string|HtmlString
    {
        $changed = self::changed($entry);

        if ($changed->isEmpty()) {
            return $entry->description;
        }

        $old = $entry->properties->get('old', collect());

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
        $created = $entry->event === 'created';
        $seen = [];

        return new HtmlString($changed
            ->map(function ($value, $key) use ($fields, $format, $old, $entry, $created, &$seen): ?string {
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
                $change = $field?->formatLoggedChange(data_get($old, $key), $value, $entry);

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
    }

    /**
     * The entry's new values, less those it logged unchanged.
     *
     * @return Collection<string, mixed>
     */
    public static function changed(Activity $entry): Collection
    {
        $old = $entry->properties->get('old', collect());

        return collect($entry->properties->get('attributes', []))
            ->reject(fn ($value, $key) => self::stringify(data_get($old, $key)) === self::stringify($value));
    }

    public static function causerName(Activity $entry): string
    {
        return $entry->causer instanceof User ? $entry->causer->displayName() : 'system';
    }

    /**
     * Attribute values logged by activitylog can be arrays (e.g. a multiselect
     * field's value) as well as scalars/null — sprintf can't interpolate an
     * array directly.
     */
    public static function stringify(mixed $value): string
    {
        if (is_array($value)) {
            return empty($value) ? '-' : implode(', ', $value);
        }

        return $value === null || $value === '' ? '-' : (string) $value;
    }
}
