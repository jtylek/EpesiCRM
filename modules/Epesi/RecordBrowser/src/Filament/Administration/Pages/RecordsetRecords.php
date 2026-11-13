<?php

namespace Epesi\Modules\RecordBrowser\Filament\Administration\Pages;

use App\Support\Demo;
use Carbon\Carbon;
use Epesi\Modules\RecordBrowser\Filament\Administration\RecordInAdministration;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Enumerable;

/**
 * The recordset's records, in its own list's table (the resource's columns
 * and search), for the administrator: every record, deleted ones too, with a
 * Records select above the table (Active, the default / Deleted / All) and
 * Restore for the deleted. Nothing else of the main panel's List page comes
 * along (no My records, Favorites, Recent, inactive switch, export, bulk
 * delete, nor the list's own filters): those are the user's ways of
 * browsing, drawn by ListRecords, which this page isn't. Rows open the
 * record's View page here in the administration panel
 * (RecordInAdministration); Edit leads to the main panel's Edit page.
 *
 * All records: the ownership scope (HasOwnershipVisibility) already lets a
 * super admin see everything, and this page is a super admin's only, so
 * only the soft-delete scope is lifted here.
 *
 * A recordset whose model doesn't soft-delete gets the plain list: its own
 * filters in the funnel (a default of their own, such as Tasks' "Not closed",
 * starting empty), no Records select, column or Restore.
 */
class RecordsetRecords extends RecordsetPage implements HasTable
{
    use InteractsWithTable;

    /** The Active / Deleted records filter. Not "trashed": Filament's bulk actions read that one as TrashedFilter. */
    public const DELETED_FILTER = 'deleted';

    protected static ?string $slug = 'recordsets/{alias}/records';

    protected string $view = 'filament-panels::pages.page';

    /** @var class-string<RecordsetResource>|null found once per request (not a Livewire property) */
    protected ?string $resourceClass = null;

