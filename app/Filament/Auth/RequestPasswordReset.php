<?php

namespace App\Filament\Auth;

use App\Support\Demo;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * The "Forgot password?" page behind the login page of every panel (legacy
 * Epesi's "Recover password"): it e-mails a link to a page where the user
 * sets a new password. Filament's page, except that:
 *
 * - the e-mail goes out straight away. Filament queues it, and on an install
 *   whose .env still says QUEUE_CONNECTION=database it would never leave:
 *   epesi runs no queue worker (AI-shared/cron.md);
 * - an address with no account, or one asked for a minute ago, gets the same
 *   answer as any other, so the page can't tell anyone who has an account;
 * - an e-mail that can't be sent says so, as legacy Epesi did, rather than
 *   ending in an error page;
 * - the answer renders inline on the page (a toast is too easy to miss on a
 *   page whose only other content is a one-field form).
 *
 * Demo mode has no password reset: the page answers 404.
 */
class RequestPasswordReset extends BaseRequestPasswordReset
{
    public ?string $statusHeading = null;

    public ?string $statusDescription = null;

    public ?string $statusColor = null;

    public function mount(): void
    {
        abort_if(Demo::enabled(), 404);

        parent::mount();
    }

    public function request(): void
    {
        app()->resolving(ResetPasswordNotification::class, fn (ResetPasswordNotification $notification) => $notification->onConnection('sync'));

        try {
            parent::request();
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            $this->showStatus(
                Notification::make()
                    ->title(__('The e-mail could not be sent. Ask your administrator to reset your password.'))
                    ->danger()
            );
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                RenderHook::make(PanelsRenderHook::AUTH_PASSWORD_RESET_REQUEST_FORM_BEFORE),
                Callout::make(fn (): ?string => $this->statusHeading)
                    ->description(fn (): ?string => $this->statusDescription)
                    ->status(fn (): ?string => $this->statusColor)
                    ->visible(fn (): bool => filled($this->statusHeading)),
                $this->getFormContentComponent(),
                RenderHook::make(PanelsRenderHook::AUTH_PASSWORD_RESET_REQUEST_FORM_AFTER),
            ]);
    }

    protected function getSentNotification(string $status): ?Notification
    {
        $this->showStatus(
            parent::getSentNotification($status)
                ?->title(__('If :email belongs to an account, we have sent it a link to reset the password.', ['email' => $this->data['email'] ?? '']))
        );

        return null;
    }

    protected function getFailureNotification(string $status): ?Notification
    {
        if (! in_array($status, [Password::INVALID_USER, Password::RESET_THROTTLED], true)) {
            $this->showStatus(parent::getFailureNotification($status));

            return null;
        }

        $this->getSentNotification(Password::RESET_LINK_SENT); // same answer as after a real send
        $this->form->fill(); // as after a link that was sent

        return null;
    }

    protected function getRateLimitedNotification(TooManyRequestsException $exception): ?Notification
    {
        $this->showStatus(parent::getRateLimitedNotification($exception));

        return null;
    }

    protected function showStatus(?Notification $notification): void
    {
        $this->statusHeading = $notification?->getTitle();
        $this->statusDescription = $notification?->getBody();
        $this->statusColor = $notification?->getStatus();
    }
}
