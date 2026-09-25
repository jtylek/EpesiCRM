<?php

namespace App\Support;

use App\Listeners\FinalizeLoginAudit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

/**
 * Port of Epesi's "Log as user" (Base_User_Administrator::log_as_user()): a
 * super_admin takes over another account's session without its password.
 * Epesi just swapped the session's user and left no way back but logging
 * out; here the session also remembers who started it, so the bar at the top
 * of every page (ImpersonationNotice) can switch back, and Login Audit shows
 * who was really at the keyboard (TrackLoginAudit).
 */
class Impersonation
{
    public const SESSION_KEY = 'impersonator_id';

    /**
     * Only a super_admin, as in Epesi; not as yourself, and not as an account
     * that couldn't sign in to the CRM on its own (deactivated, or with no
     * role). Not a UserPolicy ability: filament-shield's Gate::before lets a
     * super_admin through every one of those.
     */
    public static function allowed(User $target): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && $user->hasRole('super_admin')
            && ! $target->is($user)
            && $target->canAccessPanel(Filament::getPanel('main'));
    }

    public static function start(User $target): void
    {
        if (! self::allowed($target)) {
            throw new AuthorizationException;
        }

        // Logging in as someone else again from an impersonated super_admin
        // account still returns to the one who started it all.
        $impersonatorId = self::impersonatorId() ?? Auth::id();

        self::switchTo($target);

        if ($target->id === $impersonatorId) {
            session()->forget(self::SESSION_KEY);
        } else {
            session()->put(self::SESSION_KEY, $impersonatorId);
        }
    }

    /**
     * Back to the account that started it. That account is signed out
     * instead if it has since lost its super_admin role or been deactivated.
     */
    public static function stop(): void
    {
        $impersonator = self::impersonator();
        session()->forget(self::SESSION_KEY);

        if ($impersonator?->active && $impersonator->hasRole('super_admin')) {
            self::switchTo($impersonator);

            return;
        }

        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
    }

    public static function impersonatorId(): ?int
    {
        $id = session(self::SESSION_KEY);

        return $id === null ? null : (int) $id;
    }

    public static function impersonator(): ?User
    {
        $id = self::impersonatorId();

        return $id ? User::find($id) : null;
    }

    private static function switchTo(User $user): void
    {
        // The session changes hands: its Login Audit row ends here like on a
        // logout, and TrackLoginAudit opens the new user's on the next page.
        FinalizeLoginAudit::closeCurrent();

        Auth::login($user);

        // AuthenticateSession checks the session against the previous user's
        // password hash and would sign the new one out on the next page; it
        // stores the new user's hash itself when there is none.
        session()->forget('password_hash_'.Auth::getDefaultDriver());
    }
}
