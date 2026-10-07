<?php

namespace Epesi\Modules\RecordBrowser\CustomFields;

use Epesi\Modules\RecordBrowser\Models\CollectionItem;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Epesi\Modules\RecordBrowser\Models\RecordLink;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * The only thing in the application that runs DDL from a web request.
 *
 * An administrator-added field is a **real column** on the model's own table,
 * exactly as in Epesi (`new_record_field()` inserts the definition and then runs
 * `ALTER TABLE <tab>_data_1 ADD COLUMN f_<id> …`). The alternative designs were
 * weighed and rejected: a single JSON column costs functional indexing on
 * MariaDB 10.4 (where `JSON` is an alias for `LONGTEXT`) and forces every
 * consumer — sorting, filtering, export, any future reporting — to re-implement
 * a slice of what Eloquent and Filament already do for free; EAV costs a join
 * per field and is the worst possible fit for Filament's query-driven tables.
 *
 * The mitigations that make runtime DDL safe enough to live with:
 *
 * - the `custom_fields` table is the source of truth and the schema is derived
 *   from it, so `sync()` can rebuild or repair every column — which is also how
 *   a second install reproduces a set of fields;
 * - columns are machine-named `cf_<id>`, so no user-supplied string ever reaches
 *   DDL and the `cf_` prefix stays reserved against module migrations;
 * - everything except a boolean is nullable, because a column added to a table
 *   that already has rows cannot be NOT NULL — "required" is validation only;
 * - a per-model cap keeps anyone from walking into InnoDB's row limit by
 *   accident.
 *
 * Some types keep no column at all (FieldType::hasColumn()): `Autonumber` is
 * derived from the row's own key (`Field::formatAutonumber()`), `Related`
 * keeps its links in the shared link table (HasRecordLinks), and `Collection`
 * its items in its collection type's table, keyed by the field
 * (HasCollections). `add()`/`change()`/`sync()` skip them rather than reach
 * `define()`'s `default` arm, which is the actual "this type cannot be
 * administrator-added" refusal for anything else missing here.
 */
class CustomFieldSchema
{
    /**
     * InnoDB allows 1017 columns and a 65 535-byte row; long before either, a
     * table with hundreds of ad-hoc columns is a modelling mistake. Low enough
     * to be a conversation, high enough never to block real use.
     */
    public const MAX_FIELDS_PER_MODEL = 64;

    public function add(CustomField $field): void
    {
        if (! $field->type->hasColumn()) {
            return;
        }

        $table = $this->tableFor($field);

        if (Schema::hasColumn($table, (string) $field->column)) {
            $this->addCurrencyColumn($table, $field);

            return;
        }

        $this->assertUnderLimit($field);

        Schema::table($table, fn (Blueprint $blueprint) => $this->define($blueprint, $field));

        $this->addCurrencyColumn($table, $field);
    }

    /**
     * A Currency field's second column, `cf_N_currency`: the amount's ISO
     * code. Nullable like the amount; added on its own so a field changed from
     * Decimal to Currency gets it too.
     */
    protected function addCurrencyColumn(string $table, CustomField $field): void
    {
        $column = static::currencyColumnOf($field);

        if ($field->type !== FieldType::Currency || Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->char($column, 3)->nullable());
    }

    public static function currencyColumnOf(CustomField $field): string
    {
        return $field->column.'_currency';
    }

    /**
     * Re-issues the column definition after a definition row changed. Only a
     * change that the database can actually carry out gets here — assertChange()
     * refuses the rest rather than silently truncating, which is the same
     * restriction Epesi's own administrator panel applies.
     */
    public function change(CustomField $field): void
    {
        if (! $field->wasChanged(['type', 'params'])) {
            return;
        }

        $table = $this->tableFor($field);

        if (! Schema::hasColumn($table, (string) $field->column)) {
            $this->add($field);

            return;
        }

        if ($field->wasChanged('type')) {
            $this->assertChangeAllowed($this->originalType($field), $field->type);
        }

        Schema::table($table, fn (Blueprint $blueprint) => $this->define($blueprint, $field)->change());

        $this->addCurrencyColumn($table, $field);
    }

