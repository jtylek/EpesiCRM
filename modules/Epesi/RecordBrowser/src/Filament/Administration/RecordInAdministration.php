<?php

namespace Epesi\Modules\RecordBrowser\Filament\Administration;

use App\Support\Demo;
use Closure;
use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\RecordsetRecords;
use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\Recordsets;
use Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportPageComponents\PageComponentConfig;
use Livewire\Features\SupportPageComponents\SupportPageComponents;
use Symfony\Component\HttpFoundation\Response;

/**
 * A record opened from Administration → Recordsets → <recordset> → Records:
 * the recordset's own View page (its resource's ViewRecord page, with its
 * addons, header actions and Record Info/History kebab) inside the
 * administration panel, at /administration/recordsets/{alias}/records/{record},
 * deleted records too, with Restore in the header for those.
 *
 * Not a second View page: the route renders the resource's own View page
 * class. That page belongs to the main panel, so while it runs (this request
 * and every Livewire request it makes afterwards, through the persistent
 * middleware) the current panel is the main one and its links lead there
 * (Edit, Clone, linked records, addon rows). Only the frame around it
 * (sidebar, topbar) is drawn as the administration panel's, and the
 * breadcrumbs lead back to the Records tab.
 *
 * Access: the administration panel's own (its Authenticate runs first, as on
 * every route of the panel) plus the Recordsets screen's canAccess(), checked
 * here again for every Livewire request, since this class is also the route's
 * persistent middleware. The main panel's View page is unchanged.
 */
class RecordInAdministration
{
    public const ROUTE = 'recordsets.record';

    protected const PANEL = 'administration';

    protected const BINDING = 'epesi.recordbrowser.record-in-administration';

    /** Registered by RecordBrowserPlugin on the administration panel only. */
    public static function routes(): void
    {
        Route::get('recordsets/{alias}/records/{record}', [static::class, 'show'])
            ->middleware(static::class)
            ->name(static::ROUTE);
    }

    /**
     * The administration page of $record; there is one when its recordset's
     * resource (resourceFor()) has a View page.
     */
    public static function url(string $alias, Model|int|string $record): string
    {
        return route(Filament::getPanel(self::PANEL)->generateRouteName(static::ROUTE), [
            'alias' => $alias,
            'record' => $record instanceof Model ? $record->getRouteKey() : $record,
        ]);
    }

    /**
     * The main panel's RecordsetResource of a recordset listed under
     * Administration → Recordsets, if any.
     *
     * @return class-string<RecordsetResource>|null
     */
    public static function resourceFor(string $alias): ?string
    {
        $model = Relation::getMorphedModel($alias);

        if ($model === null || ! array_key_exists($alias, Recordsets::recordsets())) {
            return null;
        }

        foreach (Filament::getPanel('main')->getResources() as $resource) {
            if (is_subclass_of($resource, RecordsetResource::class) && $resource::getModel() === $model) {
                return $resource;
            }
        }

        return null;
    }

    /**
     * Middleware of the route, persistent for Livewire (RecordBrowserServiceProvider):
     * only the administrator gets past it, and what runs after it runs as the
     * main panel.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        abort_unless(Recordsets::canAccess(), 403);

        $alias = (string) $request->route('alias');
        $resource = static::resourceFor($alias);

        abort_unless($resource !== null && $resource::hasPage('view'), 404);

        // Kept with the request it was set for: a Livewire request replays this
        // middleware on a copy of the original request, then runs the components.
        app()->instance(self::BINDING, ['request' => app('request'), 'alias' => $alias, 'resource' => $resource]);

        Filament::setCurrentPanel(Filament::getPanel('main'));

        return $next($request);
    }

    /**
     * The route's action: the View page as a full page, which is what Livewire
     * does for a page component's route, except that the layout is drawn as
     * the administration panel's.
     */
    public function show(string $alias, string $record): Response
    {
        $page = static::resourceFor($alias)::getPages()['view']->getPage();
        $html = null;

        $layout = SupportPageComponents::interceptTheRenderOfTheComponentAndRetreiveTheLayoutConfiguration(function () use (&$html, $page, $record): void {
            $html = app('livewire')->mount($page, ['record' => $record]);
        });

        $layout = $layout ?: new PageComponentConfig;
        $layout->normalizeViewNameAndParamsForBladeComponents();

        Filament::setCurrentPanel(Filament::getPanel(self::PANEL));
        static::registerViewPageHooks($page);

        $response = response(SupportPageComponents::renderContentsIntoLayout($html, $layout));

        if (is_callable($layout->response)) {
            call_user_func($layout->response, $response);
        }

        return $response;
    }

    /** Whether $page is the View page this request shows in the administration panel. */
    public static function shows(ViewRecord $page): bool
    {
        $binding = static::binding();

        return $binding !== null && $binding['resource'] === $page::getResource();
    }

    /** The recordset whose record this request shows, if it does. */
    public static function alias(): ?string
    {
        return static::binding()['alias'] ?? null;
    }

    /** @return array{request: Request, alias: string, resource: class-string<RecordsetResource>}|null */
    protected static function binding(): ?array
    {
        if (! app()->bound(self::BINDING)) {
            return null;
        }

        $binding = app(self::BINDING);

        return $binding['request'] === app('request') ? $binding : null;
    }

    /**
     * Recordsets > <recordset> > Records > <record>.
     *
     * @return array<int|string, string|Htmlable>
     */
    public static function breadcrumbs(string $alias, string|Htmlable $title): array
    {
        return [
            ...static::inAdministrationPanel(fn (): array => Recordsets::breadcrumbsFor($alias)),
            static::recordsUrl($alias) => __(RecordsetRecords::tabLabel()),
            $title,
        ];
    }

    /** The recordset's Records tab. */
    public static function recordsUrl(string $alias): string
    {
        return RecordsetRecords::getUrl(['alias' => $alias], panel: self::PANEL);
    }

    /** Restore on the View page of a deleted record, as on the Records tab. */
    public static function restoreAction(): RestoreAction
    {
        return Demo::guard(RestoreAction::make());
    }

    /**
     * The page-wide parts a View page relies on that the main panel adds to
     * its own layout and the administration panel doesn't: the addon refresh
     * after a change, and the file preview and copied-link pop-ups of
     * attachment chips (Notes, Mail). Scoped to the page class; once per request.
     *
     * @param  class-string  $page
     */
    protected static function registerViewPageHooks(string $page): void
    {
        $key = self::BINDING.'.hooks.'.$page;

        if (app()->bound($key)) {
            return;
        }

        app()->instance($key, true);

        foreach (['addon-refresh', 'file-preview-modal', 'link-copied-modal'] as $view) {
            FilamentView::registerRenderHook(PanelsRenderHook::BODY_END, fn () => view('filament.components.'.$view), scopes: $page);
        }
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    protected static function inAdministrationPanel(Closure $callback): mixed
    {
        $current = Filament::getCurrentPanel();

        Filament::setCurrentPanel(Filament::getPanel(self::PANEL));

        try {
            return $callback();
        } finally {
            Filament::setCurrentPanel($current);
        }
    }
}
