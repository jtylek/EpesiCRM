<?php

namespace App\Filament\Auth;

use App\Models\User;
use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseResetPassword;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use SensitiveParameter;

/**
 * The page an e-mailed reset link opens (legacy Epesi's "Set new password"),
 * except that being signed in already never gets in the way of it.
 *
 * Filament's own page redirects a signed-in visitor straight into the current
 * panel instead of showing the form — reasonable when it's their own link,
 * but every panel here shares one login session (none declares its own
 * `->authGuard()`), so it just as readily fires for someone else's link. A
 * customer's link opened by an administrator still signed in from
 * Administration, say, would send the administrator to the customer
 * portal — which they can't open — for a 403 with nothing to explain it
 * (see AI-shared/Customer-portal.md).
 *
 * Signing out first — a real logout, the same one Impersonation::stop() does
 * (Auth::logout(), then the session is invalidated and its token
 * regenerated) so the Logout event still fires and Login Audit's row for
 * that session still closes (FinalizeLoginAudit) — leaves the visitor a
 * guest, and Filament's own mount() then does exactly what it does for
 * anyone else opening the link cold: shows the form. A link for the account
 * already signed in is untouched, since there's nothing wrong to fix.
 */
class ResetPassword extends BaseResetPassword
{
    public function mount(?string $email = null, #[SensitiveParameter] ?string $token = null): void
    {
        $email ??= request()->query('email');

        if (Filament::auth()->check() && ! self::signedInAs(Filament::auth()->user(), $email)) {
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();
        }

        parent::mount($email, $token);
    }

    private static function signedInAs(mixed $user, ?string $email): bool
    {
        return $user instanceof User
            && $email !== null
            && mb_strtolower($user->email) === mb_strtolower($email);
    }
}
