<?php

namespace Epesi\Modules\Roundcube\Services;

use App\Models\User;
use Composer\CaBundle\CaBundle;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\OAuth\Exceptions\OAuthException;
use Epesi\Modules\OAuth\Services\OAuthTokens;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Access tokens for Roundcube sessions logged in to an OAuth (XOAUTH2) mail
 * account. The login ticket carries the first token; a Roundcube session
 * outlives it, so the ticket also carries a grant: a random secret epesi_sso
 * keeps (encrypted) in the Roundcube session and posts to the token endpoint
 * (Http\Controllers\OAuthTokenController) for each new token. Epesi keeps
 * the tokens and renews them (OAuthTokens, under its lock); Roundcube never
 * sees a refresh token or the app's secret.
 */
class OAuthGrants
{
    public const TABLE = 'epesi_roundcube_oauth_grants';

    public function __construct(protected OAuthTokens $tokens) {}

    /**
     * $account's valid access token and when it expires (null: the provider
     * didn't say).
     *
     * @return array{access_token: string, expires_at: int|null}
     *
     * @throws OAuthException not connected, refused, or the provider unreachable
     */
    public function accessToken(MailAccount $account): array
    {
        $access = $this->tokens->accessToken($account, $account->oauthProvider());
        $stored = $account->oauthToken($account->oauthProvider());

        // Another process may have renewed it in between: then the expiry
        // read isn't this token's, which is valid for refresh_ahead at least.
        $expires = $stored && $stored->access_token === $access
            ? $stored->expires_at?->getTimestamp()
            : now()->addSeconds((int) config('epesi-oauth.refresh_ahead', 300))->getTimestamp();

        return ['access_token' => $access, 'expires_at' => $expires];
    }

    /** @return string the grant's secret, for the login ticket only */
    public function issue(MailAccount $account, User $user): string
    {
        DB::table(self::TABLE)->where('expires_at', '<', now()->getTimestamp())->delete();

        $secret = Str::random(48);

        DB::table(self::TABLE)->insert([
            'token' => hash('sha256', $secret),
            'user_id' => $user->getKey(),
            'account_id' => $account->getKey(),
            'expires_at' => $this->expiry(),
        ]);

        return $secret;
    }

    /**
     * A valid access token for the grant's account, or null when the grant
     * is unknown or expired, or its user or account may no longer sign in
     * this way. Then the Roundcube session is ended, and the Mailbox page
     * logs in again with a ticket, which says what is wrong.
     *
     * @return array{access_token: string, expires_at: int|null}|null
     *
     * @throws OAuthException the provider couldn't renew it this time (not a refusal)
     */
    public function redeem(string $secret): ?array
    {
        $hash = hash('sha256', $secret);
        $grant = DB::table(self::TABLE)->where('token', $hash)->where('expires_at', '>=', now()->getTimestamp())->first();

        if (! $grant) {
            return null;
        }

        $account = MailAccount::query()->whereKey($grant->account_id)->where('user_id', $grant->user_id)->first();
        $user = User::query()->find($grant->user_id);

        if (! $account?->usesOAuth() || ! $user?->active || ! $user->hasAnyRole(User::CRM_ROLES)) {
            DB::table(self::TABLE)->where('token', $hash)->delete();

            return null;
        }

        try {
            $token = $this->accessToken($account);
        } catch (OAuthException $e) {
            $stored = $account->oauthToken($account->oauthProvider());

            // Signing in again is the only way on: say so through a new ticket.
            if (! $stored || $stored->needsReconnect()) {
                DB::table(self::TABLE)->where('token', $hash)->delete();

                return null;
            }

            throw $e;
        }

        DB::table(self::TABLE)->where('token', $hash)->update(['expires_at' => $this->expiry()]);

        return $token;
    }

    /**
     * Where epesi_sso asks for a new token: Epesi's own route, unless the
     * server reaches itself at another address (ROUNDCUBE_INTERNAL_URL).
     */
    public function url(): string
    {
        $base = rtrim((string) config('epesi-roundcube.internal_url'), '/');

        return $base === ''
            ? route('epesi.roundcube.oauth-token')
            : $base.'/'.ltrim(route('epesi.roundcube.oauth-token', absolute: false), '/');
    }

    /** The CA certificates epesi_sso verifies the token endpoint with, as the installer does its download. */
    public function caBundle(): string
    {
        return CaBundle::getSystemCaRootBundlePath();
    }

    /**
     * As long as the Roundcube session could be idle (Laravel's session
     * lifetime, which Roundcube's follows), at least the hour a token lasts;
     * every use moves it forward.
     */
    protected function expiry(): int
    {
        return now()->addMinutes(max((int) config('session.lifetime'), 60))->getTimestamp();
    }
}
