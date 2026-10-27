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
            ->with('user')
            ->whereNotNull('imap_host')
            ->when($this->option('account'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        $failed = 0;
        // Mailboxes already counted (or queued) this run: a shared address,
        // set up once per user, is logged in to once. The check records the
        // count on its other accounts too (UnreadCounter::check()).
        $done = [];

        foreach ($accounts as $account) {
            if (isset($done[$account->mailboxKey()])) {
                $this->line("{$account->email}: same mailbox as {$done[$account->mailboxKey()]}, not asked again");

                continue;
            }

            // One broken mailbox must not stop everyone else's; the error is
            // also saved on the account for its owner to see.
            $result = $dispatcher->dispatch($account);

            if ($result === null) {
                $done[$account->mailboxKey()] = "account {$account->getKey()}";
                $this->line("{$account->email}: unread check queued");

                continue;
            }

            if ($result['count'] !== null) {
                $done[$account->mailboxKey()] = "account {$account->getKey()}";
            }

            if ($result['count'] === null) {
                $failed++;
                $this->error("{$account->email}: {$result['error']}");
                // Logged as well: the scheduler drops this command's output and
                // logs only its exit code, and the next good check clears last_error.
                Log::warning('mail:check failed for an account', [
                    'account_id' => $account->getKey(),
                    // A mailbox such as a shared help address is an account per user.
                    'user_id' => $account->user_id,
                    'user' => $account->user?->email,
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
