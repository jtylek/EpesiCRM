<?php

namespace App\Providers\Filament;

use App\Filament\Setup\Pages\FinishSetup;
use App\Filament\Setup\Pages\InstallWizard;
use App\Http\Middleware\ClearStaleSetupAuth;
use App\Http\Middleware\DisabledInDemo;
use App\Http\Middleware\SetLocale;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The setup wizard (Epesi's FirstRun), at /setup. A panel of its own so it
 * gets Filament's look without the main panel's login, sidebar or module
 * plugins, none of which exist yet on a fresh install. Its pages close
 * themselves once setup is done. In demo mode it answers 404 (DisabledInDemo):
 * the demo is set up by `php artisan demo:reset`.
 */
class SetupPanelProvider extends PanelProvider
{
    /**
     * The wizard's step bar (Setup code, Server check, Database, Options,
     * Administrator, Mail, Install) has more steps than Filament's own
     * padding was sized for; without this, the last steps sit past the edge
     * of the card behind a horizontal scrollbar instead of all fitting.
     */
    protected function compactWizardHeaderStyles(): HtmlString
    {
        return new HtmlString(
            '<style>'
            .'.fi-simple-main .fi-sc-wizard-header-step-btn{column-gap:.5rem!important;padding-inline:.75rem!important;padding-block:.75rem!important}'
            .'.fi-simple-main .fi-sc-wizard-header-step-icon-ctn{width:1.75rem!important;height:1.75rem!important}'
            .'.fi-simple-main .fi-sc-wizard-header-step-separator{width:.75rem!important}'
            .'</style>',
        );
    }

    /**
     * The wizard is drawn by Livewire, whose script is served by a route, not
     * from disk. A server that doesn't send unknown addresses to index.php
     * (nginx without try_files, or the page opened as .../public/ on a server
     * that ignores .htaccess) answers it with 404, and the card stays empty
     * with no error. Plain JavaScript, so it runs when Livewire doesn't: says
     * what to fix, with the script's own HTTP status.
     */
    protected function scriptWatchdog(): HtmlString
    {
        $text = json_encode([
            'title' => __('The setup page could not load its script'),
            'body' => __('The web server answered :status for :url. It must send every address that is not a file to index.php.'),
            'nginx' => __('On nginx: make epesi\'s public/ folder the document root (aaPanel: Running directory /public) and add this rule (aaPanel: URL rewrite, laravel5). Then open the site\'s address without /public.'),
            'started' => __(':status (but the script did not start)'),
            'none' => __('no answer'),
            'apache' => __('On Apache: open the site\'s address without /public, and check that mod_rewrite is on and AllowOverride All is set.'),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

        return new HtmlString(<<<HTML
            <script>
            (function () {
                var t = {$text};
                window.addEventListener('load', function () {
                    setTimeout(function () {
                        if (window.Livewire) return;
                        var script = document.querySelector('script[src*="livewire"]');
                        var url = script ? script.getAttribute('src') : '';
                        var show = function (status) {
                            var box = document.createElement('div');
                            box.setAttribute('role', 'alert');
                            box.style.cssText = 'max-width:46rem;margin:1.5rem auto;padding:1rem 1.25rem;border:1px solid #f59e0b;border-radius:.5rem;background:#fffbeb;color:#78350f;font:15px/1.5 system-ui,sans-serif';
                            var add = function (tag, text) { var el = document.createElement(tag); el.textContent = text; box.appendChild(el); return el; };
                            add('strong', t.title);
                            add('p', t.body.replace(':status', status).replace(':url', url.split('?')[0]));
                            add('p', t.nginx);
                            add('pre', 'location / {\\n    try_files \$uri \$uri/ /index.php?\$query_string;\\n}').style.cssText = 'background:#fef3c7;padding:.5rem .75rem;overflow-x:auto';
                            add('p', t.apache);
                            document.body.insertBefore(box, document.body.firstChild);
                        };
                        if (!url) return show('?');
                        fetch(url, { method: 'HEAD', cache: 'no-store' })
                            .then(function (r) { show(r.ok ? t.started.replace(':status', r.status) : String(r.status)); })
                            .catch(function () { show(t.none); });
                    }, 5000);
                });
            })();
            </script>
            HTML);
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('setup')
            ->path('setup')
            ->brandName(fn (): string => __('epesi setup'))
            ->colors([
                'primary' => Color::Amber,
                'gray' => Color::Neutral,
            ])
            ->viteTheme('resources/css/filament/epesi/theme.css')
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => $this->compactWizardHeaderStyles())
            ->renderHook(PanelsRenderHook::BODY_END, fn () => $this->scriptWatchdog())
            // Not at the panel root: that is Filament's own "go to the first
            // page" route, which for a panel with no navigation leads back to
            // itself. routes/web.php sends /setup here instead.
            ->routes(function (): void {
                Route::get('/install', InstallWizard::class)->name('install');
                Route::get('/finish', FinishSetup::class)->name('finish');
            })
            ->middleware([
                DisabledInDemo::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ClearStaleSetupAuth::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // Persistent: Livewire's own requests need the language too.
            ->middleware([SetLocale::class], isPersistent: true);
    }
}
