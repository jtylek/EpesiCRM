<?php

namespace Epesi\Modules\Mail\Console;

use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Services\MailFetcher;
use Illuminate\Console\Command;
use Throwable;

class FetchMailCommand extends Command
{
    protected $signature = 'mail:fetch {--account= : Only this account id}';

    protected $description = 'Archive new mail from every IMAP account (archive folder, plus auto-archive folders)';

    public function handle(MailFetcher $fetcher): int
    {
        $accounts = MailAccount::query()
            ->whereNotNull('imap_host')
            ->when($this->option('account'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        $failed = 0;

        foreach ($accounts as $account) {
            try {
                $count = $fetcher->fetch($account);
                $this->line("{$account->email}: {$count} archived");
            } catch (Throwable $e) {
                // One broken mailbox must not stop everyone else's; the error
                // is also saved on the account for its owner to see.
                $failed++;
                $this->error("{$account->email}: {$e->getMessage()}");
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
