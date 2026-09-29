<?php

namespace Epesi\Modules\RecordBrowser\Recordset;

use App\Models\StoredFile;
use App\Services\FileStorage;
use App\Support\Files\FileChip;
use BackedEnum;
use Closure;
use Epesi\Modules\CommonData\Facades\CommonData;
use Epesi\Modules\RecordBrowser\Extensions\RecordExtensions;
use Epesi\Modules\RecordBrowser\Filament\LinkedRecords;
use Epesi\Modules\RecordBrowser\Files\StoredFileIds;
use Epesi\Modules\RecordBrowser\History\TextDiff;
use Epesi\Modules\RecordBrowser\Models\CollectionItem;
use Epesi\Modules\RecordBrowser\Models\RecordLink;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\TextSize;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * One field of a recordset, and the single source every screen is built from —
 * the port of one row of Epesi's `<table>_field` table.
 *
 * A module declares these in its resource's `fields()`; an administrator's
 * additions arrive as more of the same objects (CustomFieldRegistry builds them
 * from the `custom_fields` table), so both halves go through one code path and
 * a GUI-added field is indistinguishable from a shipped one. That property is
 * the whole point of RecordBrowser and the reason this class renders the
 * components itself rather than handing back a description for someone else to
 * interpret.
 *
 * Everything is chainable and nothing is required beyond the name:
 *
 *     Field::text('title')->required()->inTable(),
 *     Field::relation('contact_id', Contact::class)->label('Contact')->inTable(),
 *     Field::boolean('pinned'),
 *     Field::longText('content')->section('Details'),
 *
 * **Escape hatches are first-class.** `->formUsing()`, `->viewUsing()`,
 * `->columnUsing()` and `->filterUsing()` receive the component the engine
 * built and return a modified (or entirely different) one. Epesi's own
 * long-term lesson is that recordsets which outgrow pure declaration end up
 * carrying callbacks anyway — `CRM/Contacts` uses six — and an engine that
 * makes overriding awkward pushes developers off it altogether.
 */
class Field
{
    protected ?string $label = null;

    protected bool $required = false;

    /** @var array<string, mixed> */
    protected array $params = [];

    protected ?string $section = null;

    protected bool $sectionCollapsible = false;

    protected bool $sectionCollapsed = false;

    protected ?string $sectionDescription = null;

    protected bool $inForm = true;

    protected bool $listedOnTarget = true;

    public function listedOnTarget(bool $listed = true): static
    {
        $this->listedOnTarget = $listed;

        return $this;
    }

    public function isListedOnTarget(): bool
    {
        return $this->listedOnTarget;
    }

    protected bool $inView = true;

    /** Shown as a table column without the user turning it on. */
    protected bool $inTable = false;

    /** Offered in the column chooser at all. */
    protected bool $tableToggleable = true;

    protected bool $filterable = false;

    /** A Collection field's filter: item fields only (e.g. City, Country), without the Has/Kind inputs. */
    protected bool $collectionFilterItemFieldsOnly = false;

    protected ?bool $searchable = null;

    protected ?bool $sortable = null;

    protected bool $fullWidth = false;

    /** Value is HTML from a rich-text editor, e.g. a resource's own RichEditor outside the engine's form builder — formatLoggedValue() strips markup for the History addon instead of showing it raw. */
    protected bool $richText = false;

    protected ?string $help = null;

    protected ?string $placeholder = null;

    protected mixed $default = null;

    protected ?Closure $formUsing = null;

    protected ?Closure $viewUsing = null;

    protected ?string $tooltipAttribute = null;

    protected ?Closure $columnUsing = null;

    protected ?Closure $filterUsing = null;

    protected ?Closure $crits = null;

    /** @var (Closure(mixed, mixed, Activity, bool): (string|Htmlable|null))|null see historyUsing() */
    protected ?Closure $historyUsing = null;

    /** Set while the select builds its offered list — see offerOnlyCrits(). */
    protected bool $offering = false;

    /** @var array<int|string, Model|false> related records looked up for the History addon */
    protected array $loggedRelated = [];

    final protected function __construct(
        public readonly string $name,
        public readonly FieldType $type,
    ) {}

    public static function make(string $name, FieldType $type): static
    {
        return new static($name, $type);
    }

    public static function text(string $name): static
    {
        return static::make($name, FieldType::Text)->maxLength(255);
    }

    public static function longText(string $name): static
    {
        return static::make($name, FieldType::LongText)->fullWidth();
    }

    public static function integer(string $name): static
    {
        return static::make($name, FieldType::Integer);
    }

    public static function decimal(string $name, int $decimals = 2): static
    {
        return static::make($name, FieldType::Decimal)->param('decimals', $decimals);
    }

    public static function boolean(string $name): static
    {
        return static::make($name, FieldType::Boolean);
    }

    public static function date(string $name): static
    {
        return static::make($name, FieldType::Date);
    }

    public static function dateTime(string $name): static
    {
        return static::make($name, FieldType::DateTime);
    }

    public static function time(string $name): static
    {
        return static::make($name, FieldType::Time);
    }

    public static function email(string $name): static
    {
        return static::make($name, FieldType::Email)->maxLength(255);
    }

    public static function url(string $name): static
    {
        return static::make($name, FieldType::Url)->maxLength(255);
    }

    public static function phone(string $name): static
    {
        return static::make($name, FieldType::Phone)->maxLength(64);
    }

    /**
     * @param  class-string<BackedEnum>|array<string, string>  $options  a backed enum class, or value => label
     */
    public static function select(string $name, string|array $options): static
    {
        return static::make($name, FieldType::Select)->param('options', $options);
    }

    /**
     * @param  class-string<BackedEnum>|array<string, string>  $options
     */
    public static function multiselect(string $name, string|array $options): static
    {
        return static::make($name, FieldType::Multiselect)->param('options', $options);
    }

    /**
     * A select drawing its options from a shared reference list an
     * administrator maintains (Administration -> Common Data), rather than from
     * an enum fixed in code. Epesi's `commondata` field type.
     *
     * @param  string  $array  path into the tree, e.g. "Contacts_Groups"
     * @param  bool  $multiple  several values at once — Epesi spells this as a
     *                          `multiselect` whose array_id starts `__COMMON__`
     */
    public static function commonData(string $name, string $array, bool $multiple = false): static
    {
        return static::make($name, FieldType::CommonData)
            ->param('array', $array)
            ->param('multiple', $multiple);
    }

    /**
     * A belongsTo. $name is the foreign key column ("contact_id"); the
     * relationship name is derived from it ("contact") unless given.
     *
     * @param  class-string<Model>  $model
     */
    public static function relation(string $name, string $model, ?string $relationship = null): static
    {
        return static::make($name, FieldType::Relation)
            ->param('model', $model)
            ->param('relationship', $relationship ?? Str::camel(Str::beforeLast($name, '_id')));
    }

    /**
     * A belongsToMany. Here $name *is* the relationship name ("employees"),
     * since there is no column to name it after.
     *
     * @param  class-string<Model>  $model
     */
    public static function relations(string $name, string $model): static
    {
        return static::make($name, FieldType::Relations)
            ->param('model', $model)
            ->param('relationship', $name);
    }

    /**
     * A formatted number derived from the record's key — Epesi's `autonumber`
     * (`decode_autonumber_param()`'s three-part param, e.g. "#__4__0" for
     * "#0042"). Legacy stores the formatted string in a real column and
     * rewrites every row when the format changes
     * (`format_autonumber_str_all_records()`); deriving it from the key
     * instead needs no column, no migration and no backfill when $prefix or
     * $padLength changes later.
     */
    public static function autonumber(string $name, string $prefix = '', int $padLength = 4, string $padMask = '0'): static
    {
        return static::make($name, FieldType::Autonumber)
            ->param('prefix', $prefix)
            ->param('pad_length', $padLength)
            ->param('pad_mask', $padMask);
    }

    /**
     * Files kept in the shared file storage (App\Services\FileStorage), as
     * legacy's `file` field. The column is JSON holding StoredFile ids: a
     * module casts it to StoredFileIds and uses HasFileFields (HasCustomFields
     * brings it), which releases the files when they're taken off.
     */
    public static function file(string $name, bool $multiple = true, int $maxSizeMb = 50): static
    {
        return static::make($name, FieldType::File)
            ->param('multiple', $multiple)
            ->param('max_size_mb', $maxSizeMb);
    }

    /**
     * Links to records of any recordset — Epesi's `__RECORDSETS__` select, the
     * Related field on tasks, meetings and phone calls. Kept in one shared
     * table (HasRecordLinks, which HasCustomFields brings), not a column.
     * $recordsets (model classes or morph aliases) narrows what it offers;
     * null offers every recordset with a View page (LinkableRecordsets).
     *
     * @param  list<string>|null  $recordsets
     */
    public static function related(string $name, ?array $recordsets = null): static
    {
        return static::make($name, FieldType::Related)->param('recordsets', $recordsets === null ? null : array_values(array_map(
            fn (string $one): string => class_exists($one) ? Relation::getMorphAlias($one) : $one,
            $recordsets,
        )));
    }

