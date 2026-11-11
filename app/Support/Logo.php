<?php

namespace App\Support;

use App\Support\Appearance\AppName;
use Closure;
use Illuminate\Support\HtmlString;

/**
 * The epesi logo: dark lettering on light pages, light lettering on dark ones
 * (public/images/logo-light.png, logo-dark.png). Filament puts a `dark` class on
 * <html>; the swap is plain CSS here because the compiled stylesheet only holds
 * the Tailwind classes the app's own views already use.
 */
class Logo
{
    private const SWAP_CSS = '<style>.epesi-logo-dark{display:none}.dark .epesi-logo-light{display:none}.dark .epesi-logo-dark{display:inline-block}</style>';

    public const LOGIN = 'login';

    public const PORTAL = 'portal';

    public const DEALER = 'dealer';

    /** @var Closure(string): array{light: ?string, dark: ?string}|null */
    protected static ?Closure $resolver = null;

    /**
     * The built-in logo, always the epesi one: what the About page shows.
     * (Never the customer's own logo — About says what the software is.)
     *
     * @param  string  $height  CSS height of the logo, e.g. "3.5rem".
     */
    public static function html(string $height = '3.5rem'): HtmlString
    {
        $alt = e('epesi');
        $style = 'height:'.e($height).';width:auto';

        return new HtmlString(
            self::SWAP_CSS
            .'<img class="epesi-logo-light" style="'.$style.'" src="'.e(asset('images/logo-light.png')).'" alt="'.$alt.'">'
            .'<img class="epesi-logo-dark" style="'.$style.'" src="'.e(asset('images/logo-dark.png')).'" alt="'.$alt.'">'
        );
    }

    /**
     * The logo for e-mails: the installation's own LOGIN logo (Administration →
     * Themes) when one is uploaded, else the built-in one, on a white plate drawn
     * into the picture (it survives a mail app's dark mode). Absolute URL, since a
     * mail client cannot resolve a relative one.
     *
     * @return array{url: string, alt: string}
     */
    public static function mail(): array
    {
        $urls = static::$resolver ? (static::$resolver)(self::LOGIN) : ['light' => null, 'dark' => null];
        $url = ($urls['mail'] ?? null) ?: ($urls['light'] ?: asset('images/logo-mail.png'));

        return [
            'url' => str_starts_with($url, 'http') ? $url : url($url),
            'alt' => $urls['light'] ? AppName::of(self::LOGIN) : 'epesi',
        ];
    }

    /**
     * The logo on the main application's login pages: the administrator's own
     * (Administration → Themes), else the built-in one.
     */
    public static function login(string $height = '5rem'): HtmlString
    {
        return self::of(self::LOGIN, $height);
    }

    /**
     * The logo on the customer portal's login page: its own, separate from the
     * main application's.
     */
    public static function portal(string $height = '5rem'): HtmlString
    {
        return self::of(self::PORTAL, $height);
    }

    /**
     * The logo on the dealer portal's login page: its own, separate from the
     * main application's and the customer portal's.
     */
    public static function dealer(string $height = '5rem'): HtmlString
    {
        return self::of(self::DEALER, $height);
    }

    /**
     * The logo of one kind (LOGIN, PORTAL or DEALER): the administrator's own,
     * else the built-in one.
     */
    public static function of(string $kind, string $height = '5rem'): HtmlString
    {
        return self::custom($kind, $height) ?? self::html($height);
    }

    /**
     * What the Appearance module tells core code: the URLs of the logos an
     * administrator uploaded for LOGIN, PORTAL or DEALER, for light and dark mode (null
     * for none). Core never names the module directly, same hook pattern as
     * AppName.
     *
     * @param  (Closure(string): array{light: ?string, dark: ?string})|null  $resolver
     */
    public static function resolveUsing(?Closure $resolver): void
    {
        static::$resolver = $resolver;
    }

    private static function custom(string $target, string $height): ?HtmlString
    {
        $urls = static::$resolver ? (static::$resolver)($target) : ['light' => null, 'dark' => null];

        if (! $urls['light'] && ! $urls['dark']) {
            return null;
        }

        // The title that goes with the logo: the login title, the customer portal's or the dealer portal's.
        $alt = e(AppName::of($target));
        $style = 'max-height:'.e($height).';max-width:100%;width:auto';

        if ($urls['light'] === $urls['dark']) {
            return new HtmlString('<img style="'.$style.'" src="'.e($urls['light']).'" alt="'.$alt.'">');
        }

        // One picture per colour mode, swapped like the built-in logo.
        return new HtmlString(
            self::SWAP_CSS
            .'<img class="epesi-logo-light" style="'.$style.'" src="'.e($urls['light']).'" alt="'.$alt.'">'
            .'<img class="epesi-logo-dark" style="'.$style.'" src="'.e($urls['dark']).'" alt="'.$alt.'">'
        );
    }
}
