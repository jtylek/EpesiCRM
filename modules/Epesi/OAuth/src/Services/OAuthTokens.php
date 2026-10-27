<?php

namespace Epesi\Modules\OAuth\Services;

use App\Models\User;
use App\Support\Locale\Locales;
use App\Support\RelativeUrl;
use Epesi\Modules\OAuth\Contracts\OAuthOwner;
use Epesi\Modules\OAuth\Contracts\OAuthRecipients;
use Epesi\Modules\OAuth\Enums\TokenStatus;
use Epesi\Modules\OAuth\Events\TokenConnected;
use Epesi\Modules\OAuth\Events\TokenNeedsReconnect;
use Epesi\Modules\OAuth\Events\TokenRefreshed;
use Epesi\Modules\OAuth\Exceptions\OAuthException;
use Epesi\Modules\OAuth\Models\OAuthClient;
use Epesi\Modules\OAuth\Models\OAuthToken;
use Epesi\Modules\OAuth\OAuthProviders;
use Epesi\Modules\OAuth\Providers\OAuthProvider;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The token service: stores what a provider issued, hands out a valid access
 * token, renews it when it is close to expiring (or when the API refused it),
 * and forgets it. It needs no session or request, so queue workers, cron and
 * the scheduler call it as pages do.
 *
 * Refreshing runs under a cache lock per token. Microsoft and Allegro issue a
 * new refresh token on every refresh and invalidate the old one, so two
 * processes refreshing at once (a page and cron, two workers) would leave one
 * of them storing a dead token. Whoever waited for the lock reads the row
 * again and uses what the other one stored. The lock lives in the cache store
 * every process shares (CACHE_STORE: memcached or files on an installation,
 * AI-shared/cron.md), or config('epesi-oauth.lock_store'); never in the
 * per-process `array` store outside tests.
 *
 * Nothing here logs a token, a code or a secret; errors carry only the
 * provider's error code and description.
 */
class OAuthTokens
{
    /**
     * A valid access token of $owner's, renewed first when it expires within
     * config('epesi-oauth.refresh_ahead') seconds.
     *
     * @throws OAuthException not connected, refused, or the provider is unreachable
     */
    public function accessToken(Model&OAuthOwner $owner, ?string $provider = null): string
    {
        return $this->validAccessToken($this->tokenOf($owner, $provider));
    }

    public function validAccessToken(OAuthToken $token): string
    {
        $provider = OAuthProviders::get($token->provider);

        if ($token->needsReconnect()) {
            throw OAuthException::needsReconnect($provider->label());
        }

        $ahead = (int) config('epesi-oauth.refresh_ahead', 300);

        if ($token->expiresWithin($ahead)) {
            $token = $this->refresh($token, $ahead);
        }

        return (string) $token->access_token;
    }

    /**
     * A renewed access token, whatever the stored expiry says: for an API call
     * answered 401 before then. Pass the token the API refused as $rejected:
     * when another process has renewed it meanwhile, its token is returned
     * without renewing again (and so without rotating the refresh token twice).
     *
     * @throws OAuthException
     */
    public function freshToken(Model&OAuthOwner $owner, ?string $provider = null, ?string $rejected = null): string
    {
        return (string) $this->refresh($this->tokenOf($owner, $provider), force: true, rejected: $rejected)->access_token;
    }

    /** Marks $owner's access token expired, so the next accessToken() renews it. */
    public function invalidate(Model&OAuthOwner $owner, ?string $provider = null): void
    {
        $owner->oauthToken($provider)?->forceFill(['expires_at' => now()->subSecond()])->save();
    }

    /**
     * The environment $owner's token came from ("production", "sandbox"), for
     * a module to pick its API host by. Null when not connected.
     */
    public function environment(Model&OAuthOwner $owner, ?string $provider = null): ?string
    {
        return $owner->oauthToken($provider)?->environment;
    }

