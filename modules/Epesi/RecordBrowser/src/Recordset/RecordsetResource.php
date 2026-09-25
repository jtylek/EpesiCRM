<?php

namespace Epesi\Modules\RecordBrowser\Recordset;

use App\Filament\Concerns\TranslatesResourceLabels;
use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldRegistry;
use Epesi\Modules\RecordBrowser\Filament\RelationManagers\HistoryRelationManager;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use LogicException;

/**
 * The engine. A recordset declares its fields and nothing else; List, View,
 * Create and Edit are built here, at run time, from that one list — the port of
 * what `Utils_RecordBrowser` does with a `<table>_field` table.
 *
 *     class NoteResource extends RecordsetResource
 *     {
 *         protected static ?string $model = Note::class;
 *
 *         public static function fields(): array
 *         {
 *             return [
 *                 Field::text('title')->required()->inTable(),
 *                 Field::relation('contact_id', Contact::class)->inTable(),
 *                 Field::boolean('pinned')->inTable(),
 *                 Field::longText('content'),
 *             ];
 *         }
 *     }
 *
 * Code generation was rejected as the primary mechanism: generated screens
 * become the developer's code the moment they are written, and then drift. This
 * owns the behaviour permanently instead, which works because every part of a
 * Filament resource that decides behaviour is a static *method*, not a static
 * array.
 *
 * **Fields an administrator added are appended to the same list** (§ the
 * `custom_fields` table, CustomFieldRegistry), so a GUI-added field reaches the
 * form, the view, the table, the filters and the history through exactly the
 * code path a shipped one does. That single code path is the design, not an
 * optimisation.
 *
 * **What a recordset still writes itself:** a model, a migration, and four page
 * stubs naming this resource. The stubs cannot be shared — Filament asks the
 * *page class* for resource-level middleware decisions while building routes,
 * before there is a request to disambiguate from — but they hold no logic and
 * `make:epesi-recordset` writes them once.
 *
 * **Overriding is expected, not a failure.** Per-field escape hatches live on
 * Field (`->formUsing()`, `->viewUsing()`, `->columnUsing()`, `->filterUsing()`);
 * whole-schema ones are the extendForm/extendInfolist/extendTable hooks below.
 * Epesi's own recordsets end up carrying callbacks (`CRM/Contacts` uses six),
 * and an engine that makes overriding awkward pushes developers off it
 * altogether.
 */
abstract class RecordsetResource extends Resource
{
    use TranslatesResourceLabels;

    /** Column the list sorts by out of the box; null leaves Filament's default. */
    protected static ?string $recordsetDefaultSort = null;

    protected static string $recordsetDefaultSortDirection = 'asc';

    /**
     * A star on each record and a Favorites tab on the list — Epesi's
     * `set_favorites()`.
     */
    protected static bool $recordsetFavorites = false;

    /**
     * How many recently opened records to keep per user, shown in a Recent
     * tab on the list; 0 keeps none and shows no tab — Epesi's `set_recent()`.
     */
    protected static int $recordsetRecent = 0;

    public static function hasFavorites(): bool
    {
        return static::$recordsetFavorites;
    }

    public static function getRecentLimit(): int
    {
        return static::$recordsetRecent;
    }

    /**
     * Every field of this recordset, in the order they should appear.
     *
     * @return array<int, Field>
     */
    abstract public static function fields(): array;

    /**
     * Addon tabs (Filament relation managers) other than History, which the
     * engine adds itself.
     *
     * @return array<int, class-string>
     */
    public static function addons(): array
    {
        return [];
    }

    /**
     * The module's fields plus the administrator's, as one list. A definition in
     * `fields()` wins over a custom field of the same name, so a field promoted
     * from GUI-added to module-shipped keeps working.
     *
     * @return array<int, Field>
     */
    public static function resolvedFields(): array
    {
        $declared = static::fields();
        $names = array_map(fn (Field $field): string => $field->name, $declared);

        $custom = array_filter(
            CustomFieldRegistry::fieldsFor(static::getModel()),
            fn (Field $field): bool => ! in_array($field->name, $names, true),
        );

        return [...$declared, ...array_values($custom)];
    }

    // ---------------------------------------------------------------- Form --

    public static function form(Schema $schema): Schema
    {
        $sections = static::groupIntoSections(
            array_filter(static::resolvedFields(), fn (Field $field): bool => $field->isInForm()),
            fn (Field $field): mixed => $field->toFormComponent(),
        );

        return static::extendForm($schema->components($sections));
    }