    /**
     * What a record has none or many of and owns alone — its addresses:
     * items of the collection type $type (a CollectionItem subclass, or its
     * morph alias), each a row of the type's own table rather than a column
     * here (HasCollections, which HasCustomFields brings). The form repeats
     * the type's fields once per item; the first item is the primary one.
     *
     * @param  class-string<CollectionItem>|string  $type
     */
    public static function collection(string $name, string $type): static
    {
        return static::make($name, FieldType::Collection)
            ->param('collection', class_exists($type) ? $type : (Relation::getMorphedModel($type) ?? $type));
    }

    /** At most this many items in a collection: a meeting's one location. */
    public function maxItems(int $count): static
    {
        return $this->param('max_items', $count);
    }

    /**
     * In the list, the first item of each kind named here as a column of its
     * own, instead of one column for the primary item: a contact's Work and
     * Mobile phones. Each column is labelled as given, else by its kind.
     *
     * @param  array<int|string, string>  $kinds  kind keys, or kind => column label
     */
    public function columnsForKinds(array $kinds): static
    {
        return $this->param('kind_columns', $kinds);
    }

    /**
     * @return class-string<CollectionItem>|null a collection field's type, null
     *                                           when it isn't installed
     */
    public function collectionType(): ?string
    {
        $type = $this->getParam('collection');

        if (is_string($type) && ! class_exists($type)) {
            $type = Relation::getMorphedModel($type);
        }

        return is_string($type) && is_subclass_of($type, CollectionItem::class) ? $type : null;
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label ?? Str::headline($this->name);
    }

    /** @var list<string>|null Additional module restrictions on administrator settings. */
    protected ?array $administratorProperties = null;

    public function administratorEditable(array $properties): static
    {
        $this->administratorProperties = $properties;

        return $this;
    }

    public function administratorDefaults(): array
    {
        return [
            'label' => $this->getLabel(),
            'help' => $this->help,
            'section' => $this->getSection(),
            'show_in_form' => $this->inForm,
            'show_in_view' => $this->inView,
            'show_in_table' => $this->inTable,
            'filterable' => $this->filterable,
            'required' => $this->required,
        ];
    }

    public function administratorEditableProperties(): array
    {
        $properties = ['label', 'help', 'section', 'position', 'show_in_view'];

        if ($this->isInColumnChooser()) {
            $properties[] = 'show_in_table';
        }

        if ($this->inForm && ! $this->required && $this->type !== FieldType::Autonumber) {
            $properties[] = 'show_in_form';
            $properties[] = 'required';
        }

        if ($this->type !== FieldType::Time && $this->type !== FieldType::Autonumber) {
            $properties[] = 'filterable';
        }

        return $this->administratorProperties === null
            ? $properties
            : array_values(array_intersect($properties, $this->administratorProperties));
    }

    public function withAdministratorProperties(array $properties): static
    {
        $field = clone $this;
        $mapping = [
            'label' => 'label', 'help' => 'help', 'section' => 'section',
            'show_in_form' => 'inForm', 'show_in_view' => 'inView',
            'show_in_table' => 'inTable', 'filterable' => 'filterable', 'required' => 'required',
        ];

        foreach (array_intersect_key($properties, array_flip($this->administratorEditableProperties())) as $key => $value) {
            if (isset($mapping[$key])) {
                $field->{$mapping[$key]} = $value;
            }
        }

        return $field;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function maxLength(int $length): static
    {
        return $this->param('length', $length);
    }

    /**
     * How many minutes apart the times a Time or DateTime field offers are —
     * legacy's "Minutes Interval" on a `time`/`timestamp` field. 60 offers
     * full hours only.
     */
    public function minutesStep(int $minutes): static
    {
        return $this->param('minutes_step', $minutes);
    }

    public function param(string $key, mixed $value): static
    {
        $this->params[$key] = $value;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function params(array $params): static
    {
        $this->params = [...$this->params, ...$params];

        return $this;
    }

    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * The named group this field appears in on the form and the view — the port
     * of Epesi's `page_split` pseudo-field, which is likewise a property of the
     * field list rather than a thing you position by hand.
     *
     * A section's collapsed/collapsible state is taken from the *first* field
     * that names it, so the options only need stating once per section.
     */
    public function section(
        ?string $section,
        bool $collapsible = false,
        bool $collapsed = false,
        ?string $description = null,
    ): static {
        $this->section = $section;
        $this->sectionCollapsible = $collapsible || $collapsed;
        $this->sectionCollapsed = $collapsed;
        $this->sectionDescription = $description;

        return $this;
    }

    public function getSection(): ?string
    {
        return $this->section;
    }

    public function isSectionCollapsible(): bool
    {
        return $this->sectionCollapsible;
    }

    public function isSectionCollapsed(): bool
    {
        return $this->sectionCollapsed;
    }

    public function getSectionDescription(): ?string
    {
        return $this->sectionDescription;
    }

    public function fullWidth(bool $fullWidth = true): static
    {
        $this->fullWidth = $fullWidth;

        return $this;
    }

    /** Spans both columns: asked for with fullWidth(), and always for long text and a collection's cards. */
    public function isFullWidth(): bool
    {
        return $this->fullWidth || in_array($this->type, [FieldType::LongText, FieldType::Collection], true);
    }

    public function richText(): static
    {
        $this->richText = true;

        return $this;
    }

    /**
     * How the History addon shows a change of this field when one value at a
     * time can't tell it: the callback gets the old and the new value
     * together (null for a record just created), the activity entry (for
     * anything the model logged beside them) and whether the whole change is
     * wanted (the Show modal) or a line's worth. It returns escaped HTML, or
     * null to fall back to "old → new".
     *
     * @param  Closure(mixed $old, mixed $new, Activity $entry, bool $whole): (string|Htmlable|null)  $callback
     */
    public function historyUsing(Closure $callback): static
    {
        $this->historyUsing = $callback;

        return $this;
    }

    /**
     * Shows another attribute of the record as the list column's tooltip on
     * hover — a title that reveals its description. Long text is cut short.
     */
    public function tooltipFrom(string $attribute): static
    {
        $this->tooltipAttribute = $attribute;

        return $this;
    }

    /** Shown as a table column without the user having to turn it on. */
    public function inTable(bool $inTable = true): static
    {
        $this->inTable = $inTable;

        return $this;
    }

    public function isInTable(): bool
    {
        return $this->inTable;
    }

    /**
     * Keeps the field off the list entirely — not shown, and not offered in the
     * column chooser either. The default sits between the two: available, but
     * off until someone turns it on.
     */
    public function notInTable(): static
    {
        $this->inTable = false;
        $this->tableToggleable = false;

        return $this;
    }

    /** Whether the list should carry a column for this field at all. */
    public function isInColumnChooser(): bool
    {
        return $this->inTable || $this->tableToggleable;
    }

    public function inForm(bool $inForm = true): static
    {
        $this->inForm = $inForm;

        return $this;
    }

    public function isInForm(): bool
    {
        return $this->inForm;
    }

    public function inView(bool $inView = true): static
    {
        $this->inView = $inView;

        return $this;
    }

    public function isInView(): bool
    {
        return $this->inView;
    }

    /**
     * A column and nothing else — the shape a read-only or derived value takes
     * (`updated_at` on a list, an autonumber), where a form input and an
     * infolist entry would both be wrong.
     */
    public function onlyInTable(): static
    {
        return $this->inForm(false)->inView(false)->inTable();
    }

    public function filterable(bool $filterable = true, bool $itemFieldsOnly = false): static
    {
        $this->filterable = $filterable;
        $this->collectionFilterItemFieldsOnly = $itemFieldsOnly;

        return $this;
    }

    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function sortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;

        return $this;
    }

    /** Whether the list's search box looks in this field: as set, else by its type. */
    public function isSearchable(): bool
    {
        return $this->searchable ?? $this->isSearchableByDefault();
    }

    public function help(?string $help): static
    {
        $this->help = $help;

        return $this;
    }

    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    /**
     * @param  Closure(mixed, static): mixed  $callback  receives the component the engine built
     */
    public function formUsing(Closure $callback): static
    {
        $this->formUsing = $callback;

        return $this;
    }

    /**
     * @param  Closure(mixed, static): mixed  $callback
     */
    public function viewUsing(Closure $callback): static
    {
        $this->viewUsing = $callback;

        return $this;
    }

    /**
     * @param  Closure(mixed, static): mixed  $callback
     */
    public function columnUsing(Closure $callback): static
    {
        $this->columnUsing = $callback;

        return $this;
    }

    /**
     * @param  Closure(mixed, static): mixed  $callback
     */
    public function filterUsing(Closure $callback): static
    {
        $this->filterUsing = $callback;

        return $this;
    }

    /**
     * The attribute Eloquent stores this under — the foreign key for a
     * belongsTo, the relationship name for a belongsToMany, the column
     * otherwise.
     */
    public function getStateName(): string
    {
        return $this->type === FieldType::Relations
            ? (string) $this->getParam('relationship')
            : $this->name;
    }

    /**
     * The `$casts` entry this field needs, or null when Eloquent's default
     * handling is already right.
     */
    public function cast(): ?string
    {
        $options = $this->getParam('options');

        return match ($this->type) {
            FieldType::Boolean => 'boolean',
            FieldType::Date => 'date',
            FieldType::DateTime => 'datetime',
            FieldType::Decimal => 'decimal:'.((int) $this->getParam('decimals', 2)),
            FieldType::Integer => 'integer',
            FieldType::Multiselect => 'array',
            FieldType::CommonData => $this->isMultipleValued() ? 'array' : null,
            FieldType::Select => is_string($options) && enum_exists($options) ? $options : null,
            FieldType::File => StoredFileIds::class,
            default => null,
        };
    }

    // ---------------------------------------------------------------- Form --

    public function toFormComponent(): mixed
    {
        $component = $this->buildFormComponent()
            ->label($this->getLabel())
            ->required($this->required)
            ->helperText($this->help);

        if ($this->isFullWidth()) {
            $component = $component->columnSpanFull();
        }

        if ($this->default !== null) {
            $component = $component->default($this->default);
        }

        return $this->formUsing ? ($this->formUsing)($component, $this) : $component;
    }

    protected function buildFormComponent(): mixed
    {
        return match ($this->type) {
            FieldType::LongText => Textarea::make($this->name)->rows(6),
            FieldType::Integer => TextInput::make($this->name)->integer(),
            FieldType::Decimal => TextInput::make($this->name)->numeric(),
            FieldType::Boolean => Toggle::make($this->name),
            FieldType::Date => DatePicker::make($this->name),
            // minutesStep() covers both pickers: the JavaScript one steps its
            // minute input, the native one gets it as `step` in seconds.
            FieldType::DateTime => DateTimePicker::make($this->name)->seconds(false)->minutesStep($this->minutesStepParam()),
            FieldType::Time => TimePicker::make($this->name)->seconds(false)->minutesStep($this->minutesStepParam()),
            FieldType::Email => TextInput::make($this->name)->email()->maxLength($this->lengthParam()),
            // Not ->url(): that demands a scheme, and people type (and legacy
            // holds) "www.example.com". A host, optional port and path is
            // enough — webAddressUrl() adds the scheme when it links.
            FieldType::Url => TextInput::make($this->name)
                ->rule('regex:/^(https?:\/\/)?[^\s:\/]+(:\d+)?(\/\S*)?$/i')
                ->maxLength($this->lengthParam()),
            FieldType::Phone => TextInput::make($this->name)->tel()->maxLength($this->lengthParam()),
            FieldType::Select => Select::make($this->name)->options($this->options()),
            FieldType::Multiselect => Select::make($this->name)->options($this->options())->multiple(),
            FieldType::CommonData => Select::make($this->name)
                ->options(fn (): array => $this->commonDataOptions())
                ->multiple($this->isMultipleValued())
                ->searchable(),
            FieldType::Relation, FieldType::Relations => $this->relationSelect(),
            FieldType::File => $this->fileUpload(),
            FieldType::Related => $this->relatedSelect(),
            FieldType::Collection => $this->collectionRepeater(),
            // Read-only: the key it derives from doesn't exist until the
            // record is saved, so nothing here is ever submitted. Set from
            // afterStateHydrated() rather than state() — a form field's
            // state() writes into the Livewire component immediately, which
            // needs a container this doesn't have yet while still being built.
            FieldType::Autonumber => TextInput::make($this->name)
                ->disabled()
                ->dehydrated(false)
                ->afterStateHydrated(fn (TextInput $component, ?Model $record) => $component->state($this->formatAutonumber($record?->getKey()))),
            default => TextInput::make($this->name)->maxLength($this->lengthParam()),
        };
    }

    protected function relationSelect(): Select
    {
        $relationship = (string) $this->getParam('relationship');
        $resource = $this->relatedResource();

        $select = Select::make($this->getStateName())
            ->relationship($relationship, $this->relatedTitleAttribute(), $this->critsModifier())
            ->searchable()
            ->preload();

        if ($this->crits) {
            $select = $this->offerOnlyCrits($select);
        }

        if ($resource) {
            $select = $select->getOptionLabelFromRecordUsing(
                fn (Model $record): string => (string) $resource::getRecordTitle($record),
            );
        }

        return $this->type === FieldType::Relations ? $select->multiple() : $select;
    }

    /**
     * One search over every recordset the field offers; each value is a token
     * ("company:12"). No column to fill: the links are saved after the record
     * (saveRelationshipsUsing), through syncRecordLinks(), which leaves a link
     * to a record this user can't see alone — it was never in their form.
     */
    protected function relatedSelect(): Select
    {
        return Select::make($this->name)
            ->multiple()
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => $this->relatedSearch($search))
            ->getOptionLabelsUsing(fn (array $values): array => $this->relatedLabels($values))
            ->afterStateHydrated(fn (Select $component, ?Model $record) => $component->state(
                $this->linkedRecordsOf($record)
                    ->map(fn (Model $linked): string => RecordLink::tokenFor($linked->getMorphClass(), $linked->getKey()))
                    ->all(),
            ))
            ->dehydrated(false)
            ->saveRelationshipsUsing(fn (Model $record, ?array $state) => method_exists($record, 'syncRecordLinks')
                ? $record->syncRecordLinks($this->name, $state ?? [], $this->relatedRecordsets())
                : null);
    }

