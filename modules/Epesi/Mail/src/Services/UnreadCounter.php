<?php

namespace Epesi\Modules\Mail\Services;

use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Services\Imap\MailboxFactory;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Unread messages in an account's INBOX — CRM_MailCommon::
 * get_unread_messages(). `mail:check` asks every account's server on a
 * schedule and caches the answer; the Mail widget only reads that cache, so
 * no page waits on a mail server. The cache outlives two scheduled runs, so a
 * late run doesn't leave the widget without a count.
 *
 * Only the INBOX's unread count is asked for (IMAP STATUS UNSEEN): no message
 * is downloaded, and nothing is ever archived from here.
 */
class UnreadCounter
{
    public const CACHE_SECONDS = 600;

    public function __construct(protected MailboxFactory $mailboxes) {}

    /**
     * The cached count, without asking the server: null when there is none
     * (a new or just edited account, or cron not running).
     *
     * @return array{count: int|null, error: string|null}|null count is null when the server could not be asked
     */
    public function cached(MailAccount $account): ?array
    {
        $cached = Cache::get(static::cacheKey($account));

        return is_array($cached) ? $cached : null;
    }

    /**
     * Asks the server, caches the answer and records it on the account
     * (last checked, last error) for its owner to see.
     *
     * @return array{count: int|null, error: string|null}
     */
    public function check(MailAccount $account): array
    {
        $started = now();
        $account->timestamps = false;
        $account->forceFill(['last_check_started_at' => $started])->saveQuietly();
        $account->timestamps = true;

        try {
            $mailbox = $this->mailboxes->make($account);

            try {
                $result = ['count' => $mailbox->unseen('INBOX'), 'error' => null];
            } finally {
                $mailbox->close();
            }
        } catch (Throwable $e) {
            $result = ['count' => null, 'error' => $e->getMessage()];
        }

        Cache::put(static::cacheKey($account), $result, static::CACHE_SECONDS);

        // Without touching updated_at: it is part of the cache key.
        $account->timestamps = false;
        $account->forceFill(['last_checked_at' => now(), 'last_error' => $result['error']])->saveQuietly();
        $account->timestamps = true;

        return $result;
    }

    public static function cacheKey(MailAccount $account): string
    {
        // Keyed on the settings too, so fixing a wrong server or password
        // shows the result straight away instead of a cached error.
        return 'epesi-mail-unseen:'.$account->getKey().':'.$account->updated_at?->timestamp;
    }
}
