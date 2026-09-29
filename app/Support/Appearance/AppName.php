<?php

namespace App\Support\Appearance;

use Closure;

/**
 * What the main panel's sidebar/topbar calls this installation
 * (MainPanelProvider::brandName()) in place of "epesi" — the one global
 * Appearance setting that isn't a Theme (see AppearanceSetting). Same hook
 * pattern as CurrentTheme, and for the same reason: core code (a panel
 * provider, loaded on every request) never names the Appearance module
 * directly, so the brand name can't 500 the panel just because the module
 * hasn't been registered yet.
 */
class AppName
{
    /** @var Closure(): string|null */
    protected static ?Closure $resolver = null;

    /**
     * @param  (Closure(): string)|null  $resolver
     */
    public static function resolveUsing(?Closure $resolver): void
    {
        static::$resolver = $resolver;
    }

    public static function current(): string
    {
        return static::$resolver ? (static::$resolver)() : 'epesi';
    }
}
