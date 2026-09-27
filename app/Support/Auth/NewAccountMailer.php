<?php

namespace App\Support\Auth;

use App\Models\User;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Password;
use SensitiveParameter;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Epesi's add_user() e-mailed a new account its generated password. Here the
 * account gets a link to choose its own instead: the same e-mail "Forgot
 * password?" sends (App\Filament\Auth\RequestPasswordReset), so no password
 * ever travels by e-mail. Used when an administrator creates a user and
 * leaves the password blank, and by Reset Password left blank.
 */
class NewAccountMailer
{
    /** The link was e-mailed. */
    public const SENT = 'sent';

    /** One was sent a moment ago (the password broker's throttle): another has to wait. */
    public const THROTTLED = 'throttled';

    /** The mail server didn't take it. */
    public const FAILED = 'failed';

    /**
     * Whether a link would work: the reset page refuses an account that can't
     * open any panel (no role, a role with nowhere to sign in, or
     * deactivated), and "Forgot password?" sends nothing to one, so a link
     * would only fail.
     */
    public static function canReceiveLink(User $user): bool
    {
        return self::panelFor($user) !== null;
    }

    /**
     * Why no link can be sent to this account, in a sentence for the
     * administrator, or null when one can: deactivated, no role, or a role
     * with nowhere to sign in yet.
     */
    public static function whyNoLink(User $user): ?string
    {
        if (self::canReceiveLink($user)) {
            return null;
        }

        if (! $user->active) {
            return __('The account is deactivated.');
        }

        $roles = $user->getRoleNames();

        if ($roles->isEmpty()) {
            return __('They have no role.');
        }

        return __('Their role (:roles) has nowhere to sign in yet.', [
            'roles' => $roles->implode(', '),
        ]);
    }

    /**
     * The panel this account's link should open, tried in the order a
     * person is likeliest to use: the main CRM panel, then the customer
     * portal. Null when it can open neither.
     */
    protected static function panelFor(User $user): ?Panel
    {
        foreach (['main', 'portal'] as $id) {
            $panel = Filament::getPanel($id);

            if ($user->canAccessPanel($panel)) {
                return $panel;
            }
        }

        return null;
    }

    /**
     * Sent straight away: Filament queues this notification, and epesi runs
     * no queue worker (AI-shared/cron.md). Returns SENT, THROTTLED or FAILED
     * (the mail server is down or misconfigured); a link that went out is
     * written to the user's History.
     *
     * The link opens whichever panel this account can sign into (panelFor()),
     * not Administration, where the administrator sending it is working.
     */
    public static function sendSetPasswordLink(User $user): string
    {
        $panel = self::panelFor($user);

        if ($panel === null) {
            return self::FAILED;
        }

        app()->resolving(ResetPasswordNotification::class, fn (ResetPasswordNotification $notification) => $notification->onConnection('sync'));

        try {
            $status = Password::broker($panel->getAuthPasswordBroker())->sendResetLink(
                ['email' => $user->email],
                function (User $user, #[SensitiveParameter] string $token) use ($panel): void {
                    $notification = app(ResetPasswordNotification::class, ['token' => $token]);
                    $notification->url = $panel->getResetPasswordUrl($token, $user);

                    $user->notify($notification);
                },
            );
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return self::FAILED;
        }

        if ($status === Password::RESET_THROTTLED) {
            return self::THROTTLED;
        }

        if ($status !== Password::RESET_LINK_SENT) {
            return self::FAILED;
        }

        UserActivity::passwordLinkSent($user);

        return self::SENT;
    }
}