    /**
     * Removes the column *and everything stored in it*. Deliberately not what
     * deleting a definition does — that only hides the field — for the same
     * reason uninstalling a module leaves its tables alone.
     */
    public function drop(CustomField $field): void
    {
        // Its "column" is its rows in the shared link table.
        if (in_array($field->type, [FieldType::Related, FieldType::Relations], true)) {
            RecordLink::query()->where('source_type', $field->model_type)->where('field', $field->column)->delete();

            return;
        }

        // And a collection's is its items, rows of its type's table.
        if ($field->type === FieldType::Collection) {
            $type = Relation::getMorphedModel((string) ($field->params['collection'] ?? ''));

            if (is_string($type) && is_subclass_of($type, CollectionItem::class)) {
                $type::query()->where('owner_type', $field->model_type)->where('field', $field->column)->delete();
            }

            return;
        }

        $table = $this->tableFor($field);

        $columns = array_values(array_filter(
            [(string) $field->column, static::currencyColumnOf($field)],
            fn (string $column): bool => ($column === $field->column || $field->type === FieldType::Currency) && Schema::hasColumn($table, $column),
        ));

        if ($columns === []) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($columns));
    }

    /**
     * Rebuilds any column a definition says should exist but the schema is
     * missing — the disaster-recovery path, and how a set of definitions copied
     * to a second install materialises there.
     *
     * @return array<int, string> human-readable description of what was created
     */
    public function sync(): array
    {
        $created = [];

        foreach (CustomField::query()->orderBy('model_type')->orderBy('id')->get() as $field) {
            if (! $field->type->hasColumn()) {
                continue;
            }

            $table = $field->modelTable();

            if ($table === null || ! Schema::hasTable($table)) {
                continue;
            }

            if (Schema::hasColumn($table, (string) $field->column)
                && ($field->type !== FieldType::Currency || Schema::hasColumn($table, static::currencyColumnOf($field)))) {
                continue;
            }

            $this->add($field);

            $created[] = "{$table}.{$field->column} ({$field->model_type}.{$field->name})";
        }

        return $created;
    }

    /**
     * Widening conversions only. Anything else — a date back to an integer, a
     * long text down to 255 characters — either loses data or fails halfway
     * through, and telling the administrator no is more honest than either.
     */
    protected function assertChangeAllowed(FieldType $from, FieldType $to): void
    {
        $allowed = [
            FieldType::Text->value => [FieldType::LongText, FieldType::Email, FieldType::Url, FieldType::Phone, FieldType::Select],
            FieldType::Email->value => [FieldType::Text, FieldType::LongText],
            FieldType::Url->value => [FieldType::Text, FieldType::LongText],
            FieldType::Phone->value => [FieldType::Text, FieldType::LongText],
            // An amount gains a currency column, which starts empty.
            FieldType::Integer->value => [FieldType::Decimal, FieldType::Currency],
            FieldType::Decimal->value => [FieldType::Currency],
            FieldType::Select->value => [FieldType::Text, FieldType::LongText],
        ];

        if ($from === $to || in_array($to, $allowed[$from->value] ?? [], true)) {
            return;
        }

        throw new RuntimeException(
            "A {$from->label()} field cannot be changed to {$to->label()} — the existing values would not survive it. "
            .'Add a new field and migrate the data instead.',
        );
    }

    protected function originalType(CustomField $field): FieldType
    {
        $original = $field->getOriginal('type');

        return $original instanceof FieldType ? $original : FieldType::from((string) $original);
    }

    protected function define(Blueprint $blueprint, CustomField $field): ColumnDefinition
    {
        $column = (string) $field->column;
        $params = (array) ($field->params ?? []);

        $definition = match ($field->type) {
            FieldType::Text, FieldType::Email, FieldType::Url, FieldType::Phone, FieldType::Select => $blueprint->string($column, (int) ($params['length'] ?? 255)),
            FieldType::LongText => $blueprint->text($column),
            FieldType::Integer => $blueprint->bigInteger($column),
            // A Currency field's amount; addCurrencyColumn() adds its code.
            FieldType::Decimal, FieldType::Currency => $blueprint->decimal($column, 15, (int) ($params['decimals'] ?? 2)),
            FieldType::Date => $blueprint->date($column),
            FieldType::DateTime => $blueprint->dateTime($column),
            FieldType::Time => $blueprint->time($column),
            // StoredFile ids (StoredFileIds), the files themselves being in the
            // shared file storage.
            FieldType::Multiselect, FieldType::File => $blueprint->json($column),

            // The linked record's key. No foreign key: uninstalling the target
            // recordset's module must not break this table, and a key whose
            // record has gone shows as "#id" (Field::loggedRelatedTitle()).
            FieldType::Relation => $blueprint->unsignedBigInteger($column),

            // One key, or several as JSON — the same shapes a module's own
            // commondata field takes (Field::cast()).
            FieldType::CommonData => ($params['multiple'] ?? false) ? $blueprint->json($column) : $blueprint->string($column, 128),

            // A boolean is the one type that can be NOT NULL: adding a column
            // with a default fills existing rows with it, so there is never a
            // null to render as a third state.
            FieldType::Boolean => $blueprint->boolean($column)->default(false),

            default => throw new RuntimeException(
                "{$field->type->label()} fields cannot be added from the administration screen.",
            ),
        };

        return $field->type === FieldType::Boolean ? $definition : $definition->nullable();
    }

    protected function tableFor(CustomField $field): string
    {
        $table = $field->modelTable();

        if ($table === null) {
            throw new RuntimeException("No model is registered for \"{$field->model_type}\".");
        }

        return $table;
    }

    protected function assertUnderLimit(CustomField $field): void
    {
        $existing = CustomField::query()
            ->where('model_type', $field->model_type)
            ->where('id', '!=', $field->getKey())
            ->count();

        if ($existing >= self::MAX_FIELDS_PER_MODEL) {
            throw new RuntimeException(
                "{$field->model_type} already has ".self::MAX_FIELDS_PER_MODEL
                .' custom fields, the limit for one recordset.',
            );
        }
    }
}
