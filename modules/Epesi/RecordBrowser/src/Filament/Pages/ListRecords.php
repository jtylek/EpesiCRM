<?php

namespace Epesi\Modules\RecordBrowser\Filament\Pages;

use App\Filament\Concerns\HasResourceIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Models\User;
use App\Support\StatusField;
use Carbon\Carbon;
use Epesi\Modules\RecordBrowser\Browsing\BrowseMode;
use Epesi\Modules\RecordBrowser\Browsing\Favorites;
use Epesi\Modules\RecordBrowser\Browsing\RecentRecords;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Filament\Resources\Pages\ListRecords as BaseListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Shared base for every resource's List page — extend this instead of
 * Filament's own ListRecords. See HasResourceIconBreadcrumb for why.
 *
 * A recordset that keeps favorites or recent records gets All / Favorites /
 * Recent tabs in its table's toolbar (see BrowseMode). The tab is remembered
 * per user and record type, so the list reopens in whichever was chosen last.
 */
abstract class ListRecords extends BaseListRecords
{
    use HasResourceIconBreadcrumb;
    use HidesPageHeading;

    /**
     * Looked up once per request rather than once per row.
     *
     * @var array<int, int|string>|null
     */
    protected ?array $favoriteKeys = null;

    /** @var array<int|string, string>|null */
    protected ?array $visits = null;

    public function getTabs(): array
    {
        $modes = $this->getBrowseModes();

        if (count($modes) < 2) {
            return [];
        }

        return collect($modes)
            ->mapWithKeys(fn (BrowseMode $mode): array => [
                $mode->value => Tab::make($mode->getLabel())
                    ->icon($mode->getIcon())
                    ->modifyQueryUsing(fn (Builder $query): Builder => $this->browse($query, $mode)),
            ])
            ->all();
    }

    /**
     * Not above the table, where Filament puts it: RecordBrowserServiceProvider
     * draws the same tabs inside the table's toolbar instead.
     */
    public function getTabsContentComponent(): Component
    {
        return parent::getTabsContentComponent()->hidden();
    }

    public function getDefaultActiveTab(): string|int|null
    {
        $remembered = $this->getCachedTabs() === []
            ? null
            : BrowseMode::rememberedFor(Auth::user(), $this->getRecordType());

        return $remembered && array_key_exists($remembered->value, $this->getCachedTabs())
            ? $remembered->value
            : parent::getDefaultActiveTab();
    }

    public function updatedActiveTab(): void
    {
        parent::updatedActiveTab();

        if (array_key_exists((string) $this->activeTab, $this->getCachedTabs())) {
            $this->getBrowseMode()->rememberFor(Auth::user(), $this->getRecordType());
        }
    }

    public function getBrowseMode(): BrowseMode
    {
        return BrowseMode::tryFrom((string) $this->activeTab) ?? BrowseMode::All;
    }

    public function table(Table $table): Table
    {
        $modes = $this->getBrowseModes();

        if (in_array(BrowseMode::Favorites, $modes, true)) {
            $table->pushRecordActions([
                Favorites::toggleAction(fn (Model $record): bool => in_array(
                    $record->getKey(),
                    $this->favoriteKeys ??= Favorites::keysFor(Auth::user(), $this->getRecordType()),
                ))
                    ->iconButton()
                    ->after(fn () => $this->favoriteKeys = null),
            ]);
        }

        if (in_array(BrowseMode::Recent, $modes, true)) {
            $table->pushColumns([
                TextColumn::make('recordbrowser_visited_at')
                    ->label('Visited')
                    ->dateTime()
                    ->state(fn (Model $record): ?string => ($this->visits ??= RecentRecords::visitsOf(Auth::user(), $this->getRecordType()))[$record->getKey()] ?? null)
                    ->formatStateUsing(fn (?string $state): ?string => filled($state)
                        ? RegionalSetting::display(Carbon::parse($state))
                        : null)
                    ->visible(fn (): bool => $this->getBrowseMode() === BrowseMode::Recent)
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
        }

        // A list with no Employees filter (Contacts, Companies) still gets a
        // "My records": the ones its user created or has changed.
        if ($table->getFilter('employees') === null
            && $table->getFilter('employee') === null
            && $table->getFilter('edited_by') === null
            && $table->getFilter(self::MY_RECORDS_FILTER) === null
            && method_exists($model = app(static::getModel()), 'activities')
            && static::hasCreatedBy($model->getTable())) {
            $table->pushFilters([
                Filter::make(self::MY_RECORDS_FILTER)
                    ->label('My records')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                        ->where($query->getModel()->qualifyColumn('created_by'), Auth::id())
                        ->orWhereHas('activities', fn (Builder $activities): Builder => $activities
                            ->where('causer_type', (new User)->getMorphClass())
                            ->where('causer_id', Auth::id())))),
            ]);
        }

        $this->applyDefaultMyRecords($table);

        return $table;
    }

