<?php

namespace App\Notifications;

use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The password-reset (and new-account "set your password") e-mail. Laravel's
 * text counts the link's life in minutes ("expire in 2880 minutes"); this one
 * says it in hours when the life is a whole number of them.
 *
 * Bound over Filament's notification in AppServiceProvider, so every panel's
 * reset flow and NewAccountMailer send this one.
 */
class ResetPasswordNotification extends FilamentResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        $expires = $minutes > 0 && $minutes % 60 === 0
            ? trans_choice('This password reset link will expire in :count hour.|This password reset link will expire in :count hours.', intdiv($minutes, 60))
            : __('This password reset link will expire in :count minutes.', ['count' => $minutes]);

        return (new MailMessage)
            ->subject(__('Reset Password Notification'))
            ->line(__('You are receiving this email because we received a password reset request for your account.'))
            ->action(__('Reset Password'), $this->resetUrl($notifiable))
            ->line($expires)
            ->line(__('If you did not request a password reset, no further action is required.'));
    }
}
