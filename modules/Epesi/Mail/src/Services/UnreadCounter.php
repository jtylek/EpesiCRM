<?php

namespace Epesi\Modules\Mail\Services;

use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Services\Imap\MailboxFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Unread messages in an account's INBOX — CRM_MailCommon::
 * get_unread_messages(). The Mail widget renders from the cache and, while it
 * is on screen, asks for the accounts whose count has expired in a request of
 * its own, so no page waits on a mail server and nobody's mailbox is asked
 * while nobody is looking. Nothing is scheduled: `mail:check` is for checking
 * by hand.
 *
 * Only the INBOX's unread count is asked for (IMAP STATUS UNSEEN): no message
 * is downloaded, and nothing is ever archived from here.
 */
class UnreadCounter
{
    /** With the widget's 3-minute poll, a count is at most about 6 minutes old. */
    public const CACHE_SECONDS = 300;

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
     * (last checked, last error) for its owner to see. A count is also
     * recorded on the other accounts of the same mailbox, so a shared
     * address is logged in to once, not once per user. An error is not:
     * another user's credentials may still work.
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
            $result = ['count' => null, 'error' => static::reason($e)];
        }

        $this->record($account, $result);

        if ($result['count'] !== null) {
            $this->sameMailbox($account)->each(fn (MailAccount $other) => $this->record($other, $result));
        }

        return $result;
    }

    /**
     * The other accounts that log in to $account's mailbox.
     *
     * @return Collection<int, MailAccount>
     */
    public function sameMailbox(MailAccount $account): Collection
    {
        $key = $account->mailboxKey();

        return MailAccount::query()
            ->whereNotNull('imap_host')
            ->whereKeyNot($account->getKey())
            ->get()
            ->filter(fn (MailAccount $other): bool => $other->mailboxKey() === $key)
            ->values();
    }

    /**
     * The error with the causes it wraps. webklex reports anything that goes
     * wrong during LOGIN as "failed to authenticate" and keeps what actually
     * happened ("empty response": the server closed the connection or
     * didn't answer in time) as the previous exception. A refusal is not
     * wrapped: it arrives as the server's own reply ("NO [...] ...").
     */
    public static function reason(Throwable $e): string
    {
        $messages = [];

        for ($cause = $e; $cause; $cause = $cause->getPrevious()) {
            $message = trim($cause->getMessage());

            if ($message !== '' && ! in_array($message, $messages, true)) {
                $messages[] = $message;
            }
        }

        return Str::limit(implode(': ', $messages) ?: $e::class, 1000);
    }

    /** @param  array{count: int|null, error: string|null}  $result */
    private function record(MailAccount $account, array $result): void
    {
        Cache::put(static::cacheKey($account), $result, static::CACHE_SECONDS);

        // Without touching updated_at: it is part of the cache key.
        $account->timestamps = false;
        $account->forceFill(['last_checked_at' => now(), 'last_error' => $result['error']])->saveQuietly();
        $account->timestamps = true;
    }

    public static function cacheKey(MailAccount $account): string
    {
        // Keyed on the settings too, so fixing a wrong server or password
        // shows the result straight away instead of a cached error.
        return 'epesi-mail-unseen:'.$account->getKey().':'.$account->updated_at?->timestamp;
    }
}
