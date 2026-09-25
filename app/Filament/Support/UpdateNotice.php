<?php

namespace App\Filament\Support;

use App\Filament\Administration\Pages\DatabaseUpdate;
use App\Services\Setup\SystemUpdate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * The bar at the top of every page telling an administrator that a database
 * update is waiting: after new release files are unpacked, nothing else says
 * so, and pages relying on the new tables fail until it has run. Only
 * super_admins see it, as only they can run it.
 */
class UpdateNotice
{
    public static function render(): string|HtmlString
    {
        if (! Auth::user()?->hasRole('super_admin') || request()->routeIs('filament.administration.pages.database-update')) {
            return '';
        }

        $waiting = SystemUpdate::waiting();

        if ($waiting === 0) {
            return '';
        }

        // Inline styles: the panels use Filament's precompiled stylesheet.
        return new HtmlString(
            '<div role="status" style="margin:0 0 1rem;padding:.75rem 1rem;border-radius:.75rem;background:color-mix(in srgb, var(--warning-500) 15%, transparent);font-size:.875rem">'
            .e(trans_choice('{1} A new version of epesi is installed, and :count database change is waiting.|[2,*] A new version of epesi is installed, and :count database changes are waiting.', $waiting, ['count' => $waiting]))
            .' <a href="'.e(DatabaseUpdate::getUrl(panel: 'administration')).'" style="font-weight:600;text-decoration:underline">'.e(__('Update the database')).'</a>'
            .'</div>'
        );
    }
}
