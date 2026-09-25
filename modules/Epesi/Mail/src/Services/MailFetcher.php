<?php

namespace Epesi\Modules\Mail\Services;

use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Models\MailAccountFolder;
use Epesi\Modules\Mail\Services\Imap\MailboxFactory;
use Throwable;

/**
 * Reads new mail from an account's folders into the archive — what the
 * Roundcube archive plugin did when a message was moved into the "CRM
 * Archive" folder, run on a schedule instead of from the webmail UI.
 *
 * - The archive folder: every message in it is archived (the user filed it
 *   there on purpose, from any mail client).
 * - With auto-archive on, the listed folders (INBOX, Sent, ...): a message is
 *   archived only if it involves a known contact or company. These start at
 *   the folder's current end the first time, so turning the option on does
 *   not import years of history.
 *
 * Progress is a per-folder high-water UID, reset when the server changes the
 * folder's UIDVALIDITY; re-reading is harmless because archiving de-duplicates
 * on Message-ID.
 */
class MailFetcher
{
    /** Messages read per folder per run, so one huge folder can't stall the rest. */
    public const BATCH = 200;

    public function __construct(
        protected MailboxFactory $mailboxes,
        protected MailArchiver $archiver,
    ) {}

    /**
     * @return int messages archived
     */
    public function fetch(MailAccount $account): int
    {
        if (! $account->canReceive()) {
            return 0;
        }

        $archived = 0;
        $mailbox = $this->mailboxes->make($account);

        try {
            foreach ($account->foldersToFetch() as $folder => $onlyIfMatched) {
                $archived += $this->fetchFolder($account, $mailbox, $folder, $onlyIfMatched);
            }

            $account->forceFill(['last_fetched_at' => now(), 'last_error' => null])->save();
        } catch (Throwable $e) {
            $account->forceFill(['last_error' => $e->getMessage()])->save();

            throw $e;
        } finally {
            $mailbox->close();
        }

        return $archived;
    }

    protected function fetchFolder(MailAccount $account, Imap\Mailbox $mailbox, string $folder, bool $onlyIfMatched): int
    {
        $validity = $mailbox->open($folder);

        $state = MailAccountFolder::query()->firstOrNew(['account_id' => $account->getKey(), 'folder' => $folder]);
        $fresh = ! $state->exists || $state->uid_validity !== $validity;

        if ($fresh) {
            $state->uid_validity = $validity;
            $state->last_uid = 0;

            if ($onlyIfMatched) {
                $existing = $mailbox->uidsAfter(0);
                $state->last_uid = $existing === [] ? 0 : max($existing);
                $state->save();

                return 0;
            }
        }

        $archived = 0;
        $user = $account->user;

        foreach (array_slice($mailbox->uidsAfter($state->last_uid), 0, self::BATCH) as $uid) {
            $mail = $this->archiver->archive($mailbox->fetchRaw($uid), $user, $account, onlyIfMatched: $onlyIfMatched);

            if ($mail?->wasRecentlyCreated) {
                $archived++;
            }

            $state->last_uid = $uid;
        }

        $state->save();

        return $archived;
    }
}
