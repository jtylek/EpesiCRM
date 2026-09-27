<?php

namespace App\Support\Auth;

use App\Models\User;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Illuminate\Support\Facades\Auth;

/**
 * What the History of a user (Administration → Users) records beyond the
 * attributes activitylog logs by itself (User::getActivitylogOptions()): the
 * things that aren't a changed column, or mustn't be written down as one. A
 * password is never logged, only that it changed; the hash would be sitting
 * in the log for anyone who can read it.
 *
 * Shown by the shared History addon, which prints an event's description when
 * it has no attribute changes and "Label: old → new" when it has.
 */
class UserActivity
{
    /**
     * By whoever changed it: an administrator's Reset Password, or the user
     * themself from an e-mailed link, when nobody is signed in.
     */
    public static function passwordChanged(User $user): void
    {
        activity('user')
            ->performedOn($user)
            ->causedBy(Auth::user() ?? $user)
            ->event('password changed')
            ->log(__('Password changed'));
    }

    /**
     * An e-mailed link to choose a password went out, whether for a new user
     * or from Reset Password left blank. The link itself isn't logged.
     */
    public static function passwordLinkSent(User $user): void
    {
        activity('user')
            ->performedOn($user)
            ->causedBy(Auth::user())
            ->event('password link sent')
            ->log(__('Link to choose a password sent'));
    }

    /**
     * @param  array<int, string>  $old  role names
     * @param  array<int, string>  $new  role names
     */
    public static function rolesChanged(User $user, array $old, array $new): void
    {
        sort($old);
        sort($new);

        if ($old === $new) {
            return;
        }

        activity('user')
            ->performedOn($user)
            ->causedBy(Auth::user())
            ->event('roles changed')
            ->withProperties(['old' => ['roles' => $old], 'attributes' => ['roles' => $new]])
            ->log(__('Roles changed'));
    }

    /** The contact the login was made for (CreateUser). */
    public static function contactLinked(User $user, Contact $contact): void
    {
        activity('user')
            ->performedOn($user)
            ->causedBy(Auth::user())
            ->event('contact linked')
            ->withProperties(['old' => ['contact' => null], 'attributes' => ['contact' => $contact->full_name ?: $contact->email]])
            ->log(__('Contact linked'));
    }
}
