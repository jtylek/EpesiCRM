<?php

namespace Epesi\Modules\RecordBrowser\Filament\Pages;

use App\Filament\Concerns\HasResourceIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use Epesi\Modules\RecordBrowser\Browsing\BrowseMode;
use Epesi\Modules\RecordBrowser\Browsing\Favorites;
use Epesi\Modules\RecordBrowser\Browsing\RecentRecords;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Resources\Pages\ListRecords as BaseListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

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
                    ->visible(fn (): bool => $this->getBrowseMode() === BrowseMode::Recent),
            ]);
        }

        return $table;
    }

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

    protected function getRecordType(): string
    {
        return app(static::getModel())->getMorphClass();
    }
}