    /**
     * The morph aliases this field offers: its own list, as far as those are
     * linkable, or every linkable recordset.
     *
     * @return list<string>
     */
    public function relatedRecordsets(): array
    {
        $linkable = array_keys(LinkableRecordsets::options());
        $own = $this->getParam('recordsets');

        return is_array($own) && $own !== [] ? array_values(array_intersect($own, $linkable)) : $linkable;
    }

    /**
     * Records matching $search in every offered recordset, by the columns its
     * resource searches globally — as the Notes addon's "Attached to" picker.
     *
     * @return array<string, string> token => label
     */
    public function relatedSearch(string $search): array
    {
        $results = [];

        foreach ($this->relatedRecordsets() as $alias) {
            $resource = LinkableRecordsets::resource($alias);
            $columns = array_filter($resource::getGloballySearchableAttributes(), fn (string $column): bool => ! str_contains($column, '.'));

            if ($columns === []) {
                continue;
            }

            $matches = Relation::getMorphedModel($alias)::query()
                ->where(function (Builder $query) use ($columns, $search): void {
                    foreach ($columns as $column) {
                        $query->orWhere($column, 'like', "%{$search}%");
                    }
                })
                ->limit(10)
                ->get();

            foreach ($matches as $match) {
                $results[RecordLink::tokenFor($alias, $match->getKey())] = $this->relatedLabel($match);
            }
        }

        return $results;
    }

    /**
     * @param  array<int, mixed>  $tokens
     * @return array<string, string> token => label, for the records this user can see
     */
    protected function relatedLabels(array $tokens): array
    {
        $ids = [];

        foreach ($tokens as $token) {
            if ($parsed = RecordLink::parseToken($token)) {
                $ids[$parsed[0]][] = $parsed[1];
            }
        }

        $labels = [];

        foreach ($ids as $alias => $keys) {
            $class = Relation::getMorphedModel($alias);

            if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            foreach ($class::query()->whereKey($keys)->get() as $record) {
                $labels[RecordLink::tokenFor($alias, $record->getKey())] = $this->relatedLabel($record);
            }
        }

        return $labels;
    }

    /** "Company: Acme Ltd" — which recordset, then the record's own title. */
    protected function relatedLabel(Model $record): string
    {
        return LinkableRecordsets::label($record->getMorphClass()).': '.LinkedRecords::title($record);
    }

    /**
     * @return Collection<int, Model>
     */
    protected function linkedRecordsOf(?Model $record): Collection
    {
        return $record?->exists && method_exists($record, 'linkedRecords')
            ? collect($record->linkedRecords($this->name)->all())
            : collect();
    }

    /**
     * A list page's links, loaded for the whole page when its first row asks —
     * a query for the links and one per recordset they point at, rather than
     * two per row.
     */
    protected function preloadRecordLinks(Column $column): void
    {
        try {
            $records = $column->getTable()->getRecords();
        } catch (Throwable) {
            return;
        }

        $records = $records instanceof EloquentCollection ? $records : (method_exists($records, 'getCollection') ? $records->getCollection() : null);

        $first = $records instanceof EloquentCollection ? $records->first() : null;

        if ($first instanceof Model && method_exists($first, 'recordLinks')) {
            $records->loadMissing('recordLinks.target');
        }
    }