    public static function tabLabel(): string
    {
        return 'Records';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $resource = $this->recordsetResource();

        $resource::configureTable($table);

        $table
            ->query(fn (): Builder => $this->recordsQuery())
            ->heading(fn () => view('epesi-recordbrowser::table-record-count', [
                'count' => $this->recordCount(),
            ]))
            ->recordUrl(fn (Model $record): ?string => $this->viewUrl($record) ?? $this->mainPanelUrl('edit', $record))
            ->toolbarActions([]);

        if (! $this->softDeletes()) {
            return $table->filters(collect($table->getFilters(withHidden: true))
                ->each(fn (BaseFilter $filter): BaseFilter => $filter->default(null))
                ->values()
                ->all());
        }

        $deletedAt = $this->deletedAtColumn();

        return $table
            // Only the Records select, shown above the table and applied on change,
            // in one row with the record count and the search (the field list's
            // layout, recordset-catalogue.css). The list's own filters would all
            // come out of the funnel into that row, so they stay on the main list.
            ->extraAttributes(['class' => 'epesi-recordset-catalogue'])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filters([
                SelectFilter::make(self::DELETED_FILTER)
                    ->label('Records')
                    ->options([
                        'active' => __('Active'),
                        'deleted' => __('Deleted'),
                        'all' => __('All'),
                    ])
                    ->default('active')
                    ->selectablePlaceholder(false)
                    // The select says it already; no "Records: Active" chip under it.
                    ->indicateUsing(fn (): array => [])
                    // A row's own actions (Restore) find their record whatever the select shows.
                    ->excludeWhenResolvingRecord()
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'deleted' => $query->whereNotNull($deletedAt),
                        'all' => $query,
                        default => $query->whereNull($deletedAt),
                    }),
            ])
            ->pushColumns([
                TextColumn::make((new ($resource::getModel()))->getDeletedAtColumn())
                    ->label('Deleted on')
                    ->sortable()
                    ->formatStateUsing(fn ($state): ?string => filled($state)
                        ? RegionalSetting::display(Carbon::parse($state))
                        : null),
            ])
            ->pushRecordActions([
                Demo::guard(RestoreAction::make()->iconButton()->tooltip(__('Restore'))),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    Demo::guard(RestoreBulkAction::make()
                        // Only the deleted among the selected: restoring an active record
                        // would still write "restored" into its history.
                        ->using(function (Enumerable $records): void {
                            $records
                                ->filter(fn (Model $record): bool => $record->trashed())
                                ->each(function (Model $record): void {
                                    $record->restore();
                                });
                        })
                        // Nothing to restore among the active records (the select's default).
                        ->hidden(fn (): bool => in_array($this->getTableFilterState(self::DELETED_FILTER)['value'] ?? null, [null, 'active'], true))),
                ]),
            ]);
    }

    /**
     * The resource's own query, deleted records included. Restoring goes
     * through the model's restore(), so the record's history gets its
     * "restored" entry and its links and collections come back, as with the
     * Restore button on its Edit page.
     */
    protected function recordsQuery(): Builder
    {
        $query = $this->recordsetResource()::getEloquentQuery();

        return $this->softDeletes()
            ? $query->withoutGlobalScope(SoftDeletingScope::class)
            : $query;
    }

    protected function recordCount(): int
    {
        $this->getTableRecords();

        return $this->getAllTableRecordsCount();
    }

    /**
     * The main panel's RecordsetResource of this recordset. Every recordset on
     * these tabs has one (Recordsets::recordsets() lists them from it).
     *
     * @return class-string<RecordsetResource>
     */
    protected function recordsetResource(): string
    {
        return $this->resourceClass ??= RecordInAdministration::resourceFor($this->alias) ?? abort(404);
    }

    protected function softDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->recordsetResource()::getModel()), true);
    }

    protected function deletedAtColumn(): string
    {
        $model = $this->recordsetResource()::getModel();

        return (new $model)->getQualifiedDeletedAtColumn();
    }

    /**
     * Restore is the administrator's: this page's own access (an active super
     * admin, not in demo mode) on top of the record's policy, which Shield
     * waves a super admin through. Checked again on every action call, since
     * a Livewire request doesn't pass the page's access check again.
     */
    protected function mayRestore(): bool
    {
        return static::canAccess() && $this->softDeletes();
    }

    /** A page of the main panel for $record, when it has one and the user may open it. */
    protected function mainPanelUrl(string $page, Model $record): ?string
    {
        $resource = $this->recordsetResource();

        if (! $resource::hasPage($page) || ! $resource::{'can'.ucfirst($page)}($record)) {
            return null;
        }

        return $resource::getUrl($page, ['record' => $record], panel: 'main');
    }

    /**
     * The recordset's View page for $record, here in the administration panel
     * (RecordInAdministration), when the recordset has one and the user may view it.
     */
    protected function viewUrl(Model $record): ?string
    {
        $resource = $this->recordsetResource();

        if (! $resource::hasPage('view') || ! $resource::canView($record)) {
            return null;
        }

        return RecordInAdministration::url($this->alias, $record);
    }

    // The resource's View/Edit actions and Filament's Restore ones ask the page
    // for their URL and authorization, which only a resource's own pages answer.

    public function getDefaultActionUrl(Action $action): ?string
    {
        return match (true) {
            $action instanceof ViewAction => $this->viewUrl($action->getRecord()),
            $action instanceof EditAction => $this->mainPanelUrl('edit', $action->getRecord()),
            default => parent::getDefaultActionUrl($action),
        };
    }

    public function getDefaultActionAuthorizationResponse(Action $action): ?Response
    {
        $resource = $this->recordsetResource();

        return match (true) {
            $action instanceof RestoreAction => $this->mayRestore()
                ? $resource::getRestoreAuthorizationResponse($action->getRecord())
                : Response::deny(),
            $action instanceof RestoreBulkAction => $this->mayRestore()
                ? $resource::getRestoreAnyAuthorizationResponse()
                : Response::deny(),
            $action instanceof ViewAction => $resource::getViewAuthorizationResponse($action->getRecord()),
            $action instanceof EditAction => $resource::getEditAuthorizationResponse($action->getRecord()),
            default => parent::getDefaultActionAuthorizationResponse($action),
        };
    }

    public function getDefaultActionIndividualRecordAuthorizationResponseResolver(Action $action): ?\Closure
    {
        $resource = $this->recordsetResource();

        return $action instanceof RestoreBulkAction
            ? fn (Model $record): Response => $resource::getRestoreAuthorizationResponse($record)
            : parent::getDefaultActionIndividualRecordAuthorizationResponseResolver($action);
    }

    // One recordset's sort, search, filters and columns aren't another's: the
    // table remembers them per recordset, not once for this page class.

    public function getTableFiltersSessionKey(): string
    {
        return $this->recordsetTableSessionKey('filters');
    }

    public function getTableSearchSessionKey(): string
    {
        return $this->recordsetTableSessionKey('search');
    }

    public function getTableColumnSearchesSessionKey(): string
    {
        return $this->recordsetTableSessionKey('column_search');
    }

    public function getTableSortSessionKey(): string
    {
        return $this->recordsetTableSessionKey('sort');
    }

    public function getTableColumnsSessionKey(): string
    {
        return $this->recordsetTableSessionKey('columns');
    }

    public function getHasReorderedTableColumnsSessionKey(): string
    {
        return $this->recordsetTableSessionKey('has_reordered_columns');
    }

    protected function recordsetTableSessionKey(string $suffix): string
    {
        return 'tables.'.md5(static::class.'|'.$this->alias).'_'.$suffix;
    }
}
