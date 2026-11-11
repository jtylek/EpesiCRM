<?php

namespace App\Support\Appearance;

use Closure;

/**
 * The titles an administrator sets in Administration → Appearance → Logo &
 * Title (see AppearanceSetting): what the main panel's sidebar/topbar calls
 * this installation (MainPanelProvider::brandName()) in place of "epesi", what
 * the login pages are headed with, and what the customer portal and the dealer
 * portal are called. Same hook pattern as CurrentTheme, and for the same
 * reason: core code (a panel provider, loaded on every request) never names
 * the Appearance module directly, so a title can't 500 the panel just because
 * the module hasn't been registered yet.
 */
class AppName
{
    public const APP = 'app';

    public const LOGIN = 'login';

    public const PORTAL = 'portal';

    public const DEALER = 'dealer';

    /**
     * The portals, by panel id: the panels whose login page has a title and a
     * logo of their own (the kind, which is also the Logo kind). Every other
     * panel's login page uses LOGIN.
     */
    public const PORTAL_PANELS = [
        'portal' => self::PORTAL,
        'dealer' => self::DEALER,
    ];

    /** @var Closure(string): string|null */
    protected static ?Closure $resolver = null;

    /**
     * @param  (Closure(string): string)|null  $resolver  gets APP, LOGIN, PORTAL or DEALER
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

    /** The dealer portal's title (default "Dealer Portal"). */
    public static function dealer(): string
    {
        return static::title(self::DEALER);
    }

    /** The title of one kind: APP, LOGIN, PORTAL or DEALER. */
    public static function of(string $kind): string
    {
        return static::title($kind);
    }

    /** The kind a panel's login page is headed with: its portal's own, else LOGIN. */
    public static function loginKindFor(?string $panelId): string
    {
        return self::PORTAL_PANELS[$panelId] ?? self::LOGIN;
    }

    protected static function title(string $kind): string
    {
        if (static::$resolver) {
            return (static::$resolver)($kind);
        }

        return match ($kind) {
            self::PORTAL => __('Customer Portal'),
            self::DEALER => __('Dealer Portal'),
            default => 'epesi',
        };
    }
}
