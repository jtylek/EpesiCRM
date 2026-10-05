<?php

namespace App\Filament\Auth;

use App\Support\Appearance\AppName;
use App\Support\Logo;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Html;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * Filament's login page with what every epesi login page has: the epesi logo at
 * the very top of the card, and a light/dark/system switcher on the "Remember me"
 * line. Used by the administration and portal panels as it is; the main panel's
 * App\Filament\Auth\Login extends it.
 */
class PanelLogin extends BaseLogin
{
    /**
     * The heading of the login page is the panel's brand name: the "login title"
     * from Administration → Appearance → Logo & Title, except in the customer portal, which has a
     * title of its own (set on the panel). Livewire boots on every request.
     */
    public function boot(): void
    {
        $panel = Filament::getCurrentPanel();

        if ($panel && $panel->getId() !== 'portal') {
            $panel->brandName(fn (): string => AppName::login());
        }
    }

    public function mount(): void
    {
        $preview = $this->previewMode();

        if ($preview) {
            // An administrator looking at this page from Administration → Appearance →
            // Logo & Title: no redirect away because they are signed in, and the
            // page in the colour mode asked for, whatever their own is.
            $this->form->fill();
            $this->forceColourMode($preview);
        } else {
            parent::mount();
        }

        FilamentView::registerRenderHook(
            PanelsRenderHook::SIMPLE_PAGE_START,
            fn (): HtmlString => new HtmlString(
                '<div style="display:flex;justify-content:center;margin-bottom:1rem">'
                .(Filament::getCurrentPanel()?->getId() === 'portal' ? Logo::portal() : Logo::login()).'</div>'
            ),
            scopes: static::class,
        );
    }

    /** "light" or "dark" when a signed-in administrator opened the page with ?preview=… */
    private function previewMode(): ?string
    {
        $mode = request()->query('preview');

        return in_array($mode, ['light', 'dark'], true) && auth()->user()?->hasRole('super_admin')
            ? $mode
            : null;
    }

    /**
     * Filament reads the colour mode from localStorage as the page loads (and the
     * theme switcher writes it there). In the preview frame — same origin as the
     * administrator's own tab — answer for it and refuse the writes, so the
     * preview shows the mode asked for and leaves their own choice alone.
     */
    private function forceColourMode(string $mode): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_START,
            fn (): HtmlString => new HtmlString(
                // The signed-in administrator's own bell and avatar are not part of the page.
                '<style>.fi-simple-layout-header{display:none}</style>'
                .'<script>(function(){var g=Storage.prototype.getItem,s=Storage.prototype.setItem;'
                .'Storage.prototype.getItem=function(k){return k==="theme"?'.json_encode($mode).':g.call(this,k)};'
                .'Storage.prototype.setItem=function(k,v){if(k!=="theme")s.call(this,k,v)};})()</script>'
            ),
            scopes: static::class,
        );
    }

    protected function getRememberFormComponent(): Component
    {
        return Flex::make([
            parent::getRememberFormComponent(),
            Html::make(fn (): HtmlString => new HtmlString(Blade::render('<x-filament-panels::theme-switcher />')))
                ->grow(false),
        ])->extraAttributes(['style' => 'align-items:center;justify-content:space-between']);
    }
}
