<?php

namespace Epesi\Modules\RecordBrowser\Filament\Administration\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Filament\Concerns\UsesEpesiFormLayout;
use BackedEnum;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetFeatures;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Locked;

/**
 * One tab of a recordset's administration screen (Fields, Records, Addons,
 * Settings, Clipboard). Each tab is a page of its own at
 * /administration/recordsets/{alias}[/<tab>], sharing the top sub-navigation;
 * a tab with settings has its own Save, which
 * writes only that tab's data. The first tab (Fields) is the recordset's
 * default URL. Only the Recordsets list registers in navigation.
 */
abstract class RecordsetPage extends Page
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use TranslatesPageLabels;
    use UsesEpesiFormLayout;

    /** Features owned by the engine itself; every other feature belongs to an addon module. */
    public const SETTINGS_FEATURES = ['clipboard', 'export'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected string $view = 'epesi-recordbrowser::recordset-tab';

    #[Locked]
    public string $alias;

    /** @var array<string, mixed> */
    public array $data = [];

    abstract public static function tabLabel(): string;

    /**
     * @return array<int, class-string<RecordsetPage>> in tab order, the default tab first
     */
    public static function tabs(): array
    {
        return [
            RecordsetFields::class,
            RecordsetRecords::class,
            RecordsetAddons::class,
            RecordsetSettings::class,
            RecordsetClipboard::class,
        ];
    }

    public static function canAccess(): bool
    {
        return Recordsets::canAccess();
    }

    public function mount(string $alias): void
    {
        abort_unless(array_key_exists($alias, Recordsets::recordsets()), 404);

        $this->alias = $alias;

        $this->form->fill($this->formData());
    }

    /** @return array<string, mixed> */
    protected function formData(): array
    {
        return [];
    }

    /** @return array<int, mixed> */
    protected function formComponents(): array
    {
        return [];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components($this->formComponents());
    }

    /** Whether the tab keeps a Save button. */
    public function hasSave(): bool
    {
        return false;
    }

    public function save(): void
    {
        abort(404);
    }

    public function recordsetLabel(): string
    {
        return Recordsets::recordsets()[$this->alias] ?? $this->alias;
    }

    public function getTitle(): string
    {
        return isset($this->alias)
            ? $this->recordsetLabel().' - '.__(static::tabLabel())
            : __('Recordset');
    }

    public function getBreadcrumbs(): array
    {
        $breadcrumbs = Recordsets::breadcrumbsFor($this->alias);

        // The default tab shares the recordset's URL, so it needs no crumb of its own.
        if (static::tabs()[0] !== static::class) {
            $breadcrumbs[static::getUrl(['alias' => $this->alias])] = __(static::tabLabel());
        }

        return $breadcrumbs;
    }

    public function getSubNavigation(): array
    {
        return collect(static::tabs())
            ->map(fn (string $page, int $index): NavigationItem => NavigationItem::make(__($page::tabLabel()))
                ->url($page::getUrl(['alias' => $this->alias]))
                ->isActiveWhen(fn (): bool => $page === static::class)
                ->sort($index))
            ->all();
    }

    /**
     * The registered features a tab owns, by label. Settings holds the engine's
     * own, Addons every other module's.
     *
     * @return array<string, string> feature => label
     */
    protected function featureOptions(bool $settings): array
    {
        return collect(RecordsetFeatures::definitions())
            ->filter(fn (string $label, string $feature): bool => in_array($feature, self::SETTINGS_FEATURES, true) === $settings)
            ->map(fn (string $label): string => __($label))
            ->all();
    }

    /** Switches only the given features, so another tab's choices stay as they were. */
    protected function saveFeatures(array $owned, array $selected): void
    {
        foreach ($owned as $feature) {
            RecordsetFeatures::set($feature, $this->alias, in_array($feature, $selected, true));
        }
    }

    protected function saved(): void
    {
        Notification::make()->success()->title(__('Recordset settings saved'))->send();
    }

    protected function authorizeSave(): void
    {
        abort_unless(static::canAccess() && $this->hasSave(), 403);
    }
}