    /**
     * Renews $token under the lock, unless (once the lock is held and the row
     * read again) it no longer needs it: it no longer expires within $ahead
     * seconds, or, when $force, its access token is no longer $rejected.
     *
     * @throws OAuthException
     */
    public function refresh(OAuthToken $token, int $ahead = 0, bool $force = false, ?string $rejected = null): OAuthToken
    {
        $provider = OAuthProviders::get($token->provider);
        $lock = $this->lock('epesi-oauth-refresh:'.$token->getKey());

        try {
            $lock->block(max(0, (int) config('epesi-oauth.lock_wait', 15)));
        } catch (LockTimeoutException) {
            throw OAuthException::busy();
        }

        try {
            // What another process may have stored while this one waited.
            $token->refresh();

            if ($token->needsReconnect()) {
                throw OAuthException::needsReconnect($provider->label());
            }

            $due = $force
                ? $rejected === null || $token->access_token === $rejected
                : $token->expiresWithin($ahead);

            if (! $due) {
                return $token;
            }

            if (blank($token->refresh_token)) {
                $this->markNeedsReconnect($token, $provider, __('The provider gave no refresh token.'));

                throw OAuthException::needsReconnect($provider->label());
            }

            $client = $this->client($provider, $token->environment);

            $response = $this->post($provider, $client, $provider->tokenUrl($client), [
                'grant_type' => 'refresh_token',
                'refresh_token' => $token->refresh_token,
            ]);

            if ($response->failed()) {
                $error = $response->json('error');
                $exception = OAuthException::fromProvider($provider->label(), is_string($error) ? $error : null, $this->description($response), $response->status());

                // invalid_grant: the refresh token is revoked, expired or was
                // already used. Nothing but signing in again helps; anything
                // else (a server error, a wrong secret) may pass.
                if ($error === 'invalid_grant') {
                    $this->markNeedsReconnect($token, $provider, $exception->getMessage());

                    throw OAuthException::needsReconnect($provider->label());
                }

                $token->forceFill(['last_error' => $exception->getMessage()])->save();

                throw $exception;
            }

            $this->fill($token, $response->json() ?? []);
            $token->save();

            TokenRefreshed::dispatch($token);

            return $token;
        } finally {
            $lock->release();
        }
    }

    /**
     * Stores what the token endpoint answered to an authorization code, as
     * $owner's token from $provider: the same row when the owner was
     * connected before (connecting again, e.g. for more scopes), else a new
     * one. $scopes are those asked for, kept unless the provider names the
     * granted ones.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<string>|null  $scopes
     */
    public function store(Model&OAuthOwner $owner, OAuthProvider $provider, array $payload, ?User $by = null, string $environment = OAuthClient::PRODUCTION, ?array $scopes = null): OAuthToken
    {
        $token = $owner->oauthTokens()->firstOrNew(['provider' => $provider->key()]);

        // A connection made again starts clean: nothing from the old one.
        $token->refresh_token = null;
        $token->scopes = $scopes ?? $provider->scopes();
        $token->environment = $environment;
        $token->connected_by = $by?->getKey();
        $this->fill($token, $payload);
        $token->save();

        TokenConnected::dispatch($token, $owner, $by);

        return $token;
    }

    /** Forgets $owner's token from $provider, revoking it at the provider when it can. */
    public function disconnect(Model&OAuthOwner $owner, string $provider): void
    {
        $token = $owner->oauthToken($provider);

        if (! $token) {
            return;
        }

        $definition = OAuthProviders::find($provider);
        $client = OAuthClient::for($provider, $token->environment);
        $url = $definition && $client ? $definition->revokeUrl($client) : null;
        $secret = $token->refresh_token ?: $token->access_token;

        if ($definition && $url && filled($secret)) {
            try {
                $this->http($definition, $client)->asForm()->post($url, ['token' => $secret]);
            } catch (Throwable) {
                // Forgetting it here is what matters; the provider lets an unused token lapse.
            }
        }

        $token->delete();
    }

    /**
     * An app-only access token (the client credentials grant) for calls that
     * act for no owner, such as a public catalog. Kept in the cache until a
     * minute before it expires; nothing to rotate, so no lock.
     *
     * @param  list<string>|null  $scopes  null for whatever the app is allowed
     *
     * @throws OAuthException
     */
    public function appToken(string $provider, string $environment = OAuthClient::PRODUCTION, ?array $scopes = null): string
    {
        $definition = OAuthProviders::get($provider);
        $client = $this->client($definition, $environment);
        $key = 'epesi-oauth-app:'.$provider.':'.$environment.':'.md5($client->client_id.'|'.implode(' ', $scopes ?? []));

        if (is_string($cached = Cache::get($key))) {
            return $cached;
        }

        ['access_token' => $access, 'expires_in' => $expiresIn] = $this->requestAppToken($definition, $client, $scopes);

        Cache::put($key, $access, max(1, $expiresIn - 60));

        return $access;
    }

