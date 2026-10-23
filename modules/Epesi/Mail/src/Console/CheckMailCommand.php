<?php

namespace Epesi\Modules\Mail\Console;

use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Services\UnreadMailCheckDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Refreshes every account's INBOX unread count for the Mail widget. It reads
 * no message and archives nothing: mail is archived only by a person, with
 * the Archive button in Roundcube.
 */
class CheckMailCommand extends Command
{
    protected $signature = 'mail:check {--account= : Only this account id}';

    protected $description = 'Check the unread mail in every IMAP account\'s INBOX for the Mail widget';

    public function handle(UnreadMailCheckDispatcher $dispatcher): int
    {
        $accounts = MailAccount::query()
            ->whereNotNull('imap_host')
            ->when($this->option('account'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        $failed = 0;

        foreach ($accounts as $account) {
            // One broken mailbox must not stop everyone else's; the error is
            // also saved on the account for its owner to see.
            $result = $dispatcher->dispatch($account);

            if ($result === null) {
                $this->line("{$account->email}: unread check queued");

                continue;
            }

            if ($result['count'] === null) {
                $failed++;
                $this->error("{$account->email}: {$result['error']}");
                // Logged as well: the scheduler drops this command's output and
                // logs only its exit code, and the next good check clears last_error.
                Log::warning('mail:check failed for an account', [
                    'account_id' => $account->getKey(),
                    'email' => $account->email,
                    'imap_host' => $account->imap_host,
                    'error' => $result['error'],
                ]);
            } else {
                $this->line("{$account->email}: {$result['count']} unread");
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
