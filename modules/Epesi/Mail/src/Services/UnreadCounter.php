<?php

namespace Epesi\Modules\Mail\Services;

use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Services\Imap\MailboxFactory;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Unread messages in an account's INBOX — CRM_MailCommon::
 * get_unread_messages(), including its three-minute cache, so a dashboard
 * that polls or many open tabs don't hammer the mail server.
 */
class UnreadCounter
{
    public const CACHE_SECONDS = 180;

    public function __construct(protected MailboxFactory $mailboxes) {}

    /**
     * @return array{count: int|null, error: string|null} count is null when the server could not be asked
     */
    public function forAccount(MailAccount $account, bool $refresh = false): array
    {
        $key = static::cacheKey($account);

        if ($refresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, static::CACHE_SECONDS, function () use ($account): array {
            try {
                $mailbox = $this->mailboxes->make($account);

                try {
                    return ['count' => $mailbox->unseen('INBOX'), 'error' => null];
                } finally {
                    $mailbox->close();
                }
            } catch (Throwable $e) {
                return ['count' => null, 'error' => $e->getMessage()];
            }
        });
    }

    public static function cacheKey(MailAccount $account): string
    {
        // Keyed on the settings too, so fixing a wrong server or password
        // shows the result straight away instead of a cached error.
        return 'epesi-mail-unseen:'.$account->getKey().':'.$account->updated_at?->timestamp;
    }
}
