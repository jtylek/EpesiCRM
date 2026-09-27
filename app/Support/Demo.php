<?php

namespace App\Support;

use App\Support\Locale\Locales;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Demo mode (config/demo.php, DEMO_MODE in .env) — Epesi's DEMO_MODE: a
 * public installation anyone can try without an account, reset every day by
 * `php artisan demo:reset`.
 *
 * What it changes, and where:
 * - the login page offers the demo accounts instead of a password, and a
 *   language kept for the visitor (App\Filament\Auth\Login, locale());
 * - the Administration panel and the setup wizard answer 404
 *   (App\Http\Middleware\DisabledInDemo, User::canAccessPanel());
 * - e-mail never leaves the server, and file uploads are off
 *   (AppServiceProvider::lockDownDemoMode());
 * - actions that would harm the next visitor stay on screen but only say
 *   "Unavailable in demo mode" (guard(), used there and in the modules).
 */
class Demo
{
    public const LOCALE_COOKIE = 'epesi_demo_locale';

    public static function enabled(): bool
    {
        return (bool) config('demo.enabled');
    }

    /**
     * The language this visitor chose on the login page, which wins over the
     * demo account's own. Kept in a cookie: the account's setting is shared
     * by every visitor using it, and the session ends at logout, which a
     * visitor trying both accounts goes through.
     */
    public static function locale(Request $request): ?string
    {
        if (! static::enabled()) {
            return null;
        }

        $code = $request->cookie(self::LOCALE_COOKIE);

        return is_string($code) && Locales::isAvailable($code) ? $code : null;
    }

    /** Keeps $code as the visitor's language; null forgets it. */
    public static function rememberLocale(?string $code): void
    {
        Cookie::queue(Locales::isAvailable($code)
            ? Cookie::make(self::LOCALE_COOKIE, (string) $code, 60 * 24 * 365)
            : Cookie::forget(self::LOCALE_COOKIE));
    }

    /**
     * The accounts the login page offers: e-mail => "Manager — sees and
     * edits every record".
     *
     * @return array<string, string>
     */
    public static function loginOptions(): array
    {
        return collect(config('demo.users', []))
            ->map(fn (array $user): string => __($user['label']).' — '.__($user['description']))
            ->all();
    }

    public static function unavailable(): void
    {
        Notification::make()
            ->title(__('Unavailable in demo mode'))
            ->danger()
            ->send();
    }

    /**
     * In demo mode, the action keeps its button but does nothing except say
     * it is unavailable: no confirmation or form first (a form would be
     * validated before anything else runs), and none of its own success
     * message. Filament never calls the original closure, so a request made
     * by hand can't run it either. Outside demo mode the action is unchanged.
     *
     * @template T of Action
     *
     * @param  T  $action
     * @return T
     */
    public static function guard(Action $action): Action
    {
        if (! static::enabled()) {
            return $action;
        }

        return $action
            ->schema(null)
            ->requiresConfirmation(false)
            ->modalHidden()
            ->before(null)
            ->after(null)
            ->action(fn () => static::unavailable())
            ->successNotification(null)
            ->failureNotification(null);
    }
}
