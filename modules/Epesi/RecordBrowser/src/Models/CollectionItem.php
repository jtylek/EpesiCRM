<?php

namespace Epesi\Modules\RecordBrowser\Models;

use Epesi\Modules\CommonData\Facades\CommonData;
use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldRegistry;
use Epesi\Modules\RecordBrowser\Models\Concerns\HasCustomFields;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\FieldOverrides;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

/**
 * One item of a collection: an address, or anything else a record can have
 * none or many of that belongs to it alone (see "Collections" in
 * AI-shared/Epesi-custom-fields.md). A collection type is a subclass with a
 * table of its own and a morph alias; a recordset takes one with
 * Field::collection().
 *
 * Every collection table starts with the same columns:
 *
 * - `owner_type`, `owner_id`: the owning record, a morph alias and an id, with
 *   no foreign key, as on the link table;
 * - `field`: the owner's collection field, so a record can hold two
 *   collections of one type, and one an administrator adds needs no DDL;
 * - `kind`: a key into the type's CommonData list (kinds()), nullable;
 * - `position`: the item's place in the field's order. The first item is
 *   the primary one, and the first of a kind that kind's default;
 * - `created_at`, `updated_at`, nullable, so MySQL adds no
 *   ON UPDATE CURRENT_TIMESTAMP.
 *
 * The type's own fields follow, declared with the same Field DSL as a
 * recordset's, each a real column.
 *
 * An item has no page, visibility or History of its own: it is shown and
 * saved through its owner (HasCollections::syncCollection()), whose ownership
 * scope and History cover it. It takes custom fields, so a field an
 * administrator adds to Address shows on every address everywhere.
 *
 * @property string $owner_type
 * @property int $owner_id
 * @property string $field
 * @property ?string $kind
 * @property int $position
 */
abstract class CollectionItem extends Model
{
    use HasCustomFields;

    /** @var array<class-string<self>, array{0: list<string>, 1: array<string, string>}> fillable columns and casts, worked out once per type */
    protected static array $itemColumns = [];

    /**
     * The type's own fields, in the order the form shows them.
     *
     * @return array<int, Field>
     */
    abstract public static function fields(): array;

    /** The CommonData list the items' kinds come from. */
    abstract public static function kinds(): string;

    /** The item on one line, without its kind: "Main St 1, 00-001 Warsaw, Poland". */
    abstract public function summary(): string;

