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
}
