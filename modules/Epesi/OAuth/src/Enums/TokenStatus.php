<?php

namespace Epesi\Modules\OAuth\Enums;

enum TokenStatus: string
{
    case Active = 'active';

    /** The provider refused the refresh token (revoked, expired, password changed): only signing in again helps. */
    case NeedsReconnect = 'needs_reconnect';
}
