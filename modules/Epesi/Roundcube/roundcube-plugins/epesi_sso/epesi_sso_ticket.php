<?php

/**
 * Redeems a Mailbox page's login ticket (Epesi\Modules\Roundcube\Services\
 * TicketIssuer). Plain PHP with no Roundcube dependency, so the test suite
 * checks it against Laravel's own encrypter.
 *
 * The payload is Laravel's Encrypter::encryptString() output for the
 * aes-256-gcm cipher: base64 of a JSON {iv, value, mac, tag} envelope.
 */
final class epesi_sso_ticket
{
    public const TABLE = 'epesi_roundcube_tickets';

    /**
     * @param  callable(string, array<int, mixed>): ?array<string, mixed>  $selectOne  first row or null
     * @param  callable(string, array<int, mixed>): int  $execute  affected rows
     * @return array<string, mixed>|null the login, or null for an unknown, used or expired ticket
     */
    public static function redeem(string $token, string $key, callable $selectOne, callable $execute, ?int $now = null): ?array
    {
        $hash = hash('sha256', $token);

        $row = $selectOne('SELECT payload FROM '.self::TABLE.' WHERE token = ? AND expires_at >= ?', [$hash, $now ?? time()]);

        // Only the request that deletes the row may use it, so two
        // simultaneous loads of the same URL can't both log in.
        if (! $row || $execute('DELETE FROM '.self::TABLE.' WHERE token = ?', [$hash]) !== 1) {
            return null;
        }

        return self::decrypt((string) $row['payload'], $key);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function decrypt(string $payload, string $key): ?array
    {
        $envelope = json_decode((string) base64_decode($payload, true), true);

        if (! is_array($envelope) || ! isset($envelope['iv'], $envelope['value'], $envelope['tag'])) {
            return null;
        }

        $json = openssl_decrypt(
            $envelope['value'],
            'aes-256-gcm',
            $key,
            0,
            (string) base64_decode($envelope['iv'], true),
            (string) base64_decode($envelope['tag'], true),
        );

        $login = $json === false ? null : json_decode($json, true);

        return is_array($login) ? $login : null;
    }

    /**
     * Whether an open session ($_SESSION['epesi']) is this ticket's login:
     * the same Epesi user, the same account, and the same servers and
     * credentials (an account edited since then logs in again). epesi_sso
     * then joins the session instead of logging in again.
     *
     * @param  array<string, mixed>  $login
     */
    public static function same_login(mixed $session, array $login): bool
    {
        return is_array($session)
            && (string) ($session['user_id'] ?? '') === (string) ($login['user_id'] ?? '')
            && (string) ($session['account_id'] ?? '') === (string) ($login['account_id'] ?? '')
            && hash_equals((string) ($session['login_key'] ?? ''), self::login_key($login));
    }

    /**
     * A fingerprint of what the login connects with. An OAuth access token
     * changes on every ticket and is renewed inside the session anyway, so
     * it is left out; a mail password is in.
     *
     * @param  array<string, mixed>  $login
     */
    public static function login_key(array $login): string
    {
        $imap = (array) ($login['imap'] ?? []);

        return hash('sha256', (string) json_encode([
            $imap['host'] ?? null,
            $imap['user'] ?? null,
            empty($login['oauth']) ? ($imap['pass'] ?? null) : 'oauth',
            $login['smtp'] ?? null,
        ]));
    }
}