    /** @var array<string, bool> table => has a created_by column, for this process */
    protected static array $createdByColumns = [];

    /**
     * Schema::hasColumn() is an information_schema query on MySQL, and
     * table() runs on every list page and every Livewire request on one. A
     * table's columns only change in a migration, which a new process follows.
     */
    protected static function hasCreatedBy(string $table): bool
    {
        return static::$createdByColumns[$table] ??= Schema::hasColumn($table, 'created_by');
    }

    /** The "My records" toggle filter of a list that has no Employees filter. */
    public const MY_RECORDS_FILTER = 'my_records';

    /**
     * @return array<int, BrowseMode>
     */
    protected function getBrowseModes(): array
    {
        $resource = static::getResource();

        if (! is_subclass_of($resource, RecordsetResource::class)) {
            return [BrowseMode::All];
        }

        return array_values(array_filter([
            BrowseMode::All,
            $resource::hasFavorites() ? BrowseMode::Favorites : null,
            $resource::getRecentLimit() > 0 ? BrowseMode::Recent : null,
        ]));
    }

    protected function browse(Builder $query, BrowseMode $mode): Builder
    {
        return match ($mode) {
            BrowseMode::All => $query,
            BrowseMode::Favorites => Favorites::onlyFavorites($query, Auth::user()),
            // A column the user sorts by wins; the visit order is only the default.
            BrowseMode::Recent => RecentRecords::onlyRecent($query, Auth::user(), latestFirst: blank($this->getTableSortColumn())),
        };
    }

    /**
     * The "My records" quick filter (AI-shared/conventions.md):
     * null when the list has no Employees filter to set. A list that also has a
     * Not closed status filter (StatusField) gets the active variant.
     *
     * @return array{label: string, active: bool}|null
     */
    public function getMyRecordsButton(): ?array
    {
        $employees = $this->myRecordsEmployeesFilter();

        if ($employees === null && $this->getTable()->getFilter(self::MY_RECORDS_FILTER)) {
            return ['label' => __('My records'), 'active' => $this->myCreatedOrChangedAreShown()];
        }

        if ($employees === null || $this->myId($employees) === null) {
            return null;
        }

        return [
            'label' => __('My records'),
            'active' => $this->myRecordsAreShown(),
        ];
    }

    public function toggleMyRecords(): void
    {
        $employees = $this->myRecordsEmployeesFilter();
        $contactId = $employees ? $this->myId($employees) : null;

        if ($employees === null && $this->getTable()->getFilter(self::MY_RECORDS_FILTER)) {
            $wasActive = $this->myCreatedOrChangedAreShown();
            $status = $this->keptStatusState();

            $this->removeTableFilters();

            if ($status !== null) {
                $this->tableFilters = $status;
            }

            if (! $wasActive) {
                $this->tableFilters = [...($this->tableFilters ?? []), self::MY_RECORDS_FILTER => ['isActive' => true]];
                $this->handleTableFilterUpdates();
            }

            return;
        }

        if ($employees === null || $contactId === null) {
            return;
        }

        $wasActive = $this->myRecordsAreShown();
        $status = $this->keptStatusState();

        $this->removeTableFilters();

        if ($status !== null) {
            $this->tableFilters = $status;
        }

        if ($wasActive) {
            $this->handleTableFilterUpdates();

            return;
        }

        $filters = $this->tableFilters ?? [];
        $filters[$employees->getName()] = $employees->isMultiple()
            ? ['values' => [(string) $contactId]]
            : ['value' => (string) $contactId];

        $this->tableFilters = $filters;
        $this->handleTableFilterUpdates();
    }

    /**
     * The Active / Inactive / All select: null when the list has no
     * closed/canceled status. `value` is "active", "inactive", "all" while the
     * status filter is empty, or "other" while it names one particular status.
     *
     * @return array{value: string}|null
     */
    public function getInactiveToggle(): ?array
    {
        $status = $this->myRecordsStatusFilter();

        if ($status === null) {
            return null;
        }

        return ['value' => match ($this->tableFilters[$status->getName()]['value'] ?? null) {
            StatusField::NOT_CLOSED => 'active',
            StatusField::INACTIVE => 'inactive',
            null, '' => 'all',
            default => 'other',
        }];
    }

