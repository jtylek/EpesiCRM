<?php

namespace Epesi\Modules\RecordBrowser;

use Epesi\Modules\RecordBrowser\Console\CustomFieldsSyncCommand;
use Epesi\Modules\RecordBrowser\Console\MakeRecordsetCommand;
use Epesi\Modules\RecordBrowser\Console\RecordsetCheckCommand;
use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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
        Relation::morphMap(['custom_field' => CustomField::class]);
    }

    public function boot(): void
    {
        // Keeps `php artisan migrate` aware of this module's migrations after
        // install; the installer runs them once itself with --path.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'epesi-recordbrowser');

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

        // Filament draws a standalone tab strip as a floating card; inside the
        // toolbar it should sit flat like the search box beside it.
        // The History addon marks a change's old value red and its new value
        // green, as a diff does: a pale solid tint under light mode's dark
        // text, a translucent one over dark mode's panel under its light text.
        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn (): HtmlString => new HtmlString('<style>'
                .'.epesi-browse-tabs.fi-tabs{margin:0;padding:0;background:none;box-shadow:none}'
                .'.epesi-history-old,.epesi-history-new{padding-inline:.25rem;border-radius:.25rem;-webkit-box-decoration-break:clone;box-decoration-break:clone}'
                .'.epesi-history-old{background:var(--danger-100)}'
                .'.epesi-history-new{background:var(--success-100)}'
                .'.dark .epesi-history-old{background:color-mix(in oklab,var(--danger-500) 30%,transparent)}'
                .'.dark .epesi-history-new{background:color-mix(in oklab,var(--success-500) 30%,transparent)}'
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
