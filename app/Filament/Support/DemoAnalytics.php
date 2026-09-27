<?php

namespace App\Filament\Support;

use App\Support\Demo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * Google Analytics on the public demo (demo.analytics_id, DEMO_ANALYTICS_ID in
 * .env), set up the way the epesicrm.com site around the demo does it:
 * Google's tag always loads, but analytics_storage stays denied until the
 * visitor accepts the cookie bar, and "Cookie settings" at the foot of every
 * page takes it back.
 *
 * The choice is kept in localStorage under the site's own key. The demo
 * lives under the site's address, so one choice covers both: a visitor who
 * already answered on the site isn't asked again here.
 *
 * Off on anything that isn't a demo, whatever the setting says: an ordinary
 * installation is a company's own CRM, and it sends nothing anywhere.
 */
class DemoAnalytics
{
    /** Where epesicrm.com keeps the visitor's choice: "granted" or "denied". */
    public const CHOICE_KEY = 'epesi-analytics';

    public static function id(): ?string
    {
        $id = trim((string) config('demo.analytics_id'));

        return Demo::enabled() && $id !== '' ? $id : null;
    }

    /**
     * HEAD_END: Google's tag with everything denied, and what loads it once
     * the visitor accepts. The demo account in use (Manager or Employee) goes
     * with it as the demo_account user property.
     */
    public static function head(): string|View
    {
        $id = static::id();

        if ($id === null) {
            return '';
        }

        // Not config("demo.users.{$email}"): the dots in an address would
        // read as nesting.
        $account = config('demo.users', [])[Auth::user()?->email ?? ''] ?? null;

        return view('filament.components.demo-analytics-head', [
            'id' => $id,
            'choiceKey' => self::CHOICE_KEY,
            'account' => $account['label'] ?? null,
        ]);
    }

    /** FOOTER: "Cookie settings", and the cookie bar for a visitor who hasn't chosen yet. */
    public static function consent(): string|View
    {
        return static::id() === null ? '' : view('filament.components.demo-analytics-consent');
    }
}
