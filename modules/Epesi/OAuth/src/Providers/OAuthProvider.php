<?php

namespace Epesi\Modules\OAuth\Providers;

use Epesi\Modules\OAuth\Models\OAuthClient;

/**
 * One OAuth2 authorization server, as epesi talks to it: the authorization
 * code flow with PKCE, the refresh grant, the client credentials grant for
 * app-only tokens, and optionally revocation. A module that needs a provider
 * subclasses this and registers it from its service provider's boot() with
 * OAuthProviders::register(); nothing in the shared pages branches on one.
 *
 * Endpoints receive the installation's OAuthClient, which carries the
 * environment (production or a sandbox) and the Microsoft tenant, so a
 * provider builds its URLs from them. Labels and help texts are English
 * keys, translated where they are shown.
 */
abstract class OAuthProvider
{
    /** Stable key, stored with every token and client row: never rename it. */
    abstract public function key(): string;

    /** The provider's name, e.g. "Microsoft 365". */
    abstract public function label(): string;

    abstract public function authorizeUrl(OAuthClient $client): string;

    abstract public function tokenUrl(OAuthClient $client): string;

    /**
     * Where a token is revoked on disconnect (RFC 7009), null when the
     * provider has no such endpoint: disconnecting then only forgets it.
     */
    public function revokeUrl(OAuthClient $client): ?string
    {
        return null;
    }

    /**
     * The scopes asked for when the connecting module names none.
     *
     * @return list<string>
     */
    abstract public function scopes(): array;

    public function scopeSeparator(): string
    {
        return ' ';
    }

    /**
     * Extra query parameters for the authorization request.
     *
     * @return array<string, string>
     */
    public function authorizeParameters(): array
    {
        return [];
    }

    /**
     * The environments an installation can register an app in, as key =>
     * label (an English key). Each has its own client ID and secret, and each
     * token remembers which one it came from (OAuthToken::$environment).
     *
     * @return array<string, string>
     */
    public function environments(): array
    {
        return [OAuthClient::PRODUCTION => 'Production'];
    }

    /**
     * Whether the token endpoint wants the client ID and secret as HTTP Basic
     * authentication (client_secret_basic, as Allegro) rather than in the
     * form body (client_secret_post, as Microsoft and Google).
     */
    public function usesBasicAuth(): bool
    {
        return false;
    }

    /**
     * The provider's own callback path (e.g. "administration/allegro/callback"),
     * for an app already registered with that redirect URI: providers such as
     * Allegro require the URI to match exactly. Served by the same callback
     * handler as the shared default, `oauth/callback`, which null keeps.
     */
    public function callbackPath(): ?string
    {
        return null;
    }

    /**
     * HTTP headers sent with every request to the provider's token server
     * (code exchange, refresh, app tokens, revocation), and offered to modules
     * for their API calls through OAuthTokens::http(). Called for every
     * request with the app of the environment in use, so it can read that
     * app's settings (appFields()): Allegro, for one, blocks requests without
     * the User-Agent its app was registered with. $client is null when the
     * environment has no app yet; defaults apply then.
     *
     * @return array<string, string>
     */
    public function headers(?OAuthClient $client = null): array
    {
        return [];
    }

    /**
     * Settings of the app beyond its client ID and secret, entered on its
     * OAuth Apps record and read with OAuthClient::setting().
     *
     * @return list<AppField>
     */
    public function appFields(): array
    {
        return [];
    }

    public function appField(string $key): ?AppField
    {
        foreach ($this->appFields() as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Whether an app of this provider can be tested on its own, without
     * anyone signing in: OAuth Apps then offers Test app on its record.
     * Microsoft and Google have no such check.
     */
    public function supportsAppTest(): bool
    {
        return false;
    }

    /**
     * Tests $app as stored (an app-only token, a read call, …) and says what
     * each step found. Must not store anything or change anything at the
     * provider. Called only when supportsAppTest(); an OAuthException thrown
     * here is shown as the test's failure.
     *
     * @return list<AppCheck>
     */
    public function testApp(OAuthClient $app): array
    {
        return [];
    }

    /**
     * What a passed app test doesn't prove, shown with its result (an
     * English key).
     */
    public function appTestLimits(): string
    {
        return 'This test doesn\'t check the redirect URI or the permissions (scopes): the provider checks those only when someone connects an account.';
    }

    /** Whether the app setting has a tenant (directory) field. */
    public function usesTenant(): bool
    {
        return false;
    }

    /** Where an administrator registers the app, shown on its OAuth Apps record. */
    public function consoleUrl(): ?string
    {
        return null;
    }

    /** How to register the app, shown on its OAuth Apps record (an English key). */
    public function setupHelp(): ?string
    {
        return null;
    }
}
