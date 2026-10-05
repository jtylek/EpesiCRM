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
     * The logo on the main application's login pages: the administrator's own
     * (Administration → Themes), else the built-in one.
     */
    public static function login(string $height = '5rem'): HtmlString
    {
        return self::custom(self::LOGIN, $height) ?? self::html($height);
    }

    /**
     * The logo on the customer portal's login page: its own, separate from the
     * main application's.
     */
    public static function portal(string $height = '5rem'): HtmlString
    {
        return self::custom(self::PORTAL, $height) ?? self::html($height);
    }

    /**
     * What the Appearance module tells core code: the URLs of the logos an
     * administrator uploaded for LOGIN or PORTAL, for light and dark mode (null
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

        $alt = e(AppName::login());
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