    public static function infolist(Schema $schema): Schema
    {
        $sections = static::groupIntoSections(
            array_filter(static::resolvedFields(), fn (Field $field): bool => $field->isInView()),
            fn (Field $field): mixed => $field->toInfolistEntry(),
            columnFlow: true,
        );

        return static::extendInfolist($schema->inlineLabel()->components($sections));
    }

    /**
     * Fields carrying no `->section()` go into a leading untitled card, the way
     * every hand-written schema in this app is laid out; named sections follow
     * in the order they first appear in `fields()`. This is Epesi's
     * `page_split`, expressed as a property of the field rather than a marker
     * you position between fields.
     *
     * @param  array<int, Field>  $fields
     * @param  callable(Field): mixed  $build
     * @param  bool  $columnFlow  read down the first column, then down the second,
     *                            rather than across each row (see flowIntoColumns)
     * @return array<int, Section>
     */
    protected static function groupIntoSections(array $fields, callable $build, bool $columnFlow = false): array
    {
        $grouped = [];
        $options = [];

        foreach ($fields as $field) {
            $title = $field->getSection() ?? '';
            $grouped[$title][] = [$field, $build($field)];

            // First field to name a section decides how it renders.
            $options[$title] ??= [
                $field->isSectionCollapsible(),
                $field->isSectionCollapsed(),
                $field->getSectionDescription(),
            ];
        }

        $sections = [];

        foreach ($grouped as $title => $pairs) {
            [$collapsible, $collapsed, $description] = $options[$title];

            $components = $columnFlow
                ? static::flowIntoColumns($pairs)
                : array_column($pairs, 1);

            $section = Section::make($title === '' ? null : __($title))
                ->description($description)
                ->columnSpanFull()
                ->columns(2)
                ->components($components);

            $sections[] = $collapsed
                ? $section->collapsed()
                : ($collapsible ? $section->collapsible() : $section);
        }

        return $sections;
    }

    /**
     * Two-column reading order for the View page: the fields in `fields()`
     * order run down the first column and carry on down the second, as in
     * Epesi's two-column record view, instead of Filament's row by row.
     *
     * A grid can't flow that way around a field spanning both columns, so each
     * stretch of ordinary fields becomes its own grid (the CSS that turns on
     * the column flow lives with the other panel-wide View styles) and a
     * full-width field stands between them. Below the `lg` breakpoint it is
     * one column in `fields()` order, as before.
     *
     * @param  array<int, array{0: Field, 1: mixed}>  $pairs
     * @return array<int, mixed>
     */
    protected static function flowIntoColumns(array $pairs): array
    {
        $blocks = [];
        $run = [];

        $endRun = function () use (&$blocks, &$run): void {
            if ($run === []) {
                return;
            }

            $blocks[] = Grid::make(2)
                ->columnSpanFull()
                ->extraAttributes(['class' => 'rb-column-flow', 'style' => '--rb-rows: '.(int) ceil(count($run) / 2)])
                ->components($run);
            $run = [];
        };

        foreach ($pairs as [$field, $component]) {
            if ($field->isFullWidth()) {
                $endRun();
                $blocks[] = $component;
            } else {
                $run[] = $component;
            }
        }

        $endRun();

        return $blocks;
    }

    // --------------------------------------------------------------- Table --

