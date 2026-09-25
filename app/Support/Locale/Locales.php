<?php

namespace App\Support\Locale;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Throwable;

/**
 * Which language a request or a user gets — Base_LangCommon::get_lang_code()
 * and detect_and_load_language(): a signed-in user gets their own language,
 * else the system default; a guest (the login page) gets their browser's
 * language when it is one of ours, else the system default.
 *
 * Where a user's language and the system default are stored is up to a module
 * (RegionalSettings keeps both), which plugs in with the resolve*Using()
 * hooks; without one, everything is config('app.locale').
 */
class Locales
{
    protected static ?Closure $userLocale = null;

    protected static ?Closure $defaultLocale = null;

    /**
     * @return array<string, string> code => name in that language
     */
    public static function available(): array
    {
        return (array) config('app.available_locales', ['en' => 'English']);
    }

    public static function isAvailable(?string $code): bool
    {
        return $code !== null && array_key_exists($code, static::available());
    }

    /**
     * Runs $callback in $locale and switches back afterwards — for text
     * rendered for someone other than the current user (a notification in
     * its recipient's language).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function using(string $locale, Closure $callback): mixed
    {
        $previous = app()->getLocale();

        if ($locale === $previous) {
            return $callback();
        }

        app()->setLocale($locale);

        try {
            return $callback();
        } finally {
            app()->setLocale($previous);
        }
    }

    /**
     * @param  Closure(User): ?string  $resolver
     */
    public static function resolveUserLocaleUsing(?Closure $resolver): void
    {
        static::$userLocale = $resolver;
    }

    /**
     * @param  Closure(): ?string  $resolver
     */
    public static function resolveDefaultLocaleUsing(?Closure $resolver): void
    {
        static::$defaultLocale = $resolver;
    }

    public static function systemDefault(): string
    {
        $locale = static::ask(static::$defaultLocale);

        return static::isAvailable($locale) ? $locale : static::configured();
    }

    /**
     * APP_LOCALE as configured. Not config('app.locale') as it is now:
     * app()->setLocale() writes there, so in a long-running process (a queue
     * worker, the test suite) it holds whatever the last request set.
     */
    protected static function configured(): string
    {
        return (string) config('app.configured_locale', config('app.locale'));
    }

    public static function forUser(User $user): string
    {
        $locale = static::ask(static::$userLocale, $user);

        return static::isAvailable($locale) ? $locale : static::systemDefault();
    }

    public static function forRequest(Request $request): string
    {
        if ($user = $request->user()) {
            return static::forUser($user);
        }

        return static::browser($request) ?? static::systemDefault();
    }

    /**
     * A resolver that fails — the database not reachable yet during setup, or
     * a column its migration hasn't added yet — means "no preference" rather
     * than an error on every page.
     */
    protected static function ask(?Closure $resolver, mixed ...$arguments): ?string
    {
        if ($resolver === null) {
            return null;
        }

        try {
            return $resolver(...$arguments);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The browser's first language we have, by its primary subtag ("pl-PL"
     * counts as "pl"), as Epesi compared the first two letters.
     */
    protected static function browser(Request $request): ?string
    {
        foreach ($request->getLanguages() as $language) {
            $code = strtolower(strtok(str_replace('-', '_', $language), '_'));

            if (static::isAvailable($code)) {
                return $code;
            }
        }

        return null;
    }
}