    /**
     * Asks the token server for an app-only token for $client, every time:
     * nothing is cached or stored. appToken() keeps what this returns; an app
     * test (OAuthProvider::testApp()) uses it as it stands, so a cached token
     * never hides a secret changed since.
     *
     * @param  list<string>|null  $scopes
     * @return array{access_token: string, expires_in: int}
     *
     * @throws OAuthException not configured, refused, or unreachable
     */
    public function requestAppToken(OAuthProvider $provider, OAuthClient $client, ?array $scopes = null): array
    {
        if (! $client->isConfigured()) {
            throw OAuthException::notConfigured($provider->label());
        }

        $response = $this->post($provider, $client, $provider->tokenUrl($client), [
            'grant_type' => 'client_credentials',
            'scope' => $scopes ? implode($provider->scopeSeparator(), $scopes) : null,
        ]);

        $access = $response->json('access_token');

        if ($response->failed() || ! is_string($access) || $access === '') {
            $error = $response->json('error');

            throw OAuthException::fromProvider($provider->label(), is_string($error) ? $error : null, $this->description($response), $response->status());
        }

        return ['access_token' => $access, 'expires_in' => (int) $response->json('expires_in', 3600)];
    }

    /** The installation's app for $provider in $environment, set up with an ID and a secret. */
    public function client(OAuthProvider $provider, string $environment = OAuthClient::PRODUCTION): OAuthClient
    {
        $client = OAuthClient::for($provider->key(), $environment);

        if (! $client?->isConfigured()) {
            throw OAuthException::notConfigured($provider->label());
        }

        return $client;
    }

    /**
     * A request to the provider's token server, with the client's credentials
     * as the provider wants them (body or HTTP Basic) and its headers.
     *
     * @param  array<string, string|null>  $parameters
     */
    public function post(OAuthProvider $provider, OAuthClient $client, string $url, array $parameters): Response
    {
        $request = $this->http($provider, $client)->asForm()->acceptJson();

        if ($provider->usesBasicAuth()) {
            $request = $request->withBasicAuth((string) $client->client_id, (string) $client->client_secret);
        } else {
            $parameters += ['client_id' => $client->client_id, 'client_secret' => $client->client_secret];
        }

        try {
            return $request->post($url, array_filter($parameters, fn ($value): bool => $value !== null));
        } catch (ConnectionException) {
            throw new OAuthException(__(':provider could not be reached. Try again later.', ['provider' => $provider->label()]));
        }
    }

    /**
     * An HTTP request carrying the provider's own headers (OAuthProvider::headers(),
     * e.g. Allegro's required User-Agent), for the token server and for a
     * module's API calls alike. $app is the app of the environment in use, or
     * that environment's name ("production", "sandbox"), so the headers come
     * from the right app's settings; null is production.
     */
    public function http(OAuthProvider|string $provider, OAuthClient|string|null $app = null): PendingRequest
    {
        $provider = is_string($provider) ? OAuthProviders::get($provider) : $provider;
        $client = $app instanceof OAuthClient ? $app : OAuthClient::for($provider->key(), $app ?? OAuthClient::PRODUCTION);

        return Http::withHeaders($provider->headers($client))
            ->timeout((int) config('epesi-oauth.timeout', 20));
    }

    /**
     * http() with $owner's valid access token as the bearer token: what a
     * module calls a provider's API with.
     *
     * @throws OAuthException
     */
    public function authorizedHttp(Model&OAuthOwner $owner, string $provider): PendingRequest
    {
        $token = $this->accessToken($owner, $provider);

        return $this->http($provider, $this->environment($owner, $provider))->withToken($token);
    }

    public function description(Response $response): ?string
    {
        $description = $response->json('error_description');

        return is_string($description) ? $description : null;
    }