    public static function table(Table $table): Table
    {
        $fields = array_values(array_filter(
            static::resolvedFields(),
            fn (Field $field): bool => $field->isInColumnChooser(),
        ));

        $filters = array_values(array_filter(array_map(
            fn (Field $field): mixed => $field->toTableFilter(),
            $fields,
        )));

        if (static::modelSoftDeletes()) {
            $filters[] = TrashedFilter::make();
        }

        $table = $table
            ->columns([
                ...array_map(fn (Field $field): mixed => $field->toTableColumn(), $fields),
                ...static::standardTableColumns($fields),
            ])
            ->filters($filters)
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip(__('View')),
                EditAction::make()->iconButton()->tooltip(__('Edit')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ...(static::modelSoftDeletes() ? [
                        ForceDeleteBulkAction::make(),
                        RestoreBulkAction::make(),
                    ] : []),
                ]),
            ]);

        if (static::$recordsetDefaultSort !== null) {
            $table = $table->defaultSort(static::$recordsetDefaultSort, static::$recordsetDefaultSortDirection);
        }

        return static::extendTable($table);
    }

    /**
     * Created/updated/deleted and who by — available in the column chooser on
     * every recordset, hidden until asked for, matching what the hand-written
     * tables in this app already offer. A recordset that wants one of these
     * *visible* declares it in `fields()` (`Field::dateTime('updated_at')
     * ->label('Updated')->onlyInTable()`); the declaration wins and this stops
     * adding it.
     *
     * @param  array<int, Field>  $declared
     * @return array<int, TextColumn>
     */
    protected static function standardTableColumns(array $declared): array
    {
        $model = static::getModel();
        $taken = array_map(fn (Field $field): string => $field->getStateName(), $declared);

        $columns = [];

        $add = function (string $name, callable $build) use (&$columns, $taken): void {
            if (! in_array($name, $taken, true)) {
                $columns[] = $build()->toggleable(isToggledHiddenByDefault: true);
            }
        };

        if (method_exists($model, 'creator')) {
            $add('creator.name', fn (): TextColumn => TextColumn::make('creator.name')->label('Created By'));
        }

        $add('created_at', fn (): TextColumn => TextColumn::make('created_at')->dateTime()->sortable());
        $add('updated_at', fn (): TextColumn => TextColumn::make('updated_at')->dateTime()->sortable());

        if (static::modelSoftDeletes()) {
            $add('deleted_at', fn (): TextColumn => TextColumn::make('deleted_at')->dateTime()->sortable());
        }

        return $columns;
    }

    /**
     * Filament's own default is `[$recordTitleAttribute]`; every searchable
     * field of the recordset is a better answer, and it is the nearest thing to
     * Epesi's `recordbrowser_search_index` that costs nothing to maintain.
     *
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        $searchable = array_values(array_map(
            fn (Field $field): string => $field->name,
            array_filter(
                static::resolvedFields(),
                fn (Field $field): bool => ! $field->type->isRelational()
                    && ($field->type->isTextual() || $field->type === FieldType::LongText),
            ),
        ));

        return $searchable !== [] ? $searchable : parent::getGloballySearchableAttributes();
    }

    // ---------------------------------------------------- Pages and addons --

    /**
     * History is every recordset's last addon tab, from one shared relation
     * manager rather than a copy per resource — unlike page classes, a relation
     * manager is not bound to a resource at route-build time, so it *can* be
     * shared.
     *
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        $addons = static::addons();

        if (method_exists(static::getModel(), 'activities')) {
            $addons[] = HistoryRelationManager::class;
        }

        return $addons;
    }

    /**
     * Resolved by convention from the resource's own name, so a recordset
     * declares no page list: NoteResource looks for Pages\ListNotes,
     * Pages\CreateNote, Pages\ViewNote and Pages\EditNote in its own namespace.
     * That is exactly what `make:filament-resource` has always generated, so
     * an existing resource needs no renaming to move onto the engine.
     *
     * A page class that doesn't exist is simply not registered — which is how a
     * recordset opts out of, say, Create.
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        $namespace = Str::beforeLast(static::class, '\\').'\\Pages\\';
        $singular = Str::beforeLast(class_basename(static::class), 'Resource');

        $candidates = [
            'index' => ['List'.Str::plural($singular), '/'],
            'create' => ['Create'.$singular, '/create'],
            'view' => ['View'.$singular, '/{record}'],
            'edit' => ['Edit'.$singular, '/{record}/edit'],
        ];

        $pages = [];

        foreach ($candidates as $key => [$class, $route]) {
            $class = $namespace.$class;

            if (class_exists($class)) {
                $pages[$key] = $class::route($route);
            }
        }

        if ($pages === []) {
            throw new LogicException(
                static::class.' has no page classes. The engine looks for '.$namespace.'List'.Str::plural($singular)
                .' and its Create/View/Edit siblings — run `php artisan make:epesi-recordset` to generate the stubs.',
            );
        }

        return $pages;
    }

    /**
     * A soft-deleted record has to stay reachable by URL or Restore has nothing
     * to open — the same override every soft-deleting resource in this app
     * carries by hand.
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        $query = parent::getRecordRouteBindingEloquentQuery();

        return static::modelSoftDeletes()
            ? $query->withoutGlobalScopes([SoftDeletingScope::class])
            : $query;
    }

    // --------------------------------------------------------------- Hooks --

    /** Whole-schema escape hatch; per-field ones live on Field. */
    protected static function extendForm(Schema $schema): Schema
    {
        return $schema;
    }

    protected static function extendInfolist(Schema $schema): Schema
    {
        return $schema;
    }

    protected static function extendTable(Table $table): Table
    {
        return $table;
    }

    protected static function modelSoftDeletes(): bool
    {
        /** @var class-string<Model> $model */
        $model = static::getModel();

        return in_array(SoftDeletes::class, class_uses_recursive($model), true);
    }
}