    /**
     * Straight into the file storage, as the Notes addon's upload field: the
     * state is StoredFile ids, never paths on a disk.
     */
    protected function fileUpload(): FileUpload
    {
        return FileUpload::make($this->name)
            ->multiple($this->isMultipleValued())
            ->maxSize(max(1, (int) $this->getParam('max_size_mb', 50)) * 1024)
            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => (string) app(FileStorage::class)->putUpload($file)->getKey())
            ->fetchFileInformation(false)
            ->getUploadedFileUsing(fn (?Model $record, string $file): ?array => $this->uploadedFile($record, $file))
            // Only the record's own files and new uploads: an id typed into
            // the request would otherwise attach someone else's file.
            ->preventFilePathTampering();
    }

    /**
     * What the upload field shows for a file the record already holds — and
     * only for those, so an id slipped into the form's state names nothing.
     *
     * @return array{name: string, size: int, type: ?string, url: string}|null
     */
    protected function uploadedFile(?Model $record, string $id): ?array
    {
        if (! $record?->exists || ! in_array($id, StoredFileIds::decode($record->getAttribute($this->name)), true)) {
            return null;
        }

        $file = StoredFile::query()->with('content')->find($id);

        return $file === null ? null : [
            'name' => $file->name,
            'size' => $file->size(),
            'type' => $file->mimeType(),
            'url' => $this->fileUrl($record, $file),
        ];
    }

    /**
     * Where one of a record's files is served (RecordFileController), behind
     * the same "may you see this record" check as its View page. $preview asks
     * for it inline, which the controller only grants to a previewable type.
     */
    public function fileUrl(Model $record, StoredFile $file, bool $preview = false): string
    {
        return route('epesi.records.file', array_filter([
            'type' => $record->getMorphClass(),
            'id' => $record->getKey(),
            'field' => $this->name,
            'file' => $file->getKey(),
            'preview' => $preview ? 1 : null,
        ]));
    }

    /**
     * The record's files as pills — the same FileChip the Notes addon and the
     * Mail archive show, so a file looks and opens the same everywhere.
     * $limit keeps a list row short: the first few, then "+N". Null when there
     * are none, so the entry shows its placeholder.
     */
    protected function fileChips(Model $record, ?int $limit = null): ?HtmlString
    {
        // HasFileFields; without it the model's files aren't looked after, and
        // recordset:check says so.
        $files = method_exists($record, 'storedFiles') ? $record->storedFiles($this->name) : collect();

        if ($files->isEmpty()) {
            return null;
        }

        $shown = $limit === null ? $files : $files->take($limit);

        $html = $shown->map(fn (StoredFile $file): string => FileChip::render(
            $file,
            $this->fileUrl($record, $file),
            $file->isPreviewable() ? $this->fileUrl($record, $file, preview: true) : null,
        ))->implode('');

        if ($files->count() > $shown->count()) {
            $html .= ' <span class="text-sm text-gray-500">+'.($files->count() - $shown->count()).'</span>';
        }

        return new HtmlString($html);
    }

    // ---------------------------------------------------------- Collection --

    /**
     * One card per item, its Kind and the type's fields, reorderable: the
     * first card is the primary item. Filled from the relation and saved
     * after the record through syncCollection(), as the Related select is —
     * not Repeater::relationship(), which saves the rows itself and would
     * bypass the History entry and the items' own events. Each card keeps
     * its item's id, which syncCollection() only honours for this field's
     * own items.
     */
    protected function collectionRepeater(): Repeater
    {
        $type = $this->collectionType();

        $repeater = Repeater::make($this->name)
            ->schema(fn (): array => $type === null ? [] : [
                Hidden::make('id'),
                $type::kindField()->toFormComponent(),
                ...array_map(
                    fn (Field $field): mixed => $field->toFormComponent(),
                    array_values(array_filter($type::resolvedFields(), fn (Field $field): bool => $field->isInForm())),
                ),
            ])
            ->columns(2)
            ->defaultItems(0)
            // A saved item starts collapsed to its label, which already says
            // what it holds ("Work: +48 22 555 01 01"), to keep a record's
            // form short; a card just added opens to be filled in.
            ->collapsed(fn (?Schema $item): bool => filled(data_get($item?->getRawState(), 'id')))
            // One row above the cards (the epesi-collection-repeater styles):
            // Add first, then Collapse all and Expand all as one toggle, which
            // starts from how the cards start.
            ->extraAttributes(fn (Repeater $component): array => [
                'class' => 'epesi-collection-repeater',
                'x-data' => '{ allCollapsed: '.Js::from($this->allItemsSaved($component)).' }',
            ])
            // Orange, as New and Edit are.
            ->addAction(fn (Action $action): Action => $action->icon(Heroicon::OutlinedPlus)->color('primary'))
            ->collapseAllAction(fn (Action $action): Action => $action->badge()
                ->size(Size::Medium)
                ->icon(Heroicon::OutlinedChevronDoubleUp)
                ->alpineClickHandler('allCollapsed = true')
                ->extraAttributes(['x-show' => '! allCollapsed']))
            ->expandAllAction(fn (Action $action): Action => $action->badge()
                ->size(Size::Medium)
                ->icon(Heroicon::OutlinedChevronDoubleDown)
                ->alpineClickHandler('allCollapsed = false')
                ->extraAttributes(['x-show' => 'allCollapsed']))
            ->itemLabel(fn (array $state): ?string => $type === null ? null : $this->collectionItemLabel($type, $state))
            ->addActionLabel($type === null ? null : $type::addActionLabel())
            ->dehydrated(false)
            ->loadStateFromRelationshipsUsing(fn (Repeater $component, Model $record) => $component->state($this->collectionState($record)))
            // The cards as they stand, the form having been validated: an
            // item's own getState() drops a key two of its inputs share (an
            // address's Zone, a select or a text box by country) — whichever
            // is hidden takes it along.
            ->saveRelationshipsUsing(fn (Repeater $component, Model $record) => method_exists($record, 'syncCollection')
                ? $record->syncCollection($this->name, array_values((array) $component->getRawState()))
                : null);

        if (is_int($max = $this->getParam('max_items'))) {
            $repeater = $repeater->maxItems($max);
        }

        return $repeater;
    }

    /** Whether the repeater holds cards and every one is a saved item: they all start collapsed. */
    protected function allItemsSaved(Repeater $repeater): bool
    {
        $items = collect((array) $repeater->getRawState());

        return $items->isNotEmpty() && $items->every(fn (mixed $item): bool => filled(data_get($item, 'id')));
    }

    /**
     * The record's items as the form's cards: each item's id and values.
     *
     * @return list<array<string, mixed>>
     */
    protected function collectionState(Model $record): array
    {
        if (! method_exists($record, 'collection')) {
            return [];
        }

        return $record->collection($this->name)->get()
            ->map(fn (CollectionItem $item): array => ['id' => $item->getKey(), ...Arr::only($item->attributesToArray(), $item->getFillable())])
            ->values()
            ->all();
    }

    /**
     * A card's heading, "Home: Main St 1, Warsaw", from what the card holds.
     *
     * @param  class-string<CollectionItem>  $type
     * @param  array<string, mixed>  $state
     */
    protected function collectionItemLabel(string $type, array $state): ?string
    {
        $item = new $type;
        $item->forceFill(array_intersect_key($state, array_flip($item->getFillable())));

        $label = implode(': ', array_filter([$item->kindLabel(), $item->summary()], filled(...)));

        return $label === '' ? null : $label;
    }

    /**
     * The record's items, primary first — the loaded relation when a list
     * page loaded the whole page's (preloadCollection()).
     *
     * @return EloquentCollection<int, CollectionItem>
     */
    public function collectionItemsOf(?Model $record): EloquentCollection
    {
        if (! $record?->exists || ! method_exists($record, 'collection') || $this->collectionType() === null) {
            return new EloquentCollection;
        }

        return $record->relationLoaded($this->name)
            ? $record->getRelation($this->name)
            : $record->collection($this->name)->get();
    }

    /**
     * One line per item for the View page: the kind as a badge, the summary
     * (a link when the item has a page elsewhere, as an online account
     * does), the item's own badges (a phone number's messengers) and any
     * administrator's fields after it. Null when there are none, so the
     * entry shows its placeholder.
     */
    protected function collectionLines(Model $record): ?HtmlString
    {
        $items = $this->collectionItemsOf($record);

        if ($items->isEmpty()) {
            return null;
        }

        return new HtmlString(view('epesi-recordbrowser::collection-items', [
            'items' => $items->map(fn (CollectionItem $item): array => [
                'kind' => $item->kindLabel(),
                'summary' => $item->summary(),
                'url' => $item->url(),
                'links' => $item->links(),
                'extra' => $item->extraValues(),
            ])->all(),
        ])->render());
    }

    /** The field of an item the list shows for the primary one: the one its type marks inTable(). */
    protected function collectionListField(): ?Field
    {
        $type = $this->collectionType();

        if ($type === null) {
            return null;
        }

        foreach ($type::resolvedFields() as $field) {
            if ($field->isInTable() && $field->type->hasColumn()) {
                return $field;
            }
        }

        return null;
    }

    /**
     * The item columns the list's search box and global search look in.
     *
     * @return list<string>
     */
    public function collectionSearchColumns(): array
    {
        $type = $this->collectionType();

        return $type === null ? [] : $type::searchColumns();
    }

    /** The primary item's value in the list, loaded for the whole page at once. */
    protected function collectionColumn(): TextColumn
    {
        $column = TextColumn::make($this->name);

        return $column
            ->state(function (Model $record) use ($column): ?string {
                $this->preloadCollection($column);

                $first = $this->collectionItemsOf($record)->first();
                $shown = $this->collectionListField();

                if ($first === null) {
                    return null;
                }

                if ($shown === null) {
                    return $first->summary();
                }

                $value = $first->getAttribute($shown->name);

                return blank($value) ? null : $shown->formatLoggedValue($value);
            })
            // Cut short with the full value on hover, as a long company name
            // or e-mail address otherwise pushes the list past the page.
            ->limit(25, '…')
            ->tooltip(fn (TextColumn $column, ?string $state): ?string => mb_strwidth((string) $state) > $column->getCharacterLimit() ? $state : null)
            // The same link the primary item gets on the View page (an
            // e-mail address opens compose, an online account its profile).
            ->url(function (Model $record) use ($column): ?string {
                $this->preloadCollection($column);

                return $this->collectionItemsOf($record)->first()?->url();
            });
    }

    /**
     * A list page's items, loaded for the whole page when its first row asks —
     * one query per collection field rather than one per row.
     */
    protected function preloadCollection(Column $column): void
    {
        try {
            $records = $column->getTable()->getRecords();
        } catch (Throwable) {
            return;
        }

        $records = $records instanceof EloquentCollection ? $records : (method_exists($records, 'getCollection') ? $records->getCollection() : null);

        if ($records instanceof EloquentCollection && $records->first() instanceof Model && method_exists($records->first(), 'collection')) {
            $records->loadMissing($this->name);
        }
    }

    /**
     * This field's items whose owner is the row of $owners — for a subquery
     * that sorts, searches or filters the owners by their items.
     */
    protected function collectionItemsQuery(Builder $owners): Builder
    {
        $owner = $owners->getModel();

        return $this->collectionType()::query()
            ->whereColumn('owner_id', $owner->getQualifiedKeyName())
            ->where('owner_type', $owner->getMorphClass())
            ->where('field', $this->name);
    }

    /** By the primary item's value: a subquery on the first item. */
    protected function collectionSortQuery(): ?Closure
    {
        $shown = $this->collectionListField();

        return $shown === null ? null : fn (Builder $query, string $direction): Builder => $query->orderBy(
            $this->collectionItemsQuery($query)->select($shown->name)->orderBy('position')->orderBy('id')->limit(1)->toBase(),
            $direction,
        );
    }

    /**
     * Any item matching, so a contact is found by their second address too —
     * each column by the term as its type looks for it there (a phone
     * number's digits by the digits typed).
     */
    protected function collectionSearchQuery(): ?Closure
    {
        $type = $this->collectionType();
        $columns = $this->collectionSearchColumns();

        return $columns === [] ? null : fn (Builder $query, string $search): Builder => $query->whereExists(
            $this->collectionItemsQuery($query)
                ->where(function (Builder $items) use ($type, $columns, $search): void {
                    // Nothing, when no column takes the term.
                    $items->whereRaw('1 = 0');

                    foreach ($columns as $column) {
                        if (($term = $type::searchTerm($column, $search)) !== null) {
                            $items->orWhere($column, 'like', "%{$term}%");
                        }
                    }
                })
                ->toBase(),
        );
    }

    /**
     * Has any (yes or no), which kinds, and the item fields the type marks
     * filterable() — Country and City for an address, Messengers for a
     * phone number. Filament gives a filter of several inputs no heading,
     * so each input names the field.
     */
    protected function collectionFilter(): ?Filter
    {
        $type = $this->collectionType();

        if ($type === null) {
            return null;
        }

        $label = __($this->getLabel());
        $kind = $type::kindField();
        $kindLabel = __(':field: :kind', ['field' => $label, 'kind' => __($kind->getLabel())]);

        /** @var array<string, array{0: Field, 1: 'in'|'like'|'json'}> $inputs item column => [field, how it matches] */
        $inputs = [];
        $schema = $this->collectionFilterItemFieldsOnly ? [] : [
            Select::make('has')->label($label)->options(['1' => __('Yes'), '0' => __('No')]),
            // Already translated, with the field's name in it: not looked
            // up once more as a whole.
            Select::make('kinds')->label($kindLabel)
                ->translateLabel(false)
                ->options(fn (): array => $kind->commonDataOptions())
                ->multiple(),
        ];

        foreach ($type::resolvedFields() as $field) {
            if (! $field->filterable || ! $field->type->hasColumn()) {
                continue;
            }

            $input = $field->toFormComponent();

            // A choice matches one of the values picked; a field holding
            // several (JSON) matches when it holds any of them.
            if ($input instanceof Select) {
                $schema[] = $input->multiple()->required(false)->live(false)->clearAfterStateUpdatedHooks();
                $inputs[$field->name] = [$field, $field->isMultipleValued() || $field->type === FieldType::Multiselect ? 'json' : 'in'];
            } elseif ($field->type->isTextual() || $field->type === FieldType::LongText) {
                $schema[] = TextInput::make($field->name)->label($field->getLabel());
                $inputs[$field->name] = [$field, 'like'];
            }
        }

        $wanted = fn (array $data): array => array_filter(
            array_intersect_key($data, $inputs),
            fn (mixed $value): bool => filled($value),
        );

        return Filter::make($this->name)
            ->schema($schema)
            ->query(function (Builder $query, array $data) use ($inputs, $wanted): Builder {
                $has = $data['has'] ?? null;
                $kinds = array_values(array_filter((array) ($data['kinds'] ?? [])));
                $values = $wanted($data);

                if ($has === '0' || $has === 0) {
                    return $query->whereNotExists($this->collectionItemsQuery($query)->toBase());
                }

                if (blank($has) && $kinds === [] && $values === []) {
                    return $query;
                }

                $items = $this->collectionItemsQuery($query)->when($kinds !== [], fn (Builder $items): Builder => $items->whereIn('kind', $kinds));

                foreach ($values as $column => $value) {
                    match ($inputs[$column][1]) {
                        'in' => $items->whereIn($column, (array) $value),
                        'json' => $items->where(function (Builder $items) use ($column, $value): void {
                            foreach ((array) $value as $one) {
                                $items->orWhereJsonContains($column, $one);
                            }
                        }),
                        'like' => $items->where($column, 'like', '%'.$value.'%'),
                    };
                }

                return $query->whereExists($items->toBase());
            })
            ->indicateUsing(function (array $data) use ($label, $kind, $kindLabel, $inputs, $wanted): array {
                $indicators = [];

                if (filled($data['has'] ?? null)) {
                    $indicators[] = Indicator::make($label.': '.($data['has'] ? __('Yes') : __('No')))->removeField('has');
                }

                if ($kinds = array_filter((array) ($data['kinds'] ?? []))) {
                    $indicators[] = Indicator::make($kindLabel.': '.implode(', ', array_map(fn (string $key): string => $kind->commonDataLabel($key), $kinds)))
                        ->removeField('kinds');
                }

                foreach ($wanted($data) as $column => $value) {
                    [$field] = $inputs[$column];
                    $indicators[] = Indicator::make(__($field->getLabel()).': '.implode(', ', array_map(
                        fn (mixed $one): string => $field->formatLoggedValue($one),
                        (array) $value,
                    )))->removeField($column);
                }

                return $indicators;
            });
    }

    /**
     * A change of a collection: the items taken off, struck through, and the
     * ones added, by the one-line summaries logged with it — an edited item
     * is one of each. A record just created lists its items; a change of
     * order alone says so.
     */
    protected function loggedCollectionChange(mixed $old, mixed $new, Activity $entry): ?HtmlString
    {
        $old = array_values(array_map('strval', (array) $old));
        $new = array_values(array_map('strval', (array) $new));

        if ($entry->event === 'created') {
            return $new === [] ? null : new HtmlString(implode('; ', array_map(fn (string $line): string => '<span class="epesi-history-new">'.e($line).'</span>', $new)));
        }

        if ($old === $new) {
            return null;
        }

        $removed = array_diff($old, $new);
        $added = array_diff($new, $old);

        if ($removed === [] && $added === []) {
            return new HtmlString(e(__('Reordered')).': <span class="epesi-history-new">'.e(implode('; ', $new)).'</span>');
        }

        return new HtmlString(implode(' ', [
            ...array_map(fn (string $line): string => '<del class="epesi-history-old">− '.e($line).'</del>', $removed),
            ...array_map(fn (string $line): string => '<ins class="epesi-history-new">+ '.e($line).'</ins>', $added),
        ]));
    }

    // ---------------------------------------------------------------- View --

    public function toInfolistEntry(): SchemaComponent
    {
        $entry = $this->buildInfolistEntry()->label($this->getLabel());

        if ($entry instanceof TextEntry) {
            $entry = $entry->placeholder($this->placeholder ?? '-');
        }

        if ($this->isFullWidth()) {
            $entry = $entry->columnSpanFull();
        }

        return $this->viewUsing ? ($this->viewUsing)($entry, $this) : $entry;
    }

    protected function buildInfolistEntry(): SchemaComponent
    {
        return match ($this->type) {
            FieldType::Boolean => IconEntry::make($this->name)->boolean(),
            FieldType::Date => TextEntry::make($this->name)->date(),
            FieldType::DateTime => TextEntry::make($this->name)->dateTime(),
            FieldType::Time => TextEntry::make($this->name)->time(),
            FieldType::Select => TextEntry::make($this->name)->badge(),
            FieldType::Multiselect => TextEntry::make($this->name)->badge(),
            // Linked as in the list — see RecordExtensions::emailLink() — with
            // the same badge and link icon as a web address. Medium size, as
            // every other link badge (collection items, related records) is.
            FieldType::Email => TextEntry::make($this->name)
                ->badge()
                ->size(TextSize::Medium)
                ->icon(Heroicon::OutlinedLink)
                ->iconPosition(IconPosition::After)
                ->url(fn (Model $record, ?string $state): ?string => filled($state) ? RecordExtensions::emailUrlFor($record, $state) : null),
            // A badge with a link icon that opens in a new tab, the same
            // look as a related record's badge.
            FieldType::Url => TextEntry::make($this->name)
                ->badge()
                ->size(TextSize::Medium)
                ->icon(Heroicon::OutlinedLink)
                ->iconPosition(IconPosition::After)
                ->url(fn (?string $state): ?string => static::webAddressUrl($state))
                ->openUrlInNewTab(),
            // Plain text, comma-joined when several are picked: a common-data
            // entry is a key and a label with no colour to make a badge earn
            // its place (see Common-data.md).
            FieldType::CommonData => TextEntry::make($this->name)
                ->formatStateUsing(fn (mixed $state): string => $this->commonDataLabel($state)),
            FieldType::Relation, FieldType::Relations => $this->relationEntry(),
            FieldType::Autonumber => TextEntry::make($this->name)
                ->state(fn (Model $record): string => $this->formatAutonumber($record->getKey())),
            FieldType::File => TextEntry::make($this->name)
                ->state(fn (Model $record): ?HtmlString => $this->fileChips($record)),
            FieldType::Related => LinkedRecords::badges(
                TextEntry::make($this->name),
                fn (Model $record): Collection => $this->linkedRecordsOf($record),
                $this->relatedLabel(...),
            ),
            FieldType::Collection => TextEntry::make($this->name)
                ->state(fn (Model $record): ?HtmlString => $this->collectionLines($record)),
            default => TextEntry::make($this->name),
        };
    }

    /**
     * Where a stored web address points. Only http(s) is kept as typed; anything
     * else gets https:// in front, so "www.example.com" links out instead of
     * resolving against this site and a "javascript:" value can't become an href.
     */
    public static function webAddressUrl(?string $state): ?string
    {
        $state = trim((string) $state);

        if ($state === '') {
            return null;
        }

        return preg_match('#^https?://#i', $state) ? $state : 'https://'.$state;
    }

    /**
     * A related record always renders as a badge linking to that record — the
     * standing rule for this port (LinkedRecords) — so the engine does it for
     * every relation field rather than leaving it to each resource to remember.
     */
    protected function relationEntry(): TextEntry
    {
        return LinkedRecords::style(
            TextEntry::make((string) $this->getParam('relationship'))
                ->state(fn (Model $record): array => $this->relatedTitles($record)),
            fn (Model $record, string $state): ?string => $this->relatedUrl($record, $state),
        );
    }

    // --------------------------------------------------------------- Table --

    /**
     * The list's columns for this field: one, or for a collection shown by
     * kind (columnsForKinds()) one per kind.
     *
     * @return list<Column>
     */
    public function toTableColumns(): array
    {
        $kinds = $this->type === FieldType::Collection ? $this->getParam('kind_columns') : null;

        if (! is_array($kinds) || $kinds === [] || $this->collectionListField() === null) {
            return [$this->toTableColumn()];
        }

        $columns = [];

        foreach ($kinds as $kind => $label) {
            $columns[] = is_int($kind) ? $this->kindColumn($label) : $this->kindColumn($kind, $label);
        }

        return $columns;
    }

    /**
     * The first item of $kind, by the value its type shows in the list
     * (collectionListField()) and sorted by it; the search box still looks
     * in every item, whichever of the columns is shown.
     */
    protected function kindColumn(string $kind, ?string $label = null): Column
    {
        $shown = $this->collectionListField();
        $column = TextColumn::make("{$this->name}_{$kind}");

        $column = $column
            ->label($label ?? $this->collectionType()::kindField()->commonDataLabel($kind))
            ->state(function (Model $record) use ($column, $kind, $shown): ?string {
                $this->preloadCollection($column);

                $value = $this->collectionItemsOf($record)->firstWhere('kind', $kind)?->getAttribute($shown->name);

                return blank($value) ? null : $shown->formatLoggedValue($value);
            })
            ->placeholder($this->placeholder ?? '-')
            ->toggleable(isToggledHiddenByDefault: ! $this->inTable)
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                $this->collectionItemsQuery($query)->where('kind', $kind)->select($shown->name)->orderBy('position')->orderBy('id')->limit(1)->toBase(),
                $direction,
            ))
            ->searchable($this->isSearchable(), query: $this->searchQuery());

        return $this->columnUsing ? ($this->columnUsing)($column, $this) : $column;
    }

    public function toTableColumn(): Column
    {
        $column = $this->buildTableColumn()
            ->label($this->columnLabel())
            ->toggleable(isToggledHiddenByDefault: ! $this->inTable);

        if ($column instanceof TextColumn) {
            $column = $column->placeholder($this->placeholder ?? '-');

            if ($this->tooltipAttribute) {
                $column = $column->tooltip(fn (Model $record): ?string => Str::limit(trim((string) $record->getAttribute($this->tooltipAttribute)), 500) ?: null);
            }
        }

        $column = $column
            ->sortable($this->sortable ?? $this->isSortableByDefault(), query: $this->sortQuery())
            ->searchable($this->isSearchable(), query: $this->searchQuery());

        return $this->columnUsing ? ($this->columnUsing)($column, $this) : $column;
    }

    /** A collection's column is named after the item field it shows: "City". */
    protected function columnLabel(): string
    {
        return $this->type === FieldType::Collection && ($shown = $this->collectionListField()) !== null
            ? $shown->getLabel()
            : $this->getLabel();
    }

    /** How a field with no column of its own sorts, if it does. */
    protected function sortQuery(): ?Closure
    {
        return $this->type === FieldType::Collection ? $this->collectionSortQuery() : null;
    }

    /** How a field with no column of its own is searched, if it is. */
    protected function searchQuery(): ?Closure
    {
        return match ($this->type) {
            // No real column to LIKE-search: read the number back to the key
            // it names instead — Ticket's own search before this existed did
            // the same thing by hand.
            FieldType::Autonumber => fn (Builder $query, string $search): Builder => ($id = $this->autonumberId($search)) !== null
                ? $query->whereKey($id)
                : $query,
            FieldType::Collection => $this->collectionSearchQuery(),
            default => null,
        };
    }

    protected function buildTableColumn(): Column
    {
        return match ($this->type) {
            FieldType::Boolean => IconColumn::make($this->name)->boolean(),
            FieldType::LongText => TextColumn::make($this->name)->limit(60),
            FieldType::Date => TextColumn::make($this->name)->date(),
            FieldType::DateTime => TextColumn::make($this->name)->dateTime(),
            FieldType::Time => TextColumn::make($this->name)->time(),
            FieldType::Select, FieldType::Multiselect => TextColumn::make($this->name)->badge(),
            FieldType::CommonData => TextColumn::make($this->name)
                ->formatStateUsing(fn (mixed $state): string => $this->commonDataLabel($state)),
            // The same badge and link icon as on the View page, cut short with
            // the full address on hover, as long company names are — see
            // RecordExtensions::emailLink() for where the link goes.
            FieldType::Email => TextColumn::make($this->name)
                ->badge()
                ->size(TextSize::Medium)
                ->icon(Heroicon::OutlinedLink)
                ->iconPosition(IconPosition::After)
                ->limit(25, '…')
                ->tooltip(fn (TextColumn $column, ?string $state): ?string => mb_strwidth((string) $state) > $column->getCharacterLimit() ? $state : null)
                ->url(fn (Model $record, ?string $state): ?string => filled($state) ? RecordExtensions::emailUrlFor($record, $state) : null),
            // The same badge, link icon and new tab as on the View page.
            FieldType::Url => TextColumn::make($this->name)
                ->badge()
                ->size(TextSize::Medium)
                ->icon(Heroicon::OutlinedLink)
                ->iconPosition(IconPosition::After)
                ->url(fn (?string $state): ?string => static::webAddressUrl($state))
                ->openUrlInNewTab(),
            FieldType::Relation, FieldType::Relations => $this->relationColumn(),
            FieldType::Autonumber => TextColumn::make($this->name)
                ->state(fn (Model $record): string => $this->formatAutonumber($record->getKey())),
            FieldType::File => TextColumn::make($this->name)
                ->state(fn (Model $record): ?HtmlString => $this->fileChips($record, limit: 2)),
            FieldType::Related => $this->relatedColumn(),
            FieldType::Collection => $this->collectionColumn(),
            default => TextColumn::make($this->name),
        };
    }

    /**
     * The same badges as on the View page, the page's links loaded at once,
     * one per row and capped at 3 with a "+N" count beyond that.
     */
    protected function relatedColumn(): TextColumn
    {
        $column = TextColumn::make($this->name);

        return LinkedRecords::badges(
            $column,
            function (Model $record) use ($column): Collection {
                $this->preloadRecordLinks($column);

                return $this->linkedRecordsOf($record);
            },
            $this->relatedLabel(...),
        )->listWithLineBreaks()->limitList(3);
    }

    /** The same badges as on the View page, one per row, capped at 3 with a "+N" count beyond that. */
    protected function relationColumn(): TextColumn
    {
        return LinkedRecords::style(
            TextColumn::make((string) $this->getParam('relationship'))
                ->state(fn (Model $record): array => $this->relatedTitles($record)),
            fn (Model $record, string $state): ?string => $this->relatedUrl($record, $state),
        )->listWithLineBreaks()->limitList(3);
    }

    /** The View page of the related record titled $state, when it has one. */
    protected function relatedUrl(Model $record, string $state): ?string
    {
        $resource = $this->relatedResource();

        if (! $resource || ! $resource::hasPage('view')) {
            return null;
        }

        $related = $this->relatedRecords($record)
            ->first(fn (Model $related): bool => (string) $resource::getRecordTitle($related) === $state);

        return $related ? $resource::getUrl('view', ['record' => $related]) : null;
    }

    /**
     * Sorting and searching are free on a real column and impossible on a
     * derived one: a relation column's displayed value comes from the related
     * resource's record title, which may be an accessor (Contact's full_name)
     * with no SQL equivalent, so those default off there. Autonumber has the
     * same problem — the formatted string doesn't sort the way its padding
     * suggests once a key outgrows it — but it gets its own searchable()
     * query in toTableColumn() rather than going without. A collection sorts
     * and searches through subqueries on its items (sortQuery(),
     * searchQuery()) when it has something to sort and search by.
     */
    protected function isSortableByDefault(): bool
    {
        if ($this->type === FieldType::Collection) {
            return $this->collectionListField() !== null;
        }

        return ! $this->type->isRelational() && ! $this->type->isMultiple() && ! $this->isMultipleValued()
            && ! in_array($this->type, [FieldType::Autonumber, FieldType::File], true);
    }

    protected function isSearchableByDefault(): bool
    {
        if ($this->type === FieldType::Collection) {
            return $this->collectionSearchColumns() !== [];
        }

        return $this->type->isTextual() || $this->type === FieldType::LongText || $this->type === FieldType::Autonumber;
    }

    public function toTableFilter(): ?BaseFilter
    {
        if (! $this->filterable) {
            return null;
        }

        $filter = match (true) {
            $this->type === FieldType::Boolean => TernaryFilter::make($this->name),
            $this->type === FieldType::Select => SelectFilter::make($this->name)->options($this->options()),
            $this->type === FieldType::Multiselect => SelectFilter::make($this->name)->options($this->options())->multiple(),
            $this->type === FieldType::CommonData => SelectFilter::make($this->name)
                ->options($this->commonDataOptions())
                ->multiple($this->isMultipleValued()),
            $this->type->isRelational() => SelectFilter::make($this->name)
                ->relationship((string) $this->getParam('relationship'), $this->relatedTitleAttribute())
                ->searchable()
                ->preload(),
            in_array($this->type, [FieldType::Date, FieldType::DateTime], true) => $this->rangeFilter(
                fn (string $name, string $label): DatePicker => DatePicker::make($name)->label($label),
            ),
            in_array($this->type, [FieldType::Integer, FieldType::Decimal], true) => $this->rangeFilter(
                fn (string $name, string $label): TextInput => TextInput::make($name)->label($label)->numeric(),
            ),
            // Which recordsets it links to — a record filter can come later.
            $this->type === FieldType::Related => SelectFilter::make($this->name)
                ->options(array_intersect_key(LinkableRecordsets::options(), array_flip($this->relatedRecordsets())))
                ->multiple()
                ->query(fn (Builder $query, array $data): Builder => filled($data['values'] ?? null)
                    ? $query->whereHas('recordLinks', fn (Builder $links): Builder => $links
                        ->where('field', $this->name)
                        ->whereIn('target_type', $data['values']))
                    : $query),
            // No files is stored as null (StoredFileIds), so "has files" is a
            // plain null check.
            $this->type === FieldType::File => TernaryFilter::make($this->name)
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereNotNull($this->name),
                    false: fn (Builder $query): Builder => $query->whereNull($this->name),
                ),
            $this->type === FieldType::Collection => $this->collectionFilter(),
            default => $this->containsFilter(),
        };

        $filter = $filter?->label($this->getLabel());

        return $this->filterUsing && $filter ? ($this->filterUsing)($filter, $this) : $filter;
    }

    /**
     * From/until on a date or number. `->filterable()` has to *do* something for
     * every type it is allowed on — a field marked filterable that silently
     * produces no filter is the kind of quiet nothing that takes an afternoon
     * to work out.
     *
     * @param  callable(string, string): mixed  $input
     */
    protected function rangeFilter(callable $input): Filter
    {
        $column = $this->name;

        // "Follow up on from" / "Follow up on until" rather than a bare
        // From/Until pair: Filament renders no heading above a multi-input
        // filter, so two range filters on one table would otherwise be four
        // unlabelled boxes.
        return Filter::make($column)
            ->schema([
                $input('from', __(':field from', ['field' => __($this->getLabel())])),
                $input('until', __(':field until', ['field' => __($this->getLabel())])),
            ])
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $q, $value): Builder => $q->where($column, '>=', $value))
                ->when($data['until'] ?? null, fn (Builder $q, $value): Builder => $q->where($column, '<=', $value)))
            ->indicateUsing(function (array $data): array {
                $indicators = [];

                if ($data['from'] ?? null) {
                    $indicators[] = Indicator::make($this->getLabel().' from '.$data['from'])->removeField('from');
                }

                if ($data['until'] ?? null) {
                    $indicators[] = Indicator::make($this->getLabel().' until '.$data['until'])->removeField('until');
                }

                return $indicators;
            });
    }

    /**
     * "Contains" on a text column. Less useful than the table's own search box,
     * but a filter is a saved, indicated, combinable thing in a way a search box
     * is not.
     */
    protected function containsFilter(): ?Filter
    {
        if (! $this->type->isTextual() && $this->type !== FieldType::LongText) {
            return null;
        }

        $column = $this->name;

        return Filter::make($column)
            ->schema([TextInput::make('value')->label($this->getLabel())])
            ->query(fn (Builder $query, array $data): Builder => $query->when(
                $data['value'] ?? null,
                fn (Builder $q, string $value): Builder => $q->where($column, 'like', "%{$value}%"),
            ))
            ->indicateUsing(fn (array $data): array => filled($data['value'] ?? null)
                ? [Indicator::make($this->getLabel().': '.$data['value'])->removeField('value')]
                : []);
    }

    // ------------------------------------------------------------- History --

    /**
     * A value from the activity log as the View page shows it — the status's
     * name rather than its number, the contact's name rather than its id —
     * for the History addon, which has nothing but what was logged. Dates use
     * the table's formats, as a list column does, or Filament's defaults with
     * no table (a collection item's one-line summary).
     */
    public function formatLoggedValue(mixed $value, ?Table $table = null): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '-';
        }

        if ($this->richText) {
            $plain = TextDiff::plainText((string) $value);

            return $plain === '' ? '-' : $plain;
        }

        return match ($this->type) {
            FieldType::Boolean => $value ? __('Yes') : __('No'),
            FieldType::Select, FieldType::Multiselect => implode(', ', array_map(fn (mixed $one): string => $this->optionLabel($one), (array) $value)),
            FieldType::CommonData => implode(', ', array_map(fn (mixed $one): string => $this->commonDataLabel($one), (array) $value)),
            FieldType::Relation => $this->loggedRelatedTitle($value),
            // A date is logged either as it was typed or as midnight UTC:
            // either way it is the day that was meant, so no time zone shift.
            FieldType::Date => Carbon::parse($value)->translatedFormat($table?->getDefaultDateDisplayFormat() ?? 'M j, Y'),
            FieldType::DateTime => Carbon::parse($value)->setTimezone(FilamentTimezone::get())->translatedFormat($table?->getDefaultDateTimeDisplayFormat() ?? 'M j, Y H:i:s'),
            FieldType::Time => Carbon::parse($value)->translatedFormat($table?->getDefaultTimeDisplayFormat() ?? 'H:i:s'),
            // The items' one-line summaries, as logged.
            FieldType::Collection => implode('; ', (array) $value),
            default => is_array($value) ? implode(', ', $value) : (string) $value,
        };
    }

    /**
     * A change the History addon shows from both values at once, or null for
     * its usual "old → new": the field's historyUsing() callback, else, for
     * long text, the words that changed (TextDiff) rather than two copies of
     * the whole text.
     */
    public function formatLoggedChange(mixed $old, mixed $new, Activity $entry, bool $whole = false): ?HtmlString
    {
        if ($this->historyUsing !== null) {
            $change = ($this->historyUsing)($old, $new, $entry, $whole);

            return $change === null ? null : new HtmlString($change instanceof Htmlable ? $change->toHtml() : $change);
        }

        if ($this->type === FieldType::File) {
            return $this->loggedFileChange($old, $new, (array) $entry->properties->get('file_names', []));
        }

        if ($this->type === FieldType::Collection) {
            return $this->loggedCollectionChange($old, $new, $entry);
        }

        if ($this->type !== FieldType::LongText) {
            return null;
        }

        // The Show modal, for rich text: the words that didn't change keep
        // their bold/italic/code/links rather than being flattened to plain
        // text first, as the compact "Changes" line does either way.
        $html = $whole && $this->richText
            ? TextDiff::renderRich((string) $old, (string) $new)
            : TextDiff::render($this->loggedText($old), $this->loggedText($new), $whole);

        return new HtmlString($html ?? '<span class="epesi-history-gap">'.e(__('Only the formatting changed')).'</span>');
    }

    /**
     * A long text as it read after a logged change, for the History addon's
     * Show modal: rich text rendered (and sanitized: it is stored HTML), plain
     * text with its line breaks.
     */
    public function formatLoggedVersion(mixed $value): HtmlString
    {
        if (blank($value)) {
            return new HtmlString('-');
        }

        return new HtmlString($this->richText ? Str::sanitizeHtml((string) $value) : nl2br(e((string) $value)));
    }

    /**
     * A change of a file field: the files taken off, struck through, and the
     * ones added, by the names logged with the change (HasFileFields'
     * tapActivity()) — a file taken off is deleted straight after, name and
     * all. The same look as a note's files in its History.
     *
     * @param  array<int|string, string>  $names  id => name
     */
    protected function loggedFileChange(mixed $old, mixed $new, array $names): ?HtmlString
    {
        $old = StoredFileIds::decode($old);
        $new = StoredFileIds::decode($new);
        $removed = array_diff($old, $new);
        $added = array_diff($new, $old);

        if ($removed === [] && $added === []) {
            return null;
        }

        $unnamed = array_diff([...$removed, ...$added], array_map('strval', array_keys($names)));

        if ($unnamed !== []) {
            $names += StoredFile::query()->whereKey($unnamed)->pluck('name', 'id')->all();
        }

        $name = fn (string $id): string => e($names[$id] ?? __('deleted file'));

        return new HtmlString(implode(' ', [
            ...array_map(fn (string $id): string => '<del class="epesi-history-old">− '.$name($id).'</del>', $removed),
            ...array_map(fn (string $id): string => '<ins class="epesi-history-new">+ '.$name($id).'</ins>', $added),
        ]));
    }

    protected function loggedText(mixed $value): string
    {
        return $this->richText ? TextDiff::plainText((string) $value) : TextDiff::normalize((string) $value);
    }

    protected function optionLabel(mixed $value): string
    {
        $options = $this->options();

        if (is_string($options) && enum_exists($options)) {
            $case = $value instanceof BackedEnum ? $value : (is_int($value) || is_string($value) ? $options::tryFrom($value) : null);

            return match (true) {
                $case instanceof HasLabel => (string) $case->getLabel(),
                $case instanceof BackedEnum => $case->name,
                default => (string) $value,
            };
        }

        return (string) (is_array($options) ? ($options[$value] ?? $value) : $value);
    }

    /**
     * The related record's title, looked up through its own visibility rules:
     * one this user may not see stays a bare id.
     */
    protected function loggedRelatedTitle(mixed $id): string
    {
        $model = $this->getParam('model');

        if (! is_string($model) || ! (is_int($id) || is_string($id))) {
            return (string) json_encode($id);
        }

        $related = $this->loggedRelated[$id] ??= $model::query()->find($id) ?? false;

        if (! $related instanceof Model) {
            return '#'.$id;
        }

        $resource = $this->relatedResource();

        return (string) ($resource ? $resource::getRecordTitle($related) : $related->getAttribute($this->relatedTitleAttribute()));
    }

    // --------------------------------------------------------------- Autonumber --

    /**
     * "$prefix" plus the key padded to $padLength with $padMask — legacy's
     * `format_autonumber_str()`. A null key (an unsaved record) pads with
     * "?" instead, exactly as legacy does, rather than a real digit that
     * doesn't exist yet.
     */
    public function formatAutonumber(int|string|null $id): string
    {
        $prefix = (string) $this->getParam('prefix', '');
        $padLength = (int) $this->getParam('pad_length', 0);
        $padMask = $id === null ? '?' : (string) $this->getParam('pad_mask', '0');

        return $prefix.str_pad((string) $id, $padLength, $padMask, STR_PAD_LEFT);
    }

    /**
     * The record key a formatted number reads back to, or null when $search
     * doesn't look like one of this field's numbers — used to make the table
     * column searchable despite holding no real column to search.
     */
    protected function autonumberId(string $search): ?int
    {
        $prefix = (string) $this->getParam('prefix', '');
        $padMask = (string) $this->getParam('pad_mask', '0');
        $pattern = '/^'.preg_quote($prefix, '/').preg_quote($padMask, '/').'*(\d+)$/';

        return preg_match($pattern, trim($search), $matches) ? (int) $matches[1] : null;
    }

    // ------------------------------------------------------------- Helpers --

    protected function lengthParam(): int
    {
        return (int) $this->getParam('length', 255);
    }

    /** Null for every minute, Filament's own default. */
    protected function minutesStepParam(): ?int
    {
        $step = (int) $this->getParam('minutes_step', 0);

        return $step > 1 ? $step : null;
    }

    /**
     * @return class-string<BackedEnum>|array<string, string>
     */
    protected function options(): string|array
    {
        return $this->getParam('options', []);
    }

    /**
     * True for a commondata field holding several keys at once.
     */
    protected function isMultipleValued(): bool
    {
        return (bool) $this->getParam('multiple', false);
    }

    /**
     * @return array<string, string> key => label, from the shared list
     */
    protected function commonDataOptions(): array
    {
        return CommonData::array((string) $this->getParam('array'), (string) $this->getParam('order', 'value'));
    }

    /**
     * What is stored is the key; what is shown is the list's label for it. A
     * key with no entry left (the administrator removed it) falls back to the
     * raw key rather than rendering blank — an unreadable value is easier to
     * fix than an invisible one.
     */
    protected function commonDataLabel(mixed $state): string
    {
        if ($state === null || $state === '') {
            return '';
        }

        return $this->commonDataOptions()[$state] ?? (string) $state;
    }

    /**
     * @return class-string<\Filament\Resources\Resource>|null null when the
     *                                                         related model has no resource in the current panel
     */
    protected function relatedResource(): ?string
    {
        $model = $this->getParam('model');

        // No panel means no resource registry to ask — console commands and
        // queued work build Fields too. Null is a supported answer everywhere
        // it's used: the field falls back to plain values with no links.
        if (! is_string($model)) {
            return null;
        }

        return LinkableRecordsets::resource(Relation::getMorphAlias($model));
    }

    /**
     * The related model's own table column used to search and order the option
     * list — necessarily a real column, unlike the displayed title.
     */
    protected function relatedTitleAttribute(): string
    {
        $explicit = $this->getParam('title_attribute');

        if (is_string($explicit)) {
            return $explicit;
        }

        $resource = $this->relatedResource();

        return ($resource ? $resource::getRecordTitleAttribute() : null) ?? 'name';
    }

    public function titleAttribute(string $attribute): static
    {
        return $this->param('title_attribute', $attribute);
    }

    /**
     * Narrows the records a relation field offers — Epesi's select crits
     * (`crits_callback`, e.g. Tasks' `employees_crits()`).
     *
     * Records the edited record already links to stay on the form even when
     * they no longer match, so they can be seen and removed. Handing the crits
     * straight to Filament's `relationship(modifyQueryUsing:)` doesn't allow
     * that: it narrows what the field loads and saves as well as what it
     * offers, so an employee who had since left the company vanished from the
     * form but stayed linked, and nobody could remove them. Only the offered
     * list (options and search results) is held to the crits alone, so a
     * removed link isn't offered straight back.
     *
     * @param  Closure(Builder): mixed  $callback  constrains the related model's query
     */
    public function crits(Closure $callback): static
    {
        $this->crits = $callback;

        return $this;
    }

    /**
     * The crits as a Filament relationship modifier. Outside offerOnlyCrits()
     * — loading, saving, labelling the selected chips, validating the choice —
     * it is widened by whatever the record links to now.
     */
    protected function critsModifier(): ?Closure
    {
        if ($this->crits === null) {
            return null;
        }

        $crits = $this->crits;
        $relationship = (string) $this->getParam('relationship');

        return function (Builder $query, ?Model $record) use ($crits, $relationship): Builder {
            $key = $query->getModel()->getQualifiedKeyName();
            $linked = ! $this->offering && $record?->exists
                ? $record->{$relationship}()->pluck($key)->all()
                : [];

            return $query->where(function (Builder $query) use ($crits, $key, $linked): void {
                $query->where(fn (Builder $query) => $crits($query));

                if ($linked !== []) {
                    $query->orWhereIn($key, $linked);
                }
            });
        };
    }

    /**
     * Holds the select's options and search results to the crits alone. The
     * browser labels selected chips from a separate lookup, not from this
     * list, so a linked record outside the crits still shows while selected,
     * and once removed it isn't offered again.
     */
    protected function offerOnlyCrits(Select $select): Select
    {
        return $select
            ->options(fn (Select $component): ?array => $this->offering(
                fn (): ?array => $component->getOptionsFromRelationship(),
            ))
            ->getSearchResultsUsing(fn (Select $component, ?string $search): array => $this->offering(
                fn (): array => $component->getSearchResultsFromRelationship($search),
            ));
    }

    protected function offering(Closure $callback): mixed
    {
        $this->offering = true;

        try {
            return $callback();
        } finally {
            $this->offering = false;
        }
    }

    /**
     * @return Collection<int, Model>
     */
    protected function relatedRecords(Model $record): Collection
    {
        $related = $record->getAttribute((string) $this->getParam('relationship'));

        return match (true) {
            $related instanceof Collection => $related,
            $related instanceof Model => collect([$related]),
            default => collect(),
        };
    }

    /**
     * @return array<int, string>
     */
    protected function relatedTitles(Model $record): array
    {
        $resource = $this->relatedResource();

        return $this->relatedRecords($record)
            ->map(fn (Model $related): string => (string) ($resource
                ? $resource::getRecordTitle($related)
                : $related->getAttribute($this->relatedTitleAttribute())))
            ->all();
    }
}
