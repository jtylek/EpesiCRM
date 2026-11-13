<?php

namespace Epesi\Modules\RecordBrowser\Filament\Pages;

use App\Filament\Concerns\HasResourceIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use Epesi\Modules\RecordBrowser\Browsing\Favorites;
use Epesi\Modules\RecordBrowser\Browsing\RecentRecords;
use Epesi\Modules\RecordBrowser\Extensions\RecordExtensions;
use Epesi\Modules\RecordBrowser\Filament\Administration\RecordInAdministration;
use Epesi\Modules\RecordBrowser\Filament\Schemas\RecordInfoEntries;
use Epesi\Modules\RecordBrowser\Recordset\ClipboardPattern;
use Epesi\Modules\RecordBrowser\Recordset\IncomingLinks;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetFeatures;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord as BaseViewRecord;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

/**
 * Shared base for every resource's View page — extend this instead of
 * Filament's own ViewRecord. The tab strip holds only the resource's real
 * addons (Contacts, History, ...) — "addon" being what we call a Filament
 * `RelationManager` tab in this codebase, matching Epesi's own term for the
 * same concept: a tab of related data attached to a record. Record Info and
 * History aren't tabs: they sit behind a kebab (an icon-only `ActionGroup`)
 * at the very end of the header actions row — after Edit/Clone and whatever
 * a resource or module (Favorites, Watchdog's Watch, Mail's Send e-mail)
 * adds — since with every addon shown too, the tab strip got too busy to
 * scan, and neither one reads as "related records" the way an addon does.
 * Picking either from the kebab opens it as a modal.
 *
 * Filament only builds tabs out of `RelationManager` classes
 * (Concerns\HasRelationManagers::getRelationManagersContentComponent()), so
 * there's no supported extension point for adding a non-relation tab to that
 * strip — this re-implements that method, adding any resource-specific
 * static-entry tabs (getAdditionalContentTabs(), unused at present) after the
 * real addons. Every resource here lists its addons as plain class-strings
 * (no RelationGroup/RelationManagerConfiguration), so that's all this
 * handles. A resource with no real addons at all (e.g. Administration's
 * ViewUser, whose only RelationManager is History) ends up with an empty
 * strip, hidden rather than rendered.
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
 * is how a note page returns to it. The default active tab is always the
 * first addon (Alpine's own default of index 1) — Record Info and History
 * are never part of the tab array, so there's no longer a case where that
 * default needs to skip past them.
 *
 * A recordset's View page also opens under Administration → Recordsets →
 * Records (RecordInAdministration): the same page, with breadcrumbs back to
 * that list, Restore for a deleted record, and no Recent entry.
 */
abstract class ViewRecord extends BaseViewRecord
{
    use HasResourceIconBreadcrumb {
        HasResourceIconBreadcrumb::getBreadcrumbs as getResourceIconBreadcrumbs;
    }
    use HidesPageHeading;

