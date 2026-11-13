<?php

namespace Epesi\Modules\Mail\Services\Imap;

/**
 * Which of a mailbox's folders holds spam: the one the server marks with the
 * SPECIAL-USE \Junk flag (Gmail's "[Gmail]/Spam", Microsoft 365's "Junk
 * Email", most Dovecot servers), or else one named like a spam folder.
 *
 * Roundcube's epesi_sso plugin (epesi_sso_folders) does the same search by
 * name at login, for an account whose spam folder is left empty; NAMES must
 * match its JUNK_NAMES (a test checks it).
 */
class JunkFolder
{
    /** Spam folder names, lower case, the most likely first. */
    public const NAMES = ['junk', 'spam', 'junk e-mail', 'junk email', 'junk-e-mail', 'bulk mail'];

    /**
     * @param  array<string, array{delimiter?: string|null, flags?: array<int, string>}>  $folders  an IMAP LIST: full name => its delimiter and flags
     */
    public static function pick(array $folders): ?string
    {
        foreach ($folders as $name => $item) {
            foreach ((array) ($item['flags'] ?? []) as $flag) {
                if (strcasecmp((string) $flag, '\\Junk') === 0) {
                    return (string) $name;
                }
            }
        }

        $top = [];
        $underInbox = [];

        foreach ($folders as $name => $item) {
            $name = (string) $name;
            $delimiter = (string) ($item['delimiter'] ?? '');
            $parts = $delimiter === '' ? [$name] : explode($delimiter, $name);

            if (count($parts) === 1) {
                $top[strtolower($name)] ??= $name;
            } elseif (count($parts) === 2 && strtoupper($parts[0]) === 'INBOX') {
                $underInbox[strtolower($parts[1])] ??= $name;
            }
        }

        foreach (self::NAMES as $candidate) {
            if (isset($top[$candidate])) {
                return $top[$candidate];
            }

            if (isset($underInbox[$candidate])) {
                return $underInbox[$candidate];
            }
        }

        return null;
    }
}
