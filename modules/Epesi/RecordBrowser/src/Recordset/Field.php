<?php

namespace Epesi\Modules\RecordBrowser\Recordset;

use BackedEnum;
use Closure;
use Epesi\Modules\CommonData\Facades\CommonData;
use Epesi\Modules\RecordBrowser\Extensions\RecordExtensions;
use Epesi\Modules\RecordBrowser\Filament\LinkedRecords;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Enums\IconPosition;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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

    protected bool $inView = true;

    /** Shown as a table column without the user turning it on. */
    protected bool $inTable = false;

    /** Offered in the column chooser at all. */
    protected bool $tableToggleable = true;

    protected bool $filterable = false;

    protected ?bool $searchable = null;

    protected ?bool $sortable = null;

    protected bool $fullWidth = false;

    protected ?string $help = null;

    protected ?string $placeholder = null;

    protected mixed $default = null;

    protected ?Closure $formUsing = null;

    protected ?Closure $viewUsing = null;

    protected ?string $tooltipAttribute = null;

    protected ?Closure $columnUsing = null;

    protected ?Closure $filterUsing = null;

    protected ?Closure $crits = null;

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

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label ?? Str::headline($this->name);
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

    /** Spans both columns: asked for with fullWidth(), and always for long text. */
    public function isFullWidth(): bool
    {
        return $this->fullWidth || $this->type === FieldType::LongText;
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

    public function filterable(bool $filterable = true): static
    {
        $this->filterable = $filterable;

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
            FieldType::DateTime => DateTimePicker::make($this->name)->seconds(false),
            FieldType::Time => TimePicker::make($this->name)->seconds(false),
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
            // the same badge and link icon as a web address.
            FieldType::Email => TextEntry::make($this->name)
                ->badge()
                ->icon(Heroicon::OutlinedLink)
                ->iconPosition(IconPosition::After)
                ->url(fn (Model $record, ?string $state): ?string => filled($state) ? RecordExtensions::emailUrlFor($record, $state) : null),
            // A badge with a link icon that opens in a new tab, the same
            // look as a related record's badge.
            FieldType::Url => TextEntry::make($this->name)
                ->badge()
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
            default => TextEntry::make($this->name),
        };
    }

    /**
     * Where a stored web address points. Only http(s) is kept as typed; anything
     * else gets https:// in front, so "www.example.com" links out instead of
     * resolving against this site and a "javascript:" value can't become an href.
     */
    protected static function webAddressUrl(?string $state): ?string
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

    public function toTableColumn(): Column
    {
        $column = $this->buildTableColumn()
            ->label($this->getLabel())
            ->toggleable(isToggledHiddenByDefault: ! $this->inTable);

        if ($column instanceof TextColumn) {
            $column = $column->placeholder($this->placeholder ?? '-');

            if ($this->tooltipAttribute) {
                $column = $column->tooltip(fn (Model $record): ?string => Str::limit(trim((string) $record->getAttribute($this->tooltipAttribute)), 500) ?: null);
            }
        }

        $column = $column
            ->sortable($this->sortable ?? $this->isSortableByDefault())
            ->searchable($this->searchable ?? $this->isSearchableByDefault());

        return $this->columnUsing ? ($this->columnUsing)($column, $this) : $column;
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
                ->icon(Heroicon::OutlinedLink)
                ->iconPosition(IconPosition::After)
                ->limit(25, '…')
                ->tooltip(fn (TextColumn $column, ?string $state): ?string => mb_strwidth((string) $state) > $column->getCharacterLimit() ? $state : null)
                ->url(fn (Model $record, ?string $state): ?string => filled($state) ? RecordExtensions::emailUrlFor($record, $state) : null),
            // The same badge, link icon and new tab as on the View page.
            FieldType::Url => TextColumn::make($this->name)
                ->badge()
                ->icon(Heroicon::OutlinedLink)
                ->iconPosition(IconPosition::After)
                ->url(fn (?string $state): ?string => static::webAddressUrl($state))
                ->openUrlInNewTab(),
            FieldType::Relation, FieldType::Relations => $this->relationColumn(),
            default => TextColumn::make($this->name),
        };
    }

    /** The same badges as on the View page. */
    protected function relationColumn(): TextColumn
    {
        return LinkedRecords::style(
            TextColumn::make((string) $this->getParam('relationship'))
                ->state(fn (Model $record): array => $this->relatedTitles($record)),
            fn (Model $record, string $state): ?string => $this->relatedUrl($record, $state),
        );
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
     * with no SQL equivalent, so those default off there.
     */
    protected function isSortableByDefault(): bool
    {
        return ! $this->type->isRelational() && ! $this->type->isMultiple() && ! $this->isMultipleValued();
    }

    protected function isSearchableByDefault(): bool
    {
        return $this->type->isTextual() || $this->type === FieldType::LongText;
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
     * the table's formats, as a list column does.
     */
    public function formatLoggedValue(mixed $value, Table $table): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '-';
        }

        return match ($this->type) {
            FieldType::Boolean => $value ? __('Yes') : __('No'),
            FieldType::Select, FieldType::Multiselect => implode(', ', array_map(fn (mixed $one): string => $this->optionLabel($one), (array) $value)),
            FieldType::CommonData => implode(', ', array_map(fn (mixed $one): string => $this->commonDataLabel($one), (array) $value)),
            FieldType::Relation => $this->loggedRelatedTitle($value),
            // A date is logged either as it was typed or as midnight UTC:
            // either way it is the day that was meant, so no time zone shift.
            FieldType::Date => Carbon::parse($value)->translatedFormat($table->getDefaultDateDisplayFormat()),
            FieldType::DateTime => Carbon::parse($value)->setTimezone(FilamentTimezone::get())->translatedFormat($table->getDefaultDateTimeDisplayFormat()),
            FieldType::Time => Carbon::parse($value)->translatedFormat($table->getDefaultTimeDisplayFormat()),
            default => is_array($value) ? implode(', ', $value) : (string) $value,
        };
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

    // ------------------------------------------------------------- Helpers --

    protected function lengthParam(): int
    {
        return (int) $this->getParam('length', 255);
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
        if (! is_string($model) || Filament::getCurrentPanel() === null) {
            return null;
        }

        return Filament::getModelResource($model);
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
