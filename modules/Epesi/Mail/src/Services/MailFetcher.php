<?php

namespace Epesi\Modules\Mail\Services;

use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Services\Imap\MailboxFactory;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Archives the messages a person just picked with Roundcube's Archive button
 * (or its "Archive this message after sending" toggle) — exactly those, read
 * from the mail server into the archive through MailArchiver (attachments go
 * to file storage, which de-duplicates them).
 *
 * Nothing else is ever read, and nothing is archived on a schedule or without
 * that click (AI-shared/Mail-archiving.md).
 */
class MailFetcher
{
    public function __construct(
        protected MailboxFactory $mailboxes,
        protected MailArchiver $archiver,
    ) {}

    /** At most this many messages per click: each is read and stored while the user waits. */
    public const MAX_PER_CLICK = 200;

    /**
     * The Archive button: read each clicked message from the folder the user
     * is in, archive it, then move the ones archived into the archive folder,
     * so they leave the inbox but stay on the mail server. A message that
     * could not be archived is not moved.
     *
     * @param  array<int, int>  $uids
     * @return array{mails: Collection<int, Mail>, moveError: string|null}
     */
    public function archiveFrom(MailAccount $account, string $folder, array $uids): array
    {
        $uids = array_slice(array_values(array_unique(array_filter(array_map('intval', $uids)))), 0, self::MAX_PER_CLICK);
        $result = ['mails' => new Collection, 'moveError' => null];

        if ($uids === [] || ! $account->canReceive()) {
            return $result;
        }

        $mailbox = $this->mailboxes->make($account);
        $archived = [];

        try {
            $mailbox->open($folder);

            foreach ($uids as $uid) {
                $result['mails']->push($this->archiver->archive($mailbox->fetchRaw($uid), $account->user, $account));
                $archived[] = $uid;
            }

            if (filled($account->archive_folder) && $folder !== $account->archive_folder) {
                try {
                    $mailbox->move($folder, $archived, $account->archive_folder);
                } catch (Throwable $e) {
                    // Archived all the same: only the mail server's copy stayed put.
                    $result['moveError'] = $e->getMessage();
                }
            }
        } finally {
            $mailbox->close();
        }

        return $result;
    }

    /**
     * A sent copy Roundcube already saved into the archive folder ("Archive
     * this message after sending"): found there by Message-ID and archived.
     *
     * @param  array<int, string>  $messageIds  with or without angle brackets
     * @return Collection<int, Mail> the archived messages (an existing copy for a duplicate)
     */
    public function archiveFiled(MailAccount $account, array $messageIds): Collection
    {
        $messageIds = collect($messageIds)
            ->map(fn ($id): string => trim((string) $id, " \t<>"))
            ->filter()
            ->unique()
            ->values();

        if ($messageIds->isEmpty() || ! $account->canReceive() || blank($account->archive_folder)) {
            return new Collection;
        }

        $mailbox = $this->mailboxes->make($account);
        $archived = new Collection;

        try {
            $mailbox->open($account->archive_folder);

            foreach ($messageIds as $messageId) {
                // One copy is enough: the archive keeps one per Message-ID.
                $uid = $mailbox->uidsWithMessageId($messageId)[0] ?? null;

                if ($uid !== null) {
                    $archived->push($this->archiver->archive($mailbox->fetchRaw($uid), $account->user, $account));
                }
            }
        } finally {
            $mailbox->close();
        }

        return $archived;
    }
}
