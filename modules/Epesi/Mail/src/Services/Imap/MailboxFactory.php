<?php

namespace Epesi\Modules\Mail\Services\Imap;

use Epesi\Modules\Mail\Models\MailAccount;

/** Bound in the container, so tests can hand the fetcher a fake server. */
class MailboxFactory
{
    public function make(MailAccount $account): Mailbox
    {
        return new WebklexMailbox($account);
    }
}
