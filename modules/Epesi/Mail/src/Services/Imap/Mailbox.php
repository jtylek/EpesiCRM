<?php

namespace Epesi\Modules\Mail\Services\Imap;

/**
 * The little of IMAP the fetcher needs. An interface so the fetcher can be
 * tested without a mail server, and so the IMAP library stays swappable.
 */
interface Mailbox
{
    /**
     * @return array<int, string> every folder's full path
     */
    public function folders(): array;

    /**
     * Open $folder read-only (so fetching never marks mail as read) and
     * return its UIDVALIDITY.
     */
    public function open(string $folder): int;

    /**
     * UIDs above $uid in the open folder, ascending.
     *
     * @return array<int, int>
     */
    public function uidsAfter(int $uid): array;

    /** The complete raw message. */
    public function fetchRaw(int $uid): string;

    /**
     * Store a raw message in $folder, marked as read — how a sent message
     * lands in the user's Sent folder.
     */
    public function append(string $folder, string $raw): void;

    /** Unread messages in $folder (IMAP STATUS UNSEEN), without opening it. */
    public function unseen(string $folder = 'INBOX'): int;

    public function close(): void;
}
