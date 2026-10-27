<?php

namespace Epesi\Modules\OAuth\Providers;

/**
 * A setting of a provider's app beyond its client ID and secret, entered on
 * the app's OAuth Apps record next to them and stored with it
 * (OAuthClient::$settings), so each environment's app has its own value:
 * Allegro's User-Agent, for one, is registered per app. A provider declares
 * these in OAuthProvider::appFields(); the shared pages render them as text
 * fields and nothing there branches on a provider.
 *
 * The label and help are English keys, translated where they are shown.
 * The default fills the field of a new app and applies whenever the stored
 * value is empty (OAuthClient::setting()).
 */
final class AppField
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly ?string $help = null,
        public readonly ?string $default = null,
        public readonly bool $required = false,
        public readonly int $maxLength = 255,
    ) {}
}
