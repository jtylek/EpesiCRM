<?php

namespace App\Support\Setup;

use App\Support\Locale\Locales;
use Throwable;

/**
 * The language picked on the setup wizard's first page — Epesi's setup.php
 * started with its Language page too. Before the tables exist there is
 * nowhere else to keep it than the session; Installer saves it as the system
 * default language (the Regional settings default) when it installs, and the
 * session copy is dropped.
 */
class SetupLocale
{
    protected const KEY = 'epesi.setup.locale';

    /** The language picked in this browser session, if it is one we offer. */
    public static function chosen(): ?string
    {
        try {
            $code = session()->get(self::KEY);
        } catch (Throwable) {
            return null;
        }

        return is_string($code) && Locales::isAvailable($code) ? $code : null;
    }

    public static function choose(string $code): void
    {
        if (Locales::isAvailable($code)) {
            session()->put(self::KEY, $code);
        }
    }

    public static function forget(): void
    {
        session()->forget(self::KEY);
    }
}
