<?php

namespace Epesi\Modules\OAuth\Contracts;

use App\Models\User;

/**
 * Optional on an OAuthOwner: who is told (in the bell) when its token needs
 * connecting again. Without it, every super admin is. An empty list tells
 * nobody, e.g. for an owner whose module reacts to TokenNeedsReconnect itself.
 */
interface OAuthRecipients
{
    /**
     * @return iterable<User>
     */
    public function oauthRecipients(): iterable;
}
