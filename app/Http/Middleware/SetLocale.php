<?php

namespace App\Http\Middleware;

use App\Support\Demo;
use App\Support\Locale\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registered on every panel as persistent middleware, so Livewire's own
 * requests (form submits, validation messages, notifications) are in the same
 * language as the page they came from. In demo mode the visitor's own choice
 * comes first (Demo::locale()).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(Demo::locale($request) ?? Locales::forRequest($request));

        return $next($request);
    }
}
