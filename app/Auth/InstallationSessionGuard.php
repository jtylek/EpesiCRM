<?php

namespace App\Auth;

use Illuminate\Auth\SessionGuard;

/**
 * Laravel's session guard, with a "remember me" cookie name that is unique to this
 * installation. The stock name depends only on the guard class, so every Laravel
 * app on the same host (localhost, or one domain with several installations) used
 * the same cookie and overwrote or discarded each other's. The session cookie's
 * name (config/session.php) carries the same per-installation suffix.
 */
class InstallationSessionGuard extends SessionGuard
{
    public function getRecallerName(): string
    {
        return 'remember_'.$this->name.'_'.sha1(static::class.'|'.config('session.cookie'));
    }
}
