<?php

namespace App\Filament\Support;

use App\Support\Impersonation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * The bar at the top of every page while a super_admin is logged in as
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
        return new HtmlString(
            '<div role="status" style="display:flex;flex-wrap:wrap;align-items:center;gap:.5rem 1rem;margin:0 0 1rem;padding:.75rem 1rem;border-radius:.75rem;background:color-mix(in srgb, var(--warning-500) 15%, transparent);font-size:.875rem">'
            .'<span>'.e(__('You are logged in as :name.', ['name' => $user->displayName()])).'</span>'
            .'<form method="post" action="'.e(route('impersonation.leave')).'" style="margin:0">'
            .'<input type="hidden" name="_token" value="'.e(csrf_token()).'">'
            .'<button type="submit" style="font-weight:600;text-decoration:underline;cursor:pointer">'.e(__('Back to :name', ['name' => $impersonator->displayName()])).'</button>'
            .'</form>'
            .'</div>'
        );
    }
}
