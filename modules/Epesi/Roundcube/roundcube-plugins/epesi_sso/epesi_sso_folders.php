<?php

/**
 * Finds a mailbox's spam folder by name, for a server that doesn't mark it
 * with the SPECIAL-USE \Junk flag (older Courier, some cPanel hosts).
 * Roundcube reads that flag at login (Gmail's "[Gmail]/Spam", Microsoft's
 * "Junk Email"); without it, it falls back to "Junk", which may not exist,
 * and Mark as junk then fails. Plain PHP with no Roundcube dependency, so
 * the test suite runs it. Epesi's own search (Mail's JunkFolder, behind the
 * mail account's "Detect") uses the same names; a test keeps them equal.
 */
final class epesi_sso_folders
{
    /** Spam folder names, lower case, the most likely first. */
    public const JUNK_NAMES = ['junk', 'spam', 'junk e-mail', 'junk email', 'junk-e-mail', 'bulk mail'];

    /**
     * The spam folder among the mailbox's folders: one of JUNK_NAMES (any
     * case), at the top level or right under INBOX ("INBOX.spam" on Courier).
     * The earlier name wins; for the same name, a top-level folder wins over
     * one under INBOX. A folder deeper down ("Clients/Spam") never counts.
     * Null when none fits.
     *
     * @param  list<string>  $folders  full IMAP folder names
     * @param  string  $delimiter  the server's hierarchy delimiter ('' on a flat server)
     */
    public static function junk_folder(array $folders, string $delimiter): ?string
    {
        $top = [];
        $underInbox = [];

        foreach ($folders as $folder) {
            $folder = (string) $folder;
            $parts = $delimiter === '' ? [$folder] : explode($delimiter, $folder);

            if (count($parts) === 1) {
                $top[strtolower($folder)] ??= $folder;
            } elseif (count($parts) === 2 && strtoupper($parts[0]) === 'INBOX') {
                $underInbox[strtolower($parts[1])] ??= $folder;
            }
        }

        foreach (self::JUNK_NAMES as $name) {
            if (isset($top[$name])) {
                return $top[$name];
            }

            if (isset($underInbox[$name])) {
                return $underInbox[$name];
            }
        }

        return null;
    }
}
