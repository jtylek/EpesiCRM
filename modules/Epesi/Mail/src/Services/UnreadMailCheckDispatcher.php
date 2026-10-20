<?php

namespace Epesi\Modules\Mail\Services;

use App\Support\Background\BackgroundWorkerHealth;
use Epesi\Modules\Mail\Jobs\UnreadMailCheck;
use Epesi\Modules\Mail\Models\MailAccount;
use Throwable;

class UnreadMailCheckDispatcher
{
    public function __construct(
        protected UnreadCounter $counter,
        protected BackgroundWorkerHealth $worker,
    ) {}

    /** @return array{count: int|null, error: string|null}|null */
    public function dispatch(MailAccount $account): ?array
    {
        if (! $this->worker->enabled()) {
            return $this->counter->check($account);
        }

        try {
            UnreadMailCheck::dispatch($account->getKey())
                ->onConnection('epesi-background')
                ->onQueue((string) config('epesi-background.queue', 'epesi-background'));
        } catch (Throwable $e) {
            // The queue tables are missing or unreachable: a check that waits on nobody is worse
            // than the synchronous one this replaces.
            report($e);

            return $this->counter->check($account);
        }

        return null;
    }
}
