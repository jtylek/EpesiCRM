<?php

namespace App\Http\Middleware;

use App\Support\Demo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On the Administration panel and the setup wizard: in demo mode they don't
 * exist (404), for everyone, their login pages included. The demo is
 * installed and reset from the command line (`php artisan demo:reset`).
 */
class DisabledInDemo
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(Demo::enabled(), 404);

        return $next($request);
    }
}
