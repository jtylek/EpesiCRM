<?php

namespace Epesi\Modules\RecordBrowser;

use Epesi\Modules\RecordBrowser\Console\CustomFieldsSyncCommand;
use Epesi\Modules\RecordBrowser\Console\MakeRecordsetCommand;
use Epesi\Modules\RecordBrowser\Console\RecordsetCheckCommand;
use Epesi\Modules\RecordBrowser\Filament\Pages\CreateRecord;
use Epesi\Modules\RecordBrowser\Filament\Pages\EditRecord;
use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord;
use Epesi\Modules\RecordBrowser\History\SaveActivity;
use Epesi\Modules\RecordBrowser\Models\Address;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Epesi\Modules\RecordBrowser\Models\EmailAddress;
use Epesi\Modules\RecordBrowser\Models\OnlineAccount;
use Epesi\Modules\RecordBrowser\Models\PhoneNumber;
use Epesi\Modules\RecordBrowser\Recordset\FieldOverrides;
use Filament\Actions\Action;
use Filament\Actions\Events\ActionCalled;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Spatie\Activitylog\ActivitylogServiceProvider;

/**
 * The recordset engine.
 *
 * A core module (`"core": true` in module.json): core resources extend its base
 * pages and core models use its traits, so ModuleInstaller refuses to disable or
 * uninstall it, and config('modules.core_namespaces') registers its PSR-4 prefix
 * even when the `modules` table can't be read.
 */
class RecordBrowserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Relation::morphMap([
            'custom_field' => CustomField::class,
            // Collection types (CollectionItem): the alias is what an
            // administrator's Collection field stores as its type.
            'address' => Address::class,
            'phone_number' => PhoneNumber::class,
            'online_account' => OnlineAccount::class,
            'email_address' => EmailAddress::class,
        ]);

        $this->app->scoped(SaveActivity::class);
        $this->app->scoped(FieldOverrides::class);
    }

    public function boot(): void
    {
        // Keeps `php artisan migrate` aware of this module's migrations after
        // install; the installer runs them once itself with --path.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Which History entry a record is being saved under, so a collection
        // change joins it (SaveActivity).
        $activity = ActivitylogServiceProvider::determineActivityModel();
        $activity::created(fn (Model $row) => app(SaveActivity::class)->remember($row));
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'epesi-recordbrowser');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Whatever an addon's action or a View-page action changes, the View
        // page recounts the badges on its tabs (ViewRecord::withCountBadge()).
        Event::listen(ActionCalled::class, function (Action $action): void {
            $livewire = $action->getLivewire();

            if ($livewire instanceof RelationManager || $livewire instanceof ViewRecord) {
                $livewire->dispatch('addon-changed');
            }
        });

        // "My records": one click sets Employees to the user — see ListRecords.
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_REORDER_TRIGGER_BEFORE,
            fn (): ?View => ($page = Livewire::current()) instanceof ListRecords && ($button = $page->getMyRecordsButton())
                ? view('epesi-recordbrowser::my-records-button', ['button' => $button])
                : null,
        );

        // "Show inactive" / "Hide inactive": the switch beside it, on lists with a
        // closed/canceled status.
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_REORDER_TRIGGER_BEFORE,
            fn (): ?View => ($page = Livewire::current()) instanceof ListRecords && ($toggle = $page->getInactiveToggle())
                ? view('epesi-recordbrowser::inactive-toggle', ['toggle' => $toggle])
                : null,
        );

        // A list's browse-mode tabs go in the table's toolbar, after "My records"
        // and the inactive switch, left of the search box, rather than
        // floating above the table. The hook sits inside the toolbar's
        // left-aligned actions, so bulk actions line up after it once rows
        // are selected.
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_REORDER_TRIGGER_BEFORE,
            fn (): ?View => ($page = Livewire::current()) instanceof ListRecords && $page->getCachedTabs() !== []
                ? view('epesi-recordbrowser::browse-tabs', ['tabs' => $page->getCachedTabs(), 'activeTab' => $page->activeTab])
                : null,
        );

        // Keyboard browsing of a List page — see list-keyboard-nav.blade.php.
        // Unconditional on tabs existing (unlike browse-tabs above): even a
        // resource with only the "All" mode still gets search-focus and row
        // navigation.
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_START,
            fn (): ?View => ($page = Livewire::current()) instanceof ListRecords
                ? view('epesi-recordbrowser::list-keyboard-nav', ['tabKeys' => array_keys($page->getCachedTabs()), 'hasMyRecords' => $page->getMyRecordsButton() !== null, 'hasStatus' => $page->getInactiveToggle() !== null])
                : null,
        );

        // Escape to cancel on a Create or Edit page, and a Create page's
        // first field focused on load — see form-keyboard-shortcuts.blade.php.
        // Ctrl/Cmd+S already saves on both without anything here: it's
        // Filament's own default.
        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_END,
            fn (): ?View => match (true) {
                Livewire::current() instanceof CreateRecord => view('epesi-recordbrowser::form-keyboard-shortcuts', ['focusFirstField' => true]),
                Livewire::current() instanceof EditRecord => view('epesi-recordbrowser::form-keyboard-shortcuts', ['focusFirstField' => false]),
                default => null,
            },
        );

        // Filament draws a standalone tab strip as a floating card; inside the
        // toolbar it should sit flat like the search box beside it.
        // The History addon marks a change's old value red and its new value
        // green, as a diff does: a pale solid tint under light mode's dark
        // text, a translucent one over dark mode's panel under its light text.
        // A long text's diff (TextDiff) strikes its removed words through
        // (<del>) and leaves added ones (<ins>) unlined; "…" is the unchanged
        // text left out.
        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn (): HtmlString => new HtmlString('<style>'
                .'.epesi-browse-tabs{min-width:9rem}'
                .'.epesi-history-old,.epesi-history-new{padding-inline:.25rem;border-radius:.25rem;-webkit-box-decoration-break:clone;box-decoration-break:clone}'
                .'.epesi-history-old{background:var(--danger-100)}'
                .'.epesi-history-new{background:var(--success-100)}'
                .'.dark .epesi-history-old{background:color-mix(in oklab,var(--danger-500) 30%,transparent)}'
                .'.dark .epesi-history-new{background:color-mix(in oklab,var(--success-500) 30%,transparent)}'
                .'del.epesi-history-old{text-decoration:line-through}'
                .'ins.epesi-history-new{text-decoration:none}'
                .'.epesi-history-gap{color:var(--gray-500)}'
                .'.dark .epesi-history-gap{color:var(--gray-400)}'
                // A collection's items on the View page, one per line: the
                // kind's badge, the summary, then an administrator's fields
                // in grey (collection-items.blade.php).
                .'.epesi-collection-items{display:flex;flex-direction:column;gap:.375rem}'
                .'.epesi-collection-item{display:flex;flex-wrap:wrap;align-items:center;gap:.25rem .5rem}'
                .'.epesi-collection-extra{color:var(--gray-500)}'
                .'.dark .epesi-collection-extra{color:var(--gray-400)}'
                // A collection's cards on the form (Field::collectionRepeater()):
                // Filament puts Add below them and Collapse all / Expand all
                // above; both go in one row above the cards, Add first. The
                // cards sit closer and their headers are lower than
                // Filament's, so a collapsed list reads as a list.
                .'.fi-fo-repeater.epesi-collection-repeater{grid-template-columns:auto 1fr;align-items:center;column-gap:.75rem;row-gap:.5rem}'
                .'.epesi-collection-repeater>.fi-fo-repeater-add{grid-row:1;grid-column:1;width:auto}'
                .'.epesi-collection-repeater>.fi-fo-repeater-actions{grid-row:1;grid-column:2}'
                .'.epesi-collection-repeater>.fi-fo-repeater-items{grid-row:2;grid-column:1/-1;gap:.375rem}'
                .'.epesi-collection-repeater .fi-fo-repeater-item-header{padding-block:.375rem;padding-inline:.75rem}'
                .'.epesi-collection-repeater .fi-fo-repeater-item-content{padding:.75rem}'
                // The row the keyboard currently has highlighted
                // (list-keyboard-nav.blade.php), tinted with the panel's own
                // accent color rather than the plain hover gray, so it reads
                // as a distinct "selection" rather than just another hover.
                .'tr.fi-ta-row.epesi-row-active{background:color-mix(in oklab,var(--primary-500) 12%,transparent) !important}'
                .'.dark tr.fi-ta-row.epesi-row-active{background:color-mix(in oklab,var(--primary-500) 20%,transparent) !important}'
                .'</style>'),
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeRecordsetCommand::class,
                RecordsetCheckCommand::class,
                CustomFieldsSyncCommand::class,
            ]);
        }
    }
}
