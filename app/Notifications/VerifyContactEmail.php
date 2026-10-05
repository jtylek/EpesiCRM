<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The link e-mailed to an address a customer added in the portal: opening it
 * proves the address is theirs (PortalEmails). Sent at once, not queued: epesi
 * runs no queue worker (AI-shared/cron.md).
 */
class VerifyContactEmail extends Notification
{
    public function __construct(protected string $url) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Verify your e-mail address'))
            ->line(__('Someone added this e-mail address to a contact in the customer portal. To confirm it is yours, press the button below.'))
            ->action(__('Verify e-mail address'), $this->url)
            ->line(__('The link works for 24 hours. If you did not ask for this, ignore this e-mail.'));
    }
}
