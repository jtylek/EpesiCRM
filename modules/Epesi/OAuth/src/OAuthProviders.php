<?php

namespace Epesi\Modules\OAuth;

use Epesi\Modules\OAuth\Exceptions\OAuthException;
use Epesi\Modules\OAuth\Http\Controllers\CallbackController;
use Epesi\Modules\OAuth\Providers\OAuthProvider;
use Illuminate\Support\Facades\Route;

/**
 * The OAuth providers this installation knows, as CalendarRegistry is for
 * calendar events: a module adds one from its service provider's boot() —
 *
 *     OAuthProviders::register(AllegroProvider::class);
 *
 * — and its tokens, OAuth Apps and the callback work for it with no
 * change elsewhere. This module registers Microsoft and Google itself.
 */
class OAuthProviders
{
    /** @var array<string, class-string<OAuthProvider>> key => class */
    protected static array $providers = [];

    /** @var array<string, OAuthProvider> */
    protected static array $instances = [];

    /**
     * @param  class-string<OAuthProvider>  $class
     */
    public static function register(string $class): void
    {
        $provider = app($class);
        static::$providers[$provider->key()] = $class;
        static::$instances[$provider->key()] = $provider;

        static::registerCallbackRoute($provider);
    }

    /**
     * A provider with a callback path of its own gets a route there, served
     * by the shared handler; the route default tells the handler which
     * provider it is for.
     */
    protected static function registerCallbackRoute(OAuthProvider $provider): void
    {
        $path = trim((string) $provider->callbackPath(), '/');
        $name = 'epesi.oauth.callback.'.$provider->key();

        if ($path === '' || Route::has($name)) {
            return;
        }

        Route::middleware('web')
            ->get($path, CallbackController::class)
            ->defaults('oauthProvider', $provider->key())
            ->name($name);

        // Added after boot, when the router's name lookup is already built.
        Route::getRoutes()->refreshNameLookups();
    }

    /** The exact redirect URI to register $provider's app with. */
    public static function redirectUri(OAuthProvider $provider): string
    {
        $name = 'epesi.oauth.callback.'.$provider->key();

        return Route::has($name) ? route($name) : route('epesi.oauth.callback');
    }

    /**
     * @return array<string, OAuthProvider> key => provider, sorted by label
     */
    public static function all(): array
    {
        $all = static::$instances;
        uasort($all, fn (OAuthProvider $a, OAuthProvider $b): int => strnatcasecmp($a->label(), $b->label()));

        return $all;
    }

    public static function find(string $key): ?OAuthProvider
    {
        return static::$instances[$key] ?? null;
    }

    public static function get(string $key): OAuthProvider
    {
        return static::find($key) ?? throw OAuthException::unknownProvider($key);
    }

    /** For tests that register a provider of their own. */
    public static function forget(string $key): void
    {
        unset(static::$providers[$key], static::$instances[$key]);
    }
}
