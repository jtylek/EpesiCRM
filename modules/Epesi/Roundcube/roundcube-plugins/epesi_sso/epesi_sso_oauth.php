<?php

require_once __DIR__.'/epesi_sso_ticket.php';

/**
 * A new access token for an OAuth (XOAUTH2) session, from Epesi's token
 * endpoint (Epesi\Modules\Roundcube\Http\Controllers\OAuthTokenController).
 * Plain PHP with no Roundcube dependency, so the test suite runs it against
 * the real endpoint.
 */
final class epesi_sso_oauth
{
    /**
     * Seconds before its expiry a session's token is replaced. Below Epesi's
     * epesi-oauth.refresh_ahead (300), so what Epesi hands out is never due
     * again at once.
     */
    public const MARGIN = 120;

    /**
     * @param  array<string, mixed>  $oauth  the session's `oauth` part of the login
     */
    public static function due(array $oauth, int $now): bool
    {
        // No expiry: the provider didn't give one, nor does Epesi renew it.
        return isset($oauth['expires_at']) && (int) $oauth['expires_at'] - self::MARGIN <= $now;
    }

    /**
     * The new token, or an error: `refused` when the grant is no good any
     * more, `unavailable` when it may work if asked again later.
     *
     * @param  callable(string, array<string, string>): array{0: int, 1: string}  $post  status and body of a form POST
     * @return array{access_token: string, expires_at: int|null}|array{error: string}
     */
    public static function fetch(string $url, string $grant, string $key, callable $post): array
    {
        try {
            [$status, $body] = $post($url, ['grant' => $grant]);
        } catch (Throwable) {
            return ['error' => 'unavailable'];
        }

        $answer = json_decode((string) $body, true);

        if ($status === 200 && is_array($answer) && is_string($answer['payload'] ?? null)) {
            $token = epesi_sso_ticket::decrypt($answer['payload'], $key);

            if (is_array($token) && is_string($token['access_token'] ?? null) && $token['access_token'] !== '') {
                return [
                    'access_token' => $token['access_token'],
                    'expires_at' => isset($token['expires_at']) ? (int) $token['expires_at'] : null,
                ];
            }

            return ['error' => 'unavailable'];
        }

        return ['error' => $status === 403 ? 'refused' : 'unavailable'];
    }
}
