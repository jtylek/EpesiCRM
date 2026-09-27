<?php

namespace App\Filament\Support;

use App\Support\Demo;
use Illuminate\Support\HtmlString;

/**
 * The bar at the bottom of every page in demo mode (App\Support\Demo): that
 * the data goes back every day, and what the demo doesn't do, so nobody
 * expects an e-mail or keeps work in it.
 */
class DemoNotice
{
    public static function render(): string|HtmlString
    {
        if (! Demo::enabled()) {
            return '';
        }

        // Own <style>: the panels use Filament's precompiled stylesheet. Sticky
        // rather than fixed, so it spans only the content column (not the
        // sidebar) and never covers the end of the page; margin-top:auto puts
        // it at the bottom of a page shorter than the window.
        return new HtmlString(
            '<style>'
            .'.epesi-demo-notice{position:sticky;bottom:0;z-index:10;margin-top:auto;padding:.625rem 1rem;text-align:center;font-size:.875rem;border-top:1px solid color-mix(in srgb, var(--info-500) 30%, transparent);background:color-mix(in srgb, var(--info-500) 15%, var(--gray-50))}'
            .'.dark .epesi-demo-notice{background:color-mix(in srgb, var(--info-500) 20%, var(--gray-950))}'
            .'</style>'
            .'<div role="status" class="epesi-demo-notice">'
            .e(__('This is a demo. The data is reset every day. E-mail is not sent and files can\'t be uploaded.'))
            .'</div>'
        );
    }
}
