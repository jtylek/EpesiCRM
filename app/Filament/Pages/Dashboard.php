<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Dashboard\Applet;
use App\Models\DashboardApplet;
use App\Models\DashboardTab;
use App\Support\UiState;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Group;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;

/**
 * Filament's own Dashboard (subclassed so its header matches every other
 * page's, see HasPageIconBreadcrumb), working as Epesi's Base_Dashboard did:
 * each user has their own tabs of applets in three columns, drags them into
 * place by the title bar, adds them with "Add applet" and sets each one up
 * from its gear. Only widgets that implement Applet take part.
 */
class Dashboard extends BaseDashboard
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;

    public const COLUMNS = 3;

    protected string $view = 'filament.pages.dashboard';

    /** The tab on show, kept for the next visit (and the next login) as legacy kept $_SESSION['client']['dashboard_tab']. */
    public ?int $tab = null;

    public function mount(): void
    {
        $this->tab = UiState::recall('dashboard.tab');
    }

    public function updatedTab(): void
    {
        UiState::remember('dashboard.tab', $this->tab);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->addAppletAction(),
            $this->manageTabsAction(),
        ];
    }

    /** Filament's header, with the tab switcher on the same line. */
    public function getHeader(): ?View
    {
        return view('filament.pages.dashboard-header');
    }

    /**
     * The user's tabs in order. The first visit creates the default ones.
     *
     * @return Collection<int, DashboardTab>
     */
    #[Computed]
    public function dashboardTabs(): Collection
    {
        $tabs = DashboardTab::query()
            ->where('user_id', Auth::id())
            ->orderBy('pos')
            ->orderBy('id')
            ->get();

        return $tabs->isEmpty() ? $this->createDefaultTabs() : $tabs;
    }

    public function currentTab(): DashboardTab
    {
        return $this->dashboardTabs->firstWhere('id', $this->tab) ?? $this->dashboardTabs->first();
    }

    /**
     * The panel's applets, keyed by class, in their widget sort order.
     *
     * @return array<class-string<Widget&Applet>, class-string<Widget&Applet>|WidgetConfiguration>
     */
    #[Computed]
    public function appletWidgets(): array
    {
        return collect($this->getWidgets())
            ->keyBy(fn (string|WidgetConfiguration $widget): string => $this->normalizeWidgetClass($widget))
            ->filter(fn (string|WidgetConfiguration $widget, string $class): bool => is_subclass_of($class, Applet::class))
            ->sortBy(fn (string|WidgetConfiguration $widget, string $class): int => $class::getSort())
            ->all();
    }

    /**
     * The applets on the tab on show, column by column. Ones the user may not
     * see stay where they are, hidden, as legacy skipped an applet whose
     * applet_caption() came back empty.
     *
     * @return array<int, list<DashboardApplet>>
     */
    public function getAppletColumns(): array
    {
        $columns = array_fill(0, self::COLUMNS, []);

        $applets = $this->currentTab()->applets()
            ->orderBy('pos')
            ->orderBy('id')
            ->get();

        foreach ($applets as $applet) {
            if (isset($this->appletWidgets[$applet->widget]) && $applet->widget::canView()) {
                $columns[min($applet->col, self::COLUMNS - 1)][] = $applet;
            }
        }

        return $columns;
    }

    /**
     * What Filament's own widget grid would mount the widget with, plus
     * which applet it is and its settings.
     *
     * @return array<string, mixed>
     */
    public function getAppletProperties(DashboardApplet $applet): array
    {
        $widget = $this->appletWidgets[$applet->widget];

        return [
            ...$this->getWidgetData(),
            ...($widget instanceof WidgetConfiguration
                ? [...$widget->widget::getDefaultProperties(), ...$widget->getProperties()]
                : $widget::getDefaultProperties()),
            'appletId' => $applet->id,
            'appletSettings' => $applet->settings ?? [],
        ];
    }

    /**
     * wire:sort's handler, called once per drop: $position is the applet's
     * index in $column after the drop. The whole tab is saved, not just the
     * one applet, so applets still in their first place stay where the user
     * saw them. Renderless: SortableJS has already moved the element.
     */
    #[Renderless]
    public function moveApplet(int|string $applet, int $position, string $column): void
    {
        $columns = $this->getAppletColumns();
        $moved = collect(array_merge(...$columns))->firstWhere('id', (int) $applet);

        if (! $moved) {
            return;
        }

        $columns = array_map(
            fn (array $applets): array => array_values(array_filter($applets, fn (DashboardApplet $each): bool => ! $each->is($moved))),
            $columns,
        );
        array_splice($columns[max(0, min((int) $column, self::COLUMNS - 1))], max(0, $position), 0, [$moved]);

        foreach ($columns as $col => $applets) {
            foreach ($applets as $pos => $each) {
                $each->fill(['col' => $col, 'pos' => $pos])->save();
            }
        }
    }

    /** The gear on an applet's title bar (IsApplet). */
    #[On('configure-applet')]
    public function openAppletSettings(int $applet): void
    {
        $this->mountAction('configureApplet', ['applet' => $applet]);
    }

    /**
     * Legacy's applet picker: every applet the user may see, by caption,
     * each with its applet_info() line. It lands on the tab on show, and one
     * with settings opens them straight away, as legacy did.
     */
    public function addAppletAction(): Action
    {
        return Action::make('addApplet')
            ->label('Add applet')
            ->icon(Heroicon::OutlinedPlus)
            ->color('gray')
            // The Notes tab holds the Notes applet and nothing else: notes are added there.
            ->visible(fn (): bool => $this->currentTab()->key !== DashboardTab::NOTES)
            ->modalHeading(__('Add applet'))
            ->modalSubmitActionLabel(__('Add'))
            ->schema([
                Radio::make('widget')
                    ->hiddenLabel()
                    ->options(fn (): array => $this->availableApplets()
                        ->map(fn (string $class): string => $class::getAppletCaption())
                        ->all())
                    ->descriptions(fn (): array => $this->availableApplets()
                        ->map(fn (string $class): ?string => $class::getAppletDescription())
                        ->filter()
                        ->all())
                    ->required(),
            ])
            ->action(function (array $data): void {
                $applet = $this->currentTab()->addApplet($data['widget'], self::COLUMNS);

                if ($data['widget']::getAppletSettingsSchema() !== []) {
                    $this->replaceMountedAction('configureApplet', ['applet' => $applet->id]);
                }
            });
    }

    /**
     * Legacy's config mode for tabs: add, rename, reorder and delete them in
     * one form. Deleting a tab deletes its applets.
     */
    public function manageTabsAction(): Action
    {
        return Action::make('manageTabs')
            ->label('Tabs')
            ->icon(Heroicon::OutlinedRectangleStack)
            ->color('gray')
            ->modalHeading(__('Dashboard tabs'))
            ->modalSubmitActionLabel(__('Save'))
            ->fillForm(fn (): array => [
                'tabs' => $this->dashboardTabs
                    ->map(fn (DashboardTab $tab): array => ['id' => $tab->id, 'name' => $tab->name, 'system' => $tab->isSystem()])
                    ->all(),
            ])
            ->schema([
                Repeater::make('tabs')
                    ->hiddenLabel()
                    // One line per tab: see the .epesi-tabs-repeater rules in the dashboard view.
                    ->extraAttributes(['class' => 'epesi-tabs-repeater'])
                    ->schema([
                        Hidden::make('id'),
                        Hidden::make('system')->dehydrated(false),
                        TextInput::make('name')
                            ->hiddenLabel()
                            ->required()
                            ->maxLength(64),
                    ])
                    ->minItems(1)
                    ->addActionLabel(__('Add tab'))
                    ->deleteAction(fn (Action $action): Action => $action
                        // Main, Agenda and Notes stay.
                        ->hidden(fn (array $arguments, Repeater $component): bool => (bool) $this->dashboardTabs->firstWhere('id', (int) ($component->getRawState()[$arguments['item']]['id'] ?? 0))?->isSystem())
                        ->requiresConfirmation()
                        ->modalHeading(__('Delete this tab and all applets assigned to it?'))),
            ])
            ->action(fn (array $data) => $this->saveTabs($data['tabs']));
    }

    /**
     * Legacy configure_applet(): the applet's own settings, and which tab it
     * is on. Remove is here rather than a second button on the title bar.
     */
    public function configureAppletAction(): Action
    {
        return Action::make('configureApplet')
            ->modalHeading(fn (array $arguments): string => __(':applet applet settings', [
                'applet' => $this->findApplet($arguments)->widget::getAppletCaption(),
            ]))
            ->modalDescription(fn (array $arguments): ?string => $this->findApplet($arguments)->widget::getAppletSettingsSchema() === []
                && $this->tabsFor($this->findApplet($arguments)->widget)->count() < 2 ? __('This applet has no settings.') : null)
            ->modalSubmitActionLabel(__('Save'))
            ->fillForm(function (array $arguments): array {
                $applet = $this->findApplet($arguments);

                return [
                    'settings' => [...$applet->widget::getAppletSettingsDefaults(), ...($applet->settings ?? [])],
                    'tab' => $applet->dashboard_tab_id,
                ];
            })
            ->schema(fn (array $arguments): array => [
                Group::make($this->findApplet($arguments)->widget::getAppletSettingsSchema())
                    ->statePath('settings'),
                Select::make('tab')
                    ->label('Tab')
                    ->options(fn (): array => $this->tabsFor($this->findApplet($arguments)->widget)->pluck('name', 'id')->all())
                    ->required()
                    ->selectablePlaceholder(false)
                    ->visible(fn (): bool => $this->tabsFor($this->findApplet($arguments)->widget)->count() > 1),
            ])
            ->extraModalFooterActions(fn (array $arguments): array => [
                Action::make('removeApplet')
                    ->label('Remove')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('Delete this applet?'))
                    ->visible(fn (): bool => ! $this->isNotesOnly($this->findApplet($arguments)->widget))
                    ->action(fn () => $this->findApplet($arguments)->delete())
                    ->cancelParentActions(),
            ])
            ->action(function (array $data, array $arguments): void {
                $applet = $this->findApplet($arguments);
                $applet->settings = $data['settings'] ?? [];

                $tab = $this->tabsFor($applet->widget)->firstWhere('id', (int) ($data['tab'] ?? 0));

                if ($tab && ! $tab->is($applet->tab)) {
                    $applet->dashboard_tab_id = $tab->id;
                    $applet->pos = ($tab->applets()->where('col', $applet->col)->max('pos') ?? -1) + 1;
                }

                $applet->save();
            });
    }

    /**
     * @return Collection<class-string<Widget&Applet>, class-string<Widget&Applet>>
     */
    private function availableApplets(): Collection
    {
        return collect(array_keys($this->appletWidgets))
            ->filter(fn (string $class): bool => $class::canView() && ! $this->isNotesOnly($class))
            ->mapWithKeys(fn (string $class): array => [$class => $class])
            ->sortBy(fn (string $class): string => $class::getAppletCaption(), SORT_NATURAL | SORT_FLAG_CASE);
    }

    /**
     * The tabs an applet may be on: the Notes applet stays on the Notes tab,
     * and no other applet goes there.
     *
     * @return Collection<int, DashboardTab>
     */
    private function tabsFor(string $widget): Collection
    {
        return $this->dashboardTabs->filter(fn (DashboardTab $tab): bool => $this->isNotesOnly($widget)
            ? $tab->key === DashboardTab::NOTES
            : $tab->key !== DashboardTab::NOTES)->values();
    }

    /** An applet that lives on the Notes tab only (it sets APPLET_ONLY_ON_NOTES_TAB). */
    private function isNotesOnly(string $widget): bool
    {
        return defined($widget.'::APPLET_ONLY_ON_NOTES_TAB');
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function findApplet(array $arguments): DashboardApplet
    {
        $applet = DashboardApplet::query()
            ->where('user_id', Auth::id())
            ->find($arguments['applet'] ?? null);

        abort_unless($applet && isset($this->appletWidgets[$applet->widget]), 404);

        return $applet;
    }

    /**
     * Legacy set_default_applets(), with config('dashboard.default') in place
     * of an administrator's default dashboard. An applet not in the panel
     * (its module off) is left out, and so is a tab left with none. One the
     * user may not see yet (Mail, before they have an account) is placed all
     * the same and stays hidden until they may.
     *
     * @return Collection<int, DashboardTab>
     */
    private function createDefaultTabs(): Collection
    {
        $layout = collect(config('dashboard.default', []))
            ->map(fn (array $columns): array => array_map(
                fn (array $widgets): array => array_values(array_filter($widgets, fn (string $class): bool => isset($this->appletWidgets[$class]))),
                $columns,
            ))
            ->filter(fn (array $columns): bool => array_merge(...$columns) !== []);

        // The dashboard needs a tab, even with nothing to put on it.
        if ($layout->isEmpty()) {
            $layout = collect(['Dashboard' => []]);
        }

        $tabs = collect();

        foreach ($layout as $name => $columns) {
            $tab = DashboardTab::query()->create([
                'user_id' => Auth::id(),
                'name' => __($name),
                'key' => config('dashboard.keys.'.$name),
                'pos' => $tabs->count(),
            ]);

            foreach ($columns as $col => $widgets) {
                foreach ($widgets as $pos => $widget) {
                    $tab->applets()->create([
                        'user_id' => Auth::id(),
                        'widget' => $widget,
                        'col' => $col,
                        'pos' => $pos,
                    ]);
                }
            }

            $tabs->push($tab);
        }

        return $tabs;
    }

    /**
     * @param  array<array{id?: int|string|null, name: string}>  $rows
     */
    private function saveTabs(array $rows): void
    {
        $kept = [];

        foreach (array_values($rows) as $pos => $row) {
            $tab = $this->dashboardTabs->firstWhere('id', (int) ($row['id'] ?? 0))
                ?? new DashboardTab(['user_id' => Auth::id()]);

            $tab->fill(['name' => $row['name'], 'pos' => $pos])->save();
            $kept[] = $tab->id;
        }

        // A system tab is never deleted, whatever the form sent: one left out of it goes last.
        $missing = $this->dashboardTabs->filter(fn (DashboardTab $tab): bool => $tab->isSystem() && ! in_array($tab->id, $kept, true));

        foreach ($missing as $tab) {
            $tab->update(['pos' => count($kept)]);
            $kept[] = $tab->id;
        }

        $gone = DashboardTab::query()->where('user_id', Auth::id())->whereNotIn('id', $kept)->pluck('id');

        DashboardApplet::query()->whereIn('dashboard_tab_id', $gone)->delete();
        DashboardTab::query()->whereKey($gone)->delete();

        unset($this->dashboardTabs);
    }
}
