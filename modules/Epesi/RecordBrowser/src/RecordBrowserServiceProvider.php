<?php

namespace Epesi\Modules\RecordBrowser;

use Epesi\Modules\RecordBrowser\Console\CustomFieldsSyncCommand;
use Epesi\Modules\RecordBrowser\Console\MakeRecordsetCommand;
use Epesi\Modules\RecordBrowser\Console\RecordsetCheckCommand;
use Epesi\Modules\RecordBrowser\Filament\Pages\CreateRecord;
use Epesi\Modules\RecordBrowser\Filament\Pages\EditRecord;
use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Epesi\Modules\RecordBrowser\History\SaveActivity;
use Epesi\Modules\RecordBrowser\Models\Address;
use Epesi\Modules\RecordBrowser\Models\CustomField;
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
        ]);

        $this->app->scoped(SaveActivity::class);
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

        // Whatever an addon's action did (add, delete, unlink...), the View
        // page recounts the badges on its tabs (ViewRecord::withCountBadge()).
        Event::listen(ActionCalled::class, function (Action $action): void {
            $livewire = $action->getLivewire();

            if ($livewire instanceof RelationManager) {
                $livewire->dispatch('addon-changed');
            }
        });

        // A list's browse-mode tabs go in the table's toolbar, left of the
        // search box, rather than floating above the table. The hook sits
        // inside the toolbar's left-aligned actions, so bulk actions line up
        // after the tabs once rows are selected.
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
                ? view('epesi-recordbrowser::list-keyboard-nav', ['tabKeys' => array_keys($page->getCachedTabs())])
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
                .'.epesi-browse-tabs.fi-tabs{margin:0;padding:0;background:none;box-shadow:none}'
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
                // Same background a row's own :hover already gives it
                // (row.css), for the row the keyboard currently has
                // highlighted (list-keyboard-nav.blade.php).
                .'.fi-ta-row.epesi-row-active:not(.fi-striped){background:var(--gray-50)}'
                .'.dark .fi-ta-row.epesi-row-active:not(.fi-striped){background:rgb(255 255 255/.05)}'
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
