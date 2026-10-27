<?php

namespace Epesi\Modules\Mail\Jobs;

use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Services\UnreadCounter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * One account's unread check. Unique per account: every open dashboard's widget poll queues
 * the accounts with no count yet (and `mail:check`, run by hand, every account), so without
 * this a drain that lags (or a worker that was enabled but never set up) piles up identical
 * jobs. While one is waiting or running, a second dispatch for the account is dropped.
 */
class UnreadMailCheck implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** Longer than the IMAP socket timeout (60), so the socket gives up first. */
    public int $timeout = 90;

    /** The lock outlives the worst case: three attempts of the timeout plus the two backoffs. */
    public int $uniqueFor = 450;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $accountId) {}

    public function uniqueId(): string
    {
        return (string) $this->accountId;
    }

    public function handle(UnreadCounter $counter): void
    {
        $account = MailAccount::query()
            ->whereKey($this->accountId)
            ->whereNotNull('imap_host')
            ->first();

        if ($account !== null) {
            $counter->check($account);
        }
    }

    public function queue(): string
    {
        return (string) config('epesi-background.queue', 'epesi-background');
    }
}
