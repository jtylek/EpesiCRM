<?php

namespace Epesi\Modules\Reminders\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The e-mail copy of a reminder — cron2()'s Base_MailCommon::send($mail,
 * 'Alert!', …). It goes through the application's own mailer (config/mail),
 * not a user's Mail-module SMTP account: the system is the sender.
 */
class ReminderMail extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject(__('Reminder: :record', ['record' => $this->title]))->line($this->title);

        foreach (array_filter(explode("\n", $this->body)) as $line) {
            $mail->line($line);
        }

        return $this->url ? $mail->action(__('Open'), $this->url) : $mail;
    }
}