    public function setStatusMode(string $mode): void
    {
        $status = $this->myRecordsStatusFilter();

        if ($status === null) {
            return;
        }

        $this->tableFilters = [
            ...($this->tableFilters ?? []),
            $status->getName() => ['value' => match ($mode) {
                'active' => StatusField::NOT_CLOSED,
                'inactive' => StatusField::INACTIVE,
                default => null, // "all": no status filter
            }],
        ];
        $this->handleTableFilterUpdates();
    }

    /** The I shortcut: Active -> Inactive -> All -> Active (any other status goes to Active). */
    public function toggleStatusMode(): void
    {
        $this->setStatusMode(match ($this->getInactiveToggle()['value']) {
            'active' => 'inactive',
            'inactive' => 'all',
            default => 'active',
        });
    }

    /** The My records / All records select. */
    public function setMyRecordsMode(string $mode): void
    {
        if (($mode === 'mine') !== $this->myRecordsAreOn()) {
            $this->toggleMyRecords();
        }
    }

    protected function myRecordsAreOn(): bool
    {
        return $this->myRecordsEmployeesFilter() === null
            ? $this->myCreatedOrChangedAreShown()
            : $this->myRecordsAreShown();
    }

    /** The status filter's state, which "My records" leaves alone when it resets the other filters. */
    protected function keptStatusState(): ?array
    {
        $status = $this->myRecordsStatusFilter();

        return $status && isset($this->tableFilters[$status->getName()])
            ? [$status->getName() => $this->tableFilters[$status->getName()]]
            : null;
    }

    protected function myCreatedOrChangedAreShown(): bool
    {
        return (bool) ($this->tableFilters[self::MY_RECORDS_FILTER]['isActive'] ?? false);
    }

    protected function myRecordsAreShown(): bool
    {
        $employees = $this->myRecordsEmployeesFilter();

        if ($employees === null) {
            return false;
        }

        $contactId = (string) $this->myId($employees);

        $state = $this->tableFilters[$employees->getName()] ?? [];
        $selected = array_map('strval', (array) ($state['values'] ?? $state['value'] ?? []));

        return $selected === [$contactId];
    }

    protected function myRecordsEmployeesFilter(?Table $table = null): ?SelectFilter
    {
        foreach (['employees', 'employee', 'edited_by'] as $name) {
            $filter = ($table ?? $this->getTable())->getFilter($name);

            if ($filter instanceof SelectFilter) {
                return $filter;
            }
        }

        return null;
    }

    /**
     * A fresh login opens every list on "My records" (or "My active records").
     * Only once per session: after that the filters the user left — including
     * none at all — are the ones persisted in the session.
     */
    protected function applyDefaultMyRecords(Table $table): void
    {
        // Not "<filters key>.x": a dot would nest the marker inside the filters array.
        $marker = 'my_records_default:'.$this->getTableFiltersSessionKey();

        if (session()->has($marker)) {
            return;
        }

        session()->put($marker, true);

        if (filled($this->tableFilters) || filled(session()->get($this->getTableFiltersSessionKey()))) {
            return;
        }

        $employees = $this->myRecordsEmployeesFilter($table);

        if ($employees === null) {
            $this->tableFilters = $table->getFilter(self::MY_RECORDS_FILTER)
                ? [self::MY_RECORDS_FILTER => ['isActive' => true]]
                : null;

            return;
        }

        $contactId = $this->myId($employees);

        if ($contactId === null) {
            return;
        }

        $filters = [$employees->getName() => $employees->isMultiple()
            ? ['values' => [(string) $contactId]]
            : ['value' => (string) $contactId]];

        if ($status = $this->myRecordsStatusFilter($table)) {
            $filters[$status->getName()] = ['value' => StatusField::NOT_CLOSED];
        }

        $this->tableFilters = $filters;
    }

    protected function myRecordsStatusFilter(?Table $table = null): ?SelectFilter
    {
        $filter = ($table ?? $this->getTable())->getFilter('status');

        return $filter instanceof SelectFilter && array_key_exists(StatusField::NOT_CLOSED, $filter->getOptions())
            ? $filter
            : null;
    }

    /** Notes have no Employees: their "Edited by" filter holds a user id, not a contact's. */
    protected function myId(SelectFilter $filter): int|string|null
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        return $filter->getName() === 'edited_by' ? $user->getKey() : $user->contact?->getKey();
    }

    protected function getRecordType(): string
    {
        return app(static::getModel())->getMorphClass();
    }
}
