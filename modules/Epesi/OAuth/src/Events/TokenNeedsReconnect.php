<?php

namespace Epesi\Modules\OAuth\Events;

use Epesi\Modules\OAuth\Models\OAuthToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The provider refused the token's refresh token (invalid_grant), once per
 * change from active: only connecting again helps. A module can stop its
 * syncing here. $owner is null when the owner row is gone.
 */
class TokenNeedsReconnect
{
    use Dispatchable;

    public function __construct(
        public readonly OAuthToken $token,
        public readonly ?Model $owner,
        public readonly string $error,
    ) {}
}