    /**
     * Past this many characters an addon tab's title is cut short rather than
     * widening its badge (withTruncatedLabel()).
     */
    protected const TAB_LABEL_MAX_LENGTH = 15;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // An administrator looking a record up under Administration → Recordsets
        // isn't browsing it: it stays out of their Recent list.
        if (! RecordInAdministration::shows($this)) {
            RecentRecords::opened(static::getResource(), $this->getRecord());
        }
    }

    /**
     * Opened under Administration → Recordsets → Records (RecordInAdministration),
     * the way back is that list: Recordsets > Company > Records > the record.
     */
    public function getBreadcrumbs(): array
    {
        if (RecordInAdministration::shows($this)) {
            return RecordInAdministration::breadcrumbs(RecordInAdministration::alias(), $this->getRecordTitle());
        }

        return $this->getResourceIconBreadcrumbs();
    }

    /** Deleting it there leads back to the Records tab, not to the main panel's list. */
    public function getDefaultActionSuccessRedirectUrl(Action $action): ?string
    {
        if ($action instanceof DeleteAction && RecordInAdministration::shows($this)) {
            return RecordInAdministration::recordsUrl(RecordInAdministration::alias());
        }

        return parent::getDefaultActionSuccessRedirectUrl($action);
    }

    /**
     * The resource's own addons plus whatever other modules registered for
     * this record type through RecordExtensions (e.g. Attachments' Notes
     * tab, pinned with `first: true` ahead of the resource's own). Going
     * through getAllRelationManagers() keeps Filament's own
     * canViewForRecord() filtering in play for every half.
     */
    protected function getAllRelationManagers(): array
    {
        $resource = static::getResource();
        $ownFirst = is_subclass_of($resource, RecordsetResource::class) && $resource::ownAddonsFirst();
        $pinned = RecordExtensions::firstAddonsFor($this->getRecord());
        $own = parent::getAllRelationManagers();

        return [
            ...($ownFirst ? $own : $pinned),
            ...($ownFirst ? $pinned : $own),
            ...RecordExtensions::addonsFor($this->getRecord()),
        ];
    }

    /**
     * Appends the favorite star, where the recordset keeps favorites,
     * module-registered header actions (e.g. Watchdog's Watch toggle) and,
     * always last, the Record Info/History kebab — after the page's own, so
     * every View page gets them without each getHeaderActions() override
     * having to remember a parent call.
     */
    public function cacheInteractsWithHeaderActions(): void
    {
        parent::cacheInteractsWithHeaderActions();

        $resource = static::getResource();
        $favorites = is_subclass_of($resource, RecordsetResource::class) && $resource::hasFavorites()
            ? [Favorites::toggleAction()]
            : [];
        $clipboard = ($clipboardAction = $this->clipboardAction()) !== null ? [$clipboardAction] : [];
        // Restore for a deleted record, only where the administrator opened it
        // (RecordInAdministration); the action itself shows only while it is deleted.
        $restore = RecordInAdministration::shows($this) ? [RecordInAdministration::restoreAction()] : [];

        foreach ([...$restore, ...$favorites, ...$clipboard, ...RecordExtensions::headerActionsFor($this->getRecord(), $this), $this->recordInfoAndHistoryKebab()] as $action) {
            if ($action instanceof ActionGroup) {
                $action->livewire($this);
                $this->mergeCachedActions($action->getFlatActions());
            } else {
                $this->cacheAction($action);
            }

            $this->cachedHeaderActions[] = $action;
        }

        // Edit sits at the far right, just before the kebab, on every View
        // page, whatever order the page and the extensions added actions in.
        $edit = collect($this->cachedHeaderActions)->first(fn (mixed $action): bool => $action instanceof Action && $action->getName() === 'edit');

        if ($edit) {
            $others = array_values(array_filter($this->cachedHeaderActions, fn (mixed $action): bool => $action !== $edit));
            $kebab = array_pop($others);

            $this->cachedHeaderActions = [...$others, $edit, $kebab];
        }
    }

    /**
     * "Copy to clipboard", next to the favorite star — only when the
     * `clipboard` feature is on for this record type and there is a pattern
     * to render (a stored override or the module's default;
     * AI-shared/recordbrowser/clipboard.md). The text itself is built
     * only once the modal opens (the view's $text), not on every render of
     * this page.
     */
    protected function clipboardAction(): ?Action
    {
        $record = $this->getRecord();
        $alias = $record->getMorphClass();

        if (! RecordsetFeatures::enabled('clipboard', $alias) || ClipboardPattern::patternFor($alias) === null) {
            return null;
        }

        return Action::make('clipboard')
            ->label(__('Copy to clipboard'))
            ->icon(Heroicon::OutlinedClipboardDocument)
            ->color('gray')
            ->modalHeading(__('Copy to clipboard'))
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->modalWidth(Width::TwoExtraLarge)
            ->modalContent(fn (): ViewContract => view('filament.components.clipboard-pattern-modal', [
                'text' => ClipboardPattern::render($record, $alias) ?? '',
            ]));
    }

    public function getRelationManagersContentComponent(): Component
    {
        $ownerRecord = $this->getRecord();
        $managerLivewireData = ['ownerRecord' => $ownerRecord, 'pageClass' => static::class];

        /** @var array<class-string<RelationManager>> $managers */
        $managers = $this->getCachedRelationManagers();
        $otherManagers = static::withoutHistory($managers);

        $buildManagerTab = function (string|RelationManagerConfiguration $manager) use ($ownerRecord, $managerLivewireData): Tab {
            if ($manager instanceof RelationManagerConfiguration) {
                $properties = $manager->getProperties();
                $resource = $properties['sourceResource'];
                $fields = IncomingLinks::for($ownerRecord)[$resource];
                $label = IncomingLinks::tabLabel($resource, $ownerRecord);

                return static::withTruncatedLabel(
                    Tab::make($label)
                        ->icon($resource::getNavigationIcon())
                        ->key(Str::kebab(Str::plural(Str::beforeLast(class_basename($resource), 'Resource'))).'::tab', isInheritable: false)
                        ->badge(function () use ($resource, $ownerRecord, $fields): ?string {
                            $count = IncomingLinks::query($resource, $ownerRecord, $fields)->count();

                            return $count > 0 ? (string) $count : null;
                        })
                        ->schema(fn (): array => [Livewire::make($manager->relationManager, [...$managerLivewireData, ...$properties])->key($resource)]),
                    $label,
                );
            }

            $tab = static::withCountBadge($manager::getTabComponent($ownerRecord, static::class), $manager, $ownerRecord)
                ->key(static::addonTab($manager), isInheritable: false)
                ->schema(fn (): array => [
                    Livewire::make($manager, [...$managerLivewireData, ...$manager::getDefaultProperties()])
                        ->key($manager),
                ]);

            return static::withTruncatedLabel($tab, $tab->getLabel());
        };

        $tabs = collect($otherManagers)
            ->map($buildManagerTab)
            ->concat($this->getAdditionalContentTabs($ownerRecord))
            ->all();

        return Tabs::make()
            ->key('relationManagerTabs')
            ->activeTab(1)
            ->persistTabInQueryString()
            ->contained(false)
            ->visible($tabs !== [])
            // Spacing around the strip: resources/css/filament/epesi/compact-tables.css.
            ->extraAttributes(['class' => 'epesi-addon-tabs'])
            ->tabs($tabs);
    }

    /**
     * $managers, minus whichever one is History (identified by relationship
     * name `activities`, the one every resource's shared HistoryRelationManager
     * copy uses) — the addon tabs and the header kebab both start from this.
     *
     * @param  array<class-string<RelationManager>|RelationManagerConfiguration>  $managers
     * @return array<class-string<RelationManager>|RelationManagerConfiguration>
     */
    protected static function withoutHistory(array $managers): array
    {
        return array_diff_key($managers, array_filter(
            $managers,
            fn ($manager): bool => is_string($manager) && $manager::getRelationshipName() === 'activities',
        ));
    }

    /**
     * The header row's last action: Record Info always, History only for a
     * resource that has it (every CRM recordset does; a resource without one
     * — none at present — would just get a one-item kebab). Both open as a
     * modal instead of a tab; see the class docblock for why they moved out
     * of the addon tab strip.
     */
    protected function recordInfoAndHistoryKebab(): ActionGroup
    {
        $record = $this->getRecord();
        $managerLivewireData = ['ownerRecord' => $record, 'pageClass' => static::class];

        /** @var array<class-string<RelationManager>> $managers */
        $managers = $this->getCachedRelationManagers();
        $historyManager = collect(array_diff_key($managers, static::withoutHistory($managers)))->first();

        return ActionGroup::make([
            Action::make('record-info')
                ->label(__('Record Info'))
                ->icon(Heroicon::OutlinedInformationCircle)
                ->modalHeading(__('Record Info'))
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->schema(fn (Schema $schema): Schema => $schema->record($record)->inlineLabel()->components(RecordInfoEntries::components(static::getResource()::getSlug()))),
            Action::make('history')
                ->label(__('History'))
                ->icon(Heroicon::OutlinedClock)
                ->modalHeading(fn (): HtmlString => new HtmlString(view('filament.components.history-modal-heading')->render()))
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->modalWidth(Width::FourExtraLarge)
                ->extraModalWindowAttributes(['class' => 'epesi-history-modal'])
                ->visible($historyManager !== null)
                ->schema(fn (): array => $historyManager ? [
                    Livewire::make($historyManager, [...$managerLivewireData, ...$historyManager::getDefaultProperties()])->key($historyManager),
                ] : []),
            DeleteAction::make()
                ->icon(Heroicon::OutlinedTrash)
                // Shield's Gate::before lets super_admin past the policy, so a frozen record's own rule is asked here.
                ->hidden(fn (Model $record): bool => method_exists($record, 'wasPosted') && $record->wasPosted()),
        ])->color('gray');
    }

    /**
     * How many records the addon lists, on its tab — a zero stays unbadged,
     * so a glance at the strip says which addons actually hold something,
     * without every unused one still carrying a gray "0".
     *
     * The count is the addon's own relationship, so it follows the same row
     * visibility as its table; an addon whose table narrows the relationship
     * further (Reminders) returns the right number from `getBadge()`, as does
     * one that wants no count at all by deferring it. Any badge an addon sets
     * for itself wins.
     *
     * @param  class-string<RelationManager>  $manager
     */
    protected static function withCountBadge(Tab $tab, string $manager, Model $ownerRecord): Tab
    {
        if ($manager::isBadgeDeferred($ownerRecord, static::class)) {
            return $tab;
        }

        return $tab
            ->badge(function () use ($manager, $ownerRecord): ?string {
                $badge = $manager::getBadge($ownerRecord, static::class)
                    ?? (string) $ownerRecord->{$manager::getRelationshipName()}()->count();

                return $badge === '0' ? null : $badge;
            })
            ->badgeColor($manager::getBadgeColor($ownerRecord, static::class));
    }

    /**
     * Addon badges size to their own title (compact-tables.css), up to a max
     * width — past TAB_LABEL_MAX_LENGTH characters ("Sales Opportunities",
     * "Timesheet Rates") a label would blow that width out, so it's cut to
     * length plus a trailing "." instead, with the full name kept as the
     * button's native title tooltip.
     */
    protected static function withTruncatedLabel(Tab $tab, string|Htmlable|null $label): Tab
    {
        $text = $label instanceof Htmlable ? strip_tags($label->toHtml()) : (string) $label;

        if (mb_strlen($text) <= static::TAB_LABEL_MAX_LENGTH) {
            return $tab;
        }

        return $tab
            ->label(mb_substr($text, 0, static::TAB_LABEL_MAX_LENGTH).'.')
            ->extraAttributes(['title' => $text], merge: true);
    }

    /**
     * An addon's Create, Delete, Associate... ran (see RecordBrowserServiceProvider):
     * rendering the page again recounts the tabs' badges.
     */
    #[On('addon-changed')]
    public function refreshAddonBadges(): void {}

    /**
     * The `?tab=` value that opens $manager's addon — `notes::tab` for
     * NotesRelationManager, `phone-calls::tab` for PhoneCallsRelationManager.
     * Filament would key the tab by its slugged label, but the label is
     * translated, so a link built that way would miss the tab for anyone
     * reading another language. Doesn't apply to History or Record Info —
     * they aren't tabs; deep-link to them with `?action=history` /
     * `?action=record-info` instead (Filament's own
     * InteractsWithActions::$defaultAction), as Watchdog::url() does.
     *
     * @param  class-string<RelationManager>  $manager
     */
    public static function addonTab(string $manager): string
    {
        return Str::kebab(Str::beforeLast(class_basename($manager), 'RelationManager')).'::tab';
    }

    /**
     * Extension point for a resource-specific tab that isn't backed by a
     * RelationManager/addon (none at present: Contact's Login tab moved to Administration → Users) — see the class
     * docblock for where this lands in the overall tab order (after the real
     * addons). Empty by default; override in a resource's View page to add one.
     *
     * @return array<Tab>
     */
    protected function getAdditionalContentTabs(Model $ownerRecord): array
    {
        return [];
    }
}
