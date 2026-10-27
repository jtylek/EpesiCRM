<?php

namespace Epesi\Modules\OAuth\Events;

use Epesi\Modules\OAuth\Models\OAuthToken;
use Illuminate\Foundation\Events\Dispatchable;

/** A token was renewed with its refresh token; a rotated refresh token is stored already. */
class TokenRefreshed
{
    use Dispatchable;

    public function __construct(public readonly OAuthToken $token) {}
}
