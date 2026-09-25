<?php

namespace Epesi\Modules\RecordBrowser\Filament\Pages;

use App\Filament\Concerns\HasResourceIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use Epesi\Modules\RecordBrowser\Browsing\Favorites;
use Epesi\Modules\RecordBrowser\Browsing\RecentRecords;
use Epesi\Modules\RecordBrowser\Extensions\RecordExtensions;
use Epesi\Modules\RecordBrowser\Filament\Schemas\RecordInfoEntries;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord as BaseViewRecord;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Shared base for every resource's View page — extend this instead of
 * Filament's own ViewRecord. Moves the "Record Info" section out of the
 * infolist and into its own tab alongside the addon tabs (Contacts,
 * History, ...) — "addon" being what we call a Filament `RelationManager`
 * tab in this codebase, matching Epesi's own term for the same concept: a
 * tab of related data attached to a record. Record Info reads like just
 * another addon's panel instead of a card sitting above them.
 *
 * Filament only builds tabs out of `RelationManager` classes
 * (Concerns\HasRelationManagers::getRelationManagersContentComponent()), so
 * there's no supported extension point for adding a non-relation tab to that
 * strip — this re-implements that method, adding Record Info and any
 * resource-specific static-entry tabs (e.g. Contact's Login) around them.
 * Every resource here lists its addons as plain class-strings (no
 * RelationGroup/RelationManagerConfiguration), so that's all this handles.
 *
 * **Tab order (general rule, not resource-specific):** real addons first
 * (e.g. Contacts on Company), then any `getAdditionalContentTabs()` entries
 * (e.g. Login on Contact), then Record Info, then History last — History
 * (identified by relationship name `activities`, the one every resource's
 * `ActivitiesRelationManager` copy uses) and Record Info are always the
 * last two tabs, in that order, on every resource.
 *
 * Deliberately does NOT call ->livewireProperty('activeRelationManager') the
 * way the base implementation does: that property is what Filament's own
 * Concerns\HasRelationManagers::renderingHasRelationManagers() resets to
 * `array_key_first($managers)` on every render whenever its value isn't one
 * of the *real* relation manager keys — which our extra tabs never are, so
 * selecting one would immediately snap back to the first addon. Omitting the
 * property falls back to Tabs' plain Alpine-only client-side tab state,
 * which isn't fought by that hook. Deep-linking to a tab comes from Tabs'
 * own persistTabInQueryString() instead of that property's `?relation=`:
 * `?tab=notes::tab` opens the Notes tab (see addonTab() for the key), which
 * is how a note page returns to it. Since Alpine's `activeTab` is a
 * plain 1-indexed position and History/Record Info no longer sit at a fixed end of
 * the array for every resource, the default tab is computed explicitly
 * (see below) rather than left at Alpine's own default of 1 — a resource
 * with no non-History addon (Contact, Task, Meeting, PhoneCall) would
 * otherwise default onto Login or Record Info instead of History.
 */
abstract class ViewRecord extends BaseViewRecord
{
    use HasResourceIconBreadcrumb;
    use HidesPageHeading;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        RecentRecords::opened(static::getResource(), $this->getRecord());
    }

    /**
     * The resource's own addons plus whatever other modules registered for
     * this record type through RecordExtensions (e.g. Attachments' Notes
     * tab). Going through getAllRelationManagers() keeps Filament's own
     * canViewForRecord() filtering in play for both halves.
     */
    protected function getAllRelationManagers(): array
    {
        return [
            ...parent::getAllRelationManagers(),
            ...RecordExtensions::addonsFor($this->getRecord()),
        ];
    }

    /**
     * Appends the favorite star, where the recordset keeps favorites, and
     * module-registered header actions (e.g. Watchdog's Watch toggle) after
     * the page's own, so every View page gets them without each
     * getHeaderActions() override having to remember a parent call.
     */
    public function cacheInteractsWithHeaderActions(): void
    {
        parent::cacheInteractsWithHeaderActions();

        $resource = static::getResource();
        $favorites = is_subclass_of($resource, RecordsetResource::class) && $resource::hasFavorites()
            ? [Favorites::toggleAction()]
            : [];

        foreach ([...$favorites, ...RecordExtensions::headerActionsFor($this->getRecord())] as $action) {
            if ($action instanceof ActionGroup) {
                $action->livewire($this);
                $this->mergeCachedActions($action->getFlatActions());
            } else {
                $this->cacheAction($action);
            }

            $this->cachedHeaderActions[] = $action;
        }
    }

    public function getRelationManagersContentComponent(): Component
    {
        $ownerRecord = $this->getRecord();
        $managerLivewireData = ['ownerRecord' => $ownerRecord, 'pageClass' => static::class];

        /** @var array<class-string<RelationManager>> $managers */
        $managers = $this->getCachedRelationManagers();

        $historyManagers = array_filter(
            $managers,
            fn (string $manager): bool => $manager::getRelationshipName() === 'activities',
        );
        $otherManagers = array_diff_key($managers, $historyManagers);

        $buildManagerTab = fn (string $manager): Tab => $manager::getTabComponent($ownerRecord, static::class)
            ->key(static::addonTab($manager), isInheritable: false)
            ->schema(fn (): array => [
                Livewire::make($manager, [...$managerLivewireData, ...$manager::getDefaultProperties()])
                    ->key($manager),
            ]);

        $tabs = collect($otherManagers)
            ->map($buildManagerTab)
            ->concat($this->getAdditionalContentTabs($ownerRecord))
            ->push(static::entriesTab('Record Info', $ownerRecord, RecordInfoEntries::components()))
            ->concat(collect($historyManagers)->map($buildManagerTab))
            ->all();

        // Default tab: the first real addon (Contacts on Company) if there
        // is one, otherwise History — never Record Info or an additional
        // content tab like Login. $otherManagers always sits first when
        // non-empty, and the (always present) History tab always sits last.
        $defaultActiveTab = $otherManagers === [] ? count($tabs) : 1;

        return Tabs::make()
            ->key('relationManagerTabs')
            ->activeTab($defaultActiveTab)
            ->persistTabInQueryString()
            ->contained(false)
            // Spacing around the strip: HasCompactTableStyles.
            ->extraAttributes(['class' => 'epesi-addon-tabs'])
            ->tabs($tabs);
    }

    /**
     * The `?tab=` value that opens $manager's addon — `history::tab` for
     * HistoryRelationManager, `phone-calls::tab` for PhoneCallsRelationManager.
     * Filament would key the tab by its slugged label, but the label is
     * translated ("Historia"), so a link built that way would miss the tab
     * for anyone reading another language.
     *
     * @param  class-string<RelationManager>  $manager
     */
    public static function addonTab(string $manager): string
    {
        return Str::kebab(Str::beforeLast(class_basename($manager), 'RelationManager')).'::tab';
    }

    /**
     * Extension point for a resource-specific tab that isn't backed by a
     * RelationManager/addon (e.g. Contact's "Login" section) — see the class
     * docblock for where this lands in the overall tab order (after the real
     * addons, before Record Info/History). Empty by default; override in a
     * resource's View page to add one.
     *
     * @return array<Tab>
     */
    protected function getAdditionalContentTabs(Model $ownerRecord): array
    {
        return [];
    }

    /**
     * A Tab of plain infolist entries (TextEntry etc.) bound to $record —
     * for a tab that isn't a RelationManager, e.g. Record Info or Login.
     *
     * `Tab::make(...)->model($record)->schema($entries)` looks like it should
     * work (Component::model() exists) but silently renders every entry
     * blank: Schema::getConstantState() walks up
     * `getParentComponent()->getContainer()->getConstantState()` looking for
     * a bound record, and that walk passes through the Tabs strip's own
     * schema, which has no record of its own and resolves to `[]` — an
     * empty array, not null, so the `??` chain stops there and never reaches
     * the record set on the Tab itself. Passing a pre-built `Schema` (with
     * `->record()` called directly on it) via `childComponents()` instead of
     * a bare component array via `schema()` binds the record on that
     * innermost schema directly, so it's found on the first step instead of
     * needing to fall through to the Tab.
     *
     * Entries are wrapped in a `Section` titled the same as the tab (rather
     * than passed straight to the tab's schema) so the panel reads like the
     * Contacts/History relation manager tabs next to it — each of those is a
     * bordered card with its own repeated heading, not a bare list.
     *
     * `compact()`/`dense()` (Filament's own header-padding/child-gap
     * modifiers) rather than hand-rolled CSS, since — unlike the panel-wide
     * tweaks in MainPanelProvider's STYLES_AFTER hook — this should only
     * affect these entries-only tabs, not every Section in the app (e.g. the
     * Address section on the main infolist).
     *
     * `$inlineLabel` defaults to false because it would misalign
     * `RecordInfoEntries` — those entries are already rendered as compact
     * single-line "Label: value" text via `hiddenLabel()` + `prefix()`
     * (see that class), so turning on the inline-label grid there would
     * only add a blank label column to their left. Callers whose entries
     * use ordinary visible labels (e.g. Contact's "Login" tab) should pass
     * true to match the same side-by-side layout as the main infolist.
     *
     * @param  array<Component>  $entries
     * @param  array<Action>  $headerActions
     */
    protected static function entriesTab(string $label, Model $record, array $entries, array $headerActions = [], bool $inlineLabel = false): Tab
    {
        return Tab::make($label)->childComponents(
            Schema::make()->record($record)->inlineLabel($inlineLabel)->components([
                Section::make(__($label))->columns(2)->compact()->dense()->headerActions($headerActions)->components($entries),
            ]),
        );
    }
}
