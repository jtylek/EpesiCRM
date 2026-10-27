<?php

namespace Epesi\Modules\OAuth\Events;

use App\Models\User;
use Epesi\Modules\OAuth\Models\OAuthToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An owner was connected (or connected again, e.g. with more scopes): the
 * token row is stored and valid. A module can call the provider's API now,
 * e.g. to read and keep the seller account's login on the owner.
 */
class TokenConnected
{
    use Dispatchable;

    public function __construct(
        public readonly OAuthToken $token,
        public readonly Model $owner,
        public readonly ?User $user,
    ) {}
}
