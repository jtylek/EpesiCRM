<?php

namespace App\Filament\Support;

use App\Support\Impersonation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * The sticky bar at the bottom of every page while a super_admin is logged in as
 * someone else ("Log in as user"): whose account this is, so nothing gets
 * done under it by mistake, and the way back to their own.
 */
class ImpersonationNotice
{
    public static function render(): string|HtmlString
    {
        $user = Auth::user();
        $impersonator = $user ? Impersonation::impersonator() : null;

        if (! $impersonator) {
            return '';
        }

        // Inline styles: the panels use Filament's precompiled stylesheet.
        // A sticky bar fixed to the bottom of the screen; the body gets bottom
        // padding so it never covers the end of the page.
        return new HtmlString(
            '<style>body{padding-bottom:3.25rem}</style>'
            .'<div role="status" style="position:fixed;left:0;right:0;bottom:0;z-index:50;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:.5rem 1rem;padding:.75rem 1rem;border-top:1px solid var(--warning-500);background:color-mix(in srgb, var(--warning-500) 20%, rgb(24 24 27));color:#fff;font-size:.875rem;box-shadow:0 -2px 8px rgb(0 0 0 / .25)">'
            .'<span>'.e(__('You are logged in as :name.', ['name' => $user->displayName()])).'</span>'
            .'<form method="post" action="'.e(route('impersonation.leave')).'" style="margin:0">'
            .'<input type="hidden" name="_token" value="'.e(csrf_token()).'">'
            .'<button type="submit" style="font-weight:600;text-decoration:underline;cursor:pointer">'.e(__('Back to :name', ['name' => $impersonator->displayName()])).'</button>'
            .'</form>'
            .'</div>'
        );
    }
}