    protected function tokenOf(Model&OAuthOwner $owner, ?string $provider): OAuthToken
    {
        return $owner->oauthToken($provider)
            ?? throw OAuthException::notConnected($provider ? OAuthProviders::get($provider)->label() : __('the provider'));
    }

    /**
     * The refresh lock, in a store every process shares: the configured
     * one, else the default, except that the per-process `array` store is
     * swapped for files outside tests.
     */
    protected function lock(string $name): Lock
    {
        $store = config('epesi-oauth.lock_store') ?: config('cache.default');

        if (config("cache.stores.{$store}.driver") === 'array' && ! app()->runningUnitTests()) {
            $store = 'file';
        }

        $cache = Cache::store($store)->getStore();

        return $cache instanceof LockProvider
            ? $cache->lock($name, 60)
            : Cache::store('file')->lock($name, 60);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function fill(OAuthToken $token, array $payload): void
    {
        if (blank($payload['access_token'] ?? null)) {
            throw new OAuthException(__('The provider answered without an access token.'));
        }

        $expiresIn = (int) ($payload['expires_in'] ?? 0);

        $token->access_token = (string) $payload['access_token'];
        $token->expires_at = $expiresIn > 0 ? now()->addSeconds($expiresIn) : null;

        // Rotating providers send a new refresh token; the others keep the old one valid.
        if (filled($payload['refresh_token'] ?? null)) {
            $token->refresh_token = (string) $payload['refresh_token'];
        }

        // The scopes granted, when the provider says; else those asked for stay.
        if (filled($payload['scope'] ?? null)) {
            $token->scopes = array_values(array_filter(preg_split('/[\s,]+/', (string) $payload['scope']) ?: []));
        }

        if (is_string($payload['id_token'] ?? null) && ($account = $this->accountFrom($payload['id_token']))) {
            $token->account = $account;
        }

        $token->status = TokenStatus::Active;
        $token->last_error = null;
        $token->refreshed_at = now();
    }

    /**
     * Who signed in, from the ID token's claims. The token came straight from
     * the provider's token endpoint over TLS and is only displayed, so its
     * signature isn't checked.
     */
    protected function accountFrom(string $idToken): ?string
    {
        $parts = explode('.', $idToken);
        $claims = count($parts) === 3 ? json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true) : null;

        if (! is_array($claims)) {
            return null;
        }

        $account = $claims['email'] ?? $claims['preferred_username'] ?? null;

        return is_string($account) ? mb_substr($account, 0, 255) : null;
    }

    /** Marks $token refused; on the change from active, says so (event, bell). */
    protected function markNeedsReconnect(OAuthToken $token, OAuthProvider $provider, string $error): void
    {
        $wasActive = ! $token->needsReconnect();

        $token->forceFill(['status' => TokenStatus::NeedsReconnect, 'last_error' => $error])->save();

        if ($wasActive) {
            $owner = $token->owner;

            TokenNeedsReconnect::dispatch($token, $owner instanceof Model ? $owner : null, $error);

            $this->notifyOwner($token, $provider, $owner instanceof OAuthOwner ? $owner : null);
        }
    }

    protected function notifyOwner(OAuthToken $token, OAuthProvider $provider, ?OAuthOwner $owner): void
    {
        $recipients = $owner instanceof OAuthRecipients
            ? collect($owner->oauthRecipients())
            : User::query()->role('super_admin')->get();

        $url = $owner?->oauthUrl();
        // Without the host: the bell stores it (see Watchdog's DatabaseNotifications).
        $url = $url !== null ? RelativeUrl::of($url) : null;

        foreach ($recipients->filter(fn ($user): bool => $user instanceof User) as $user) {
            try {
                // In the recipient's language: the bell stores rendered text.
                // notifyNow(): Filament's database notification would be queued.
                Locales::using($user->preferredLocale(), fn () => $user->notifyNow(Notification::make()
                    ->title(__('Connect :owner again', ['owner' => $owner?->oauthLabel() ?? __('the :provider connection', ['provider' => $provider->label()])]))
                    ->body(__(':provider no longer accepts this connection. Connect the account again.', ['provider' => $provider->label()]))
                    ->icon('heroicon-o-key')
                    ->iconColor('danger')
                    ->actions($url ? [Action::make('open')->label(__('Open'))->url($url)->markAsRead()] : [])
                    ->toDatabase()));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
