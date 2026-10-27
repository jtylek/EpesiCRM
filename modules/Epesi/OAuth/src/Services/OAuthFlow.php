<?php

namespace Epesi\Modules\OAuth\Services;

use App\Models\User;
use Epesi\Modules\OAuth\Contracts\OAuthOwner;
use Epesi\Modules\OAuth\Exceptions\OAuthException;
use Epesi\Modules\OAuth\Models\OAuthClient;
use Epesi\Modules\OAuth\Models\OAuthToken;
use Epesi\Modules\OAuth\OAuthProviders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The authorization code flow with PKCE (RFC 7636, S256).
 *
 * start() is called by a "Connect" action on a page: it keeps what it needs
 * in the session under a random `state` (the owner, who started it, the PKCE
 * verifier, the scopes and environment, where to come back) and returns the
 * provider's sign-in address. complete() is the callback: only a state this
 * very session started is accepted, once, within
 * config('epesi-oauth.flow_lifetime') minutes, by the user who started it and
 * who may still connect the owner. The redirect URI is the shared
 * `oauth/callback` route unless the provider declares a callback path of its
 * own (OAuthProviders::redirectUri()).
 */
class OAuthFlow
{
    public const SESSION_KEY = 'epesi_oauth_flows';

    public function __construct(protected OAuthTokens $tokens) {}

    /**
     * The provider's sign-in address for connecting $owner. Connecting an
     * owner that already has a token from $provider replaces it in the same
     * row: that is how a module asks for more $scopes.
     *
     * @param  list<string>|null  $scopes  null for the provider's default scopes
     *
     * @throws OAuthException the provider is unknown or its app not set up
     * @throws AuthorizationException $user may not connect $owner
     */
    public function start(Model&OAuthOwner $owner, string $providerKey, User $user, ?string $returnUrl = null, ?array $scopes = null, string $environment = OAuthClient::PRODUCTION): string
    {
        $provider = OAuthProviders::get($providerKey);

        if (! isset($provider->environments()[$environment])) {
            throw OAuthException::unknownProvider($providerKey.' ('.$environment.')');
        }

        $client = $this->tokens->client($provider, $environment);

        if (! $owner->exists || ! $owner->mayConnectOAuth($user)) {
            throw new AuthorizationException;
        }

        $scopes = array_values($scopes ?? $provider->scopes());
        $state = Str::random(40);
        $verifier = Str::random(96);

        $flows = $this->pendingFlows();
        $flows[$state] = [
            'provider' => $provider->key(),
            'environment' => $environment,
            'scopes' => $scopes,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'user_id' => $user->getKey(),
            'verifier' => $verifier,
            'return_url' => $this->safeReturnUrl($returnUrl),
            'started_at' => now()->getTimestamp(),
        ];

        // A few at most: each unfinished connect leaves one behind.
        session()->put(self::SESSION_KEY, array_slice($flows, -5, preserve_keys: true));

        $query = [
            'client_id' => $client->client_id,
            'response_type' => 'code',
            'redirect_uri' => OAuthProviders::redirectUri($provider),
            'scope' => implode($provider->scopeSeparator(), $scopes),
            'state' => $state,
            'code_challenge' => static::challenge($verifier),
            'code_challenge_method' => 'S256',
            ...array_filter(['login_hint' => $owner->oauthLoginHint()]),
            ...$provider->authorizeParameters(),
        ];

        return $provider->authorizeUrl($client).'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * The callback's work: the state checked, the code exchanged and stored.
     * Returns the token (null on failure, with the reason) and where to send
     * the browser back to.
     *
     * @return array{token: OAuthToken|null, return_url: string, error: string|null}
     *
     * @throws AuthorizationException a state this session didn't start, or a user who may not connect the owner
     */
    public function complete(Request $request, User $user): array
    {
        $state = (string) $request->query('state', '');
        $flows = $this->pendingFlows();
        $flow = $state !== '' ? ($flows[$state] ?? null) : null;
        $unknown = __('This connection was not started in this browser, or it has expired. Start connecting again.');

        if ($flow === null) {
            throw new AuthorizationException($unknown);
        }

        // Once only, whatever happens next.
        unset($flows[$state]);
        session()->put(self::SESSION_KEY, $flows);

        if ((int) $flow['user_id'] !== (int) $user->getKey()) {
            throw new AuthorizationException(__('This connection was started by someone else.'));
        }

        $returnUrl = $flow['return_url'];
        $owner = $this->owner($flow);

        if (! $owner || ! $owner->mayConnectOAuth($user)) {
            throw new AuthorizationException;
        }

        $provider = OAuthProviders::get($flow['provider']);

        // A provider's own callback path answers for that provider only.
        $routeProvider = $request->route()?->defaults['oauthProvider'] ?? null;

        if ($routeProvider !== null && $routeProvider !== $provider->key()) {
            throw new AuthorizationException($unknown);
        }

        // Declined at the provider, or the provider's own error.
        if ($request->filled('error')) {
            $error = OAuthException::fromProvider($provider->label(), (string) $request->query('error'), $request->query('error_description') ? (string) $request->query('error_description') : null, 400);

            return ['token' => null, 'return_url' => $returnUrl, 'error' => $error->getMessage()];
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            return ['token' => null, 'return_url' => $returnUrl, 'error' => __('The provider sent no authorization code.')];
        }

        try {
            $client = $this->tokens->client($provider, $flow['environment']);

            $response = $this->tokens->post($provider, $client, $provider->tokenUrl($client), [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => OAuthProviders::redirectUri($provider),
                'code_verifier' => $flow['verifier'],
            ]);

            if ($response->failed()) {
                $error = $response->json('error');

                throw OAuthException::fromProvider($provider->label(), is_string($error) ? $error : null, $this->tokens->description($response), $response->status());
            }

            $token = $this->tokens->store($owner, $provider, $response->json() ?? [], $user, $flow['environment'], $flow['scopes']);
        } catch (OAuthException $e) {
            return ['token' => null, 'return_url' => $returnUrl, 'error' => $e->getMessage()];
        }

        return ['token' => $token, 'return_url' => $returnUrl, 'error' => null];
    }

    /** The S256 code challenge: base64url(sha256(verifier)), unpadded. */
    public static function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /**
     * @param  array<string, mixed>  $flow
     */
    protected function owner(array $flow): (Model&OAuthOwner)|null
    {
        $class = Relation::getMorphedModel((string) $flow['owner_type']);

        if (! $class || ! is_subclass_of($class, OAuthOwner::class)) {
            return null;
        }

        $owner = $class::query()->find($flow['owner_id']);

        return $owner instanceof OAuthOwner ? $owner : null;
    }

    /**
     * Unexpired flows of this session.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function pendingFlows(): array
    {
        $oldest = now()->subMinutes((int) config('epesi-oauth.flow_lifetime', 15))->getTimestamp();

        return array_filter(
            (array) session()->get(self::SESSION_KEY, []),
            fn ($flow): bool => is_array($flow) && (int) ($flow['started_at'] ?? 0) >= $oldest,
        );
    }

    /** Only an address on this installation: the callback must not become an open redirect. */
    protected function safeReturnUrl(?string $url): string
    {
        $home = url('/');
        $url = (string) $url;

        if ($url === '') {
            return $home;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if ($host === null && str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_contains($url, '\\')) {
            return url($url);
        }

        return is_string($host) && strcasecmp($host, (string) parse_url($home, PHP_URL_HOST)) === 0 ? $url : $home;
    }
}
