<?php

namespace App\Http\Middleware;

use App\Support\Appearance\CurrentTheme;
use Closure;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registered on MainPanelProvider and UserSettingsPanelProvider's own
 * middleware lists, right after AuthenticateSession — must run after the
 * session/auth middleware (so Auth::user() is resolved) and before anything
 * renders: FilamentColor caches the colours the first time they're read,
 * which for a page happens very early in <head>, well before any render
 * hook fires.
 *
 * Goes through CurrentTheme rather than naming Epesi\Modules\Appearance
 * directly — see that class's docblock.
 */
class ApplyThemeColor
{
    public function handle(Request $request, Closure $next): Response
    {
        $accentColor = CurrentTheme::forUser(Auth::user())['accent_color'] ?? null;

        if ($accentColor) {
            FilamentColor::register(fn (): array => ['primary' => $accentColor]);
        }

        return $next($request);
    }
}
