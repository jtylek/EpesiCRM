<?php

namespace App\Support\Auth;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;

/**
 * The panel an account works in: the main CRM panel for staff, otherwise the
 * dealer portal, otherwise the customer portal. Never Administration or User
 * Settings, which nobody works in on their own. Shared by the links e-mailed
 * to an account (NewAccountMailer, VerifyContactEmailController) and by "Log
 * in as user" (Impersonation), so they agree on where an account belongs.
 */
class HomePanel
{
    /** Tried in this order; an account with staff roles too (a dealer who is also an employee) gets main. */
    public const PANELS = ['main', 'dealer', 'portal'];

    /** Null when the account can open none of them: deactivated, no role, or a role with nowhere to sign in. */
    public static function for(User $user): ?Panel
    {
        foreach (self::PANELS as $id) {
            $panel = Filament::getPanel($id);

            if ($user->canAccessPanel($panel)) {
                return $panel;
            }
        }

        return null;
    }
}
