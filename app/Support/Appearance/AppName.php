<?php

namespace App\Support\Appearance;

use Closure;

/**
 * The three titles an administrator sets in Administration → Themes (see
 * AppearanceSetting): what the main panel's sidebar/topbar calls this
 * installation (MainPanelProvider::brandName()) in place of "epesi", what the
 * login pages are headed with, and what the customer portal is called. Same
 * hook pattern as CurrentTheme, and for the same reason: core code (a panel
 * provider, loaded on every request) never names the Appearance module
 * directly, so a title can't 500 the panel just because the module hasn't
 * been registered yet.
 */
class AppName
{
    public const APP = 'app';

    public const LOGIN = 'login';

    public const PORTAL = 'portal';

    /** @var Closure(string): string|null */
    protected static ?Closure $resolver = null;

    /**
     * @param  (Closure(string): string)|null  $resolver  gets APP, LOGIN or PORTAL
     */
    public static function resolveUsing(?Closure $resolver): void
    {
        static::$resolver = $resolver;
    }

    /** The main application's title (default "epesi"). */
    public static function current(): string
    {
        return static::title(self::APP);
    }

    /** The heading of the login pages (default "epesi"). */
    public static function login(): string
    {
        return static::title(self::LOGIN);
    }

    /** The customer portal's title (default "Customer Portal"). */
    public static function portal(): string
    {
        return static::title(self::PORTAL);
    }

    protected static function title(string $kind): string
    {
        if (static::$resolver) {
            return (static::$resolver)($kind);
        }

        return $kind === self::PORTAL ? __('Customer Portal') : 'epesi';
    }
}
