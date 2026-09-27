<?php

namespace App\Providers\Filament\Concerns;

use Illuminate\Support\HtmlString;

/**
 * On the login (and other simple-layout auth) pages, the brand name ("epesi")
 * reads larger than the page heading ("Sign in") — the reverse of Filament's
 * default, where the heading (text-2xl) outweighs the logo (text-xl). Scoped
 * to `.fi-simple-header` so it doesn't touch `.fi-logo` in the sidebar/topbar
 * of the app shell.
 */
trait HasAuthBrandingStyles
{
    protected function authBrandingStyles(): HtmlString
    {
        return new HtmlString('<style>'
            .'.fi-simple-header .fi-logo{font-size:1.5rem!important}'
            .'.fi-simple-header-heading{font-size:1.25rem!important}'
            .'</style>');
    }
}