    /**
     * Only the item's values are fillable: the owner, the field and the
     * position are set by syncCollection(), never by what a form sends.
     * Before the parent constructor, which fills $attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        [$columns, $casts] = static::$itemColumns[static::class] ??= static::itemColumns();

        $this->mergeFillable($columns);
        $this->mergeCasts($casts);

        parent::__construct($attributes);
    }

    /**
     * @return array{0: list<string>, 1: array<string, string>} the fillable columns and their casts
     */
    protected static function itemColumns(): array
    {
        $casts = ['owner_id' => 'integer', 'position' => 'integer'];
        $columns = ['kind'];

        foreach (static::fields() as $field) {
            if (! $field->type->hasColumn()) {
                continue;
            }

            $columns[] = $field->name;

            if ($cast = $field->cast()) {
                $casts[$field->name] = $cast;
            }
        }

        return [$columns, $casts];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * fields() plus the administrator's, as RecordsetResource::resolvedFields()
     * does for a recordset. Only the types an item can hold
     * (FieldType::fitsCollectionItem()); Administration → Fields offers no
     * other.
     *
     * @return array<int, Field>
     */
    public static function resolvedFields(): array
    {
        $declared = static::fields();
        $names = array_map(fn (Field $field): string => $field->name, $declared);

        $custom = array_filter(
            CustomFieldRegistry::fieldsFor(static::class),
            fn (Field $field): bool => ! in_array($field->name, $names, true) && $field->type->fitsCollectionItem(),
        );

        return app(FieldOverrides::class)->resolve(static::class, [...$declared, ...array_values($custom)]);
    }

    /** The item's Kind, from the type's CommonData list in the list's own order. */
    public static function kindField(): Field
    {
        return Field::commonData('kind', static::kinds())
            ->param('order', 'position')
            ->label(static::kindFieldLabel())
            ->required(static::kindRequired());
    }

    /** What the type calls its kind: "Service" for an online account. */
    public static function kindFieldLabel(): string
    {
        return 'Kind';
    }

    /** Whether every item needs a kind: a type whose kind decides what its value means. */
    public static function kindRequired(): bool
    {
        return false;
    }

    /**
     * The columns the list's search box and global search look in: the
     * searchable fields', and any the type keeps for searching alone (a
     * phone number's digits).
     *
     * @return list<string>
     */
    public static function searchColumns(): array
    {
        return array_values(array_map(
            fn (Field $field): string => $field->name,
            array_filter(static::resolvedFields(), fn (Field $field): bool => $field->type->hasColumn() && $field->isSearchable()),
        ));
    }

    /** $search as the list's search box looks for it in $column; null leaves the column out. */
    public static function searchTerm(string $column, string $search): ?string
    {
        return $search;
    }

    /** The type's name for one item: "Address". */
    public static function label(): string
    {
        return __(Str::headline(class_basename(static::class)));
    }

    /** The button under the form's items: "Add address". */
    public static function addActionLabel(): string
    {
        return __('Add');
    }

    /**
     * Every collection type: each subclass in the morph map, as a module adds
     * one with a model, a table and an alias.
     *
     * @return array<string, class-string<self>> morph alias => class
     */
    public static function types(): array
    {
        return array_filter(
            Relation::morphMap(),
            fn (mixed $class): bool => is_string($class) && class_exists($class) && is_subclass_of($class, self::class),
        );
    }

    /**
     * @return array<string, string> morph alias => label, for picking a type
     */
    public static function options(): array
    {
        $options = array_map(fn (string $class): string => $class::label(), static::types());

        asort($options);

        return $options;
    }

    /** The kind's label; the raw key when the list no longer has it. */
    public function kindLabel(): ?string
    {
        if (blank($this->kind)) {
            return null;
        }

        return CommonData::array(static::kinds(), 'position')[$this->kind] ?? (string) $this->kind;
    }

    /**
     * What the summary leaves out: the administrator's fields that hold
     * something ("Gate code" => "4412").
     *
     * @return array<string, string> label => value
     */
    public function extraValues(): array
    {
        $declared = array_map(fn (Field $field): string => $field->name, static::fields());
        $values = [];

        foreach (static::resolvedFields() as $field) {
            if (in_array($field->name, $declared, true) || ! $field->isInView() || ! $field->type->hasColumn()) {
                continue;
            }

            $value = $this->getAttribute($field->name);

            if ($value === null || $value === '' || $value === [] || $value === false) {
                continue;
            }

            $values[__($field->getLabel())] = $field->formatLoggedValue($value);
        }

        return $values;
    }

    /** Where the summary links to on the View page (an online account's profile), or null. */
    public function url(): ?string
    {
        return null;
    }

    /**
     * Badges after the summary on the View page, each linking out when it
     * has a URL: a phone number's messengers.
     *
     * @return list<array{label: string, url: ?string}>
     */
    public function links(): array
    {
        return [];
    }

    /**
     * The item as the History addon records it: kind, summary and the
     * details, "Home: Main St 1, Warsaw (Gate code: 4412)". Compared before
     * and after a save, so a change to any of them is logged.
     */
    public function historyLine(): string
    {
        $line = $this->summary();

        if ($details = $this->historyDetails()) {
            $line .= ' ('.implode(', ', $details).')';
        }

        return ($kind = $this->kindLabel()) !== null ? "{$kind}: {$line}" : $line;
    }

    /**
     * What the History line adds in brackets after the summary: the
     * administrator's fields, "Gate code: 4412".
     *
     * @return list<string>
     */
    protected function historyDetails(): array
    {
        $extra = $this->extraValues();

        return array_map(fn (string $label, string $value): string => "{$label}: {$value}", array_keys($extra), array_values($extra));
    }
}
