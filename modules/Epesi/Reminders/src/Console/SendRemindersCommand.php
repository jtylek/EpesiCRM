<?php

namespace Epesi\Modules\Reminders\Console;

use Epesi\Modules\Reminders\Reminders;
use Illuminate\Console\Command;

class SendRemindersCommand extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Deliver the reminders that are due (bell notifications, and e-mail where asked for)';

    public function handle(): int
    {
        $sent = Reminders::deliverDue();

        $this->line("{$sent} reminder(s) delivered");

        return self::SUCCESS;
    }
}
