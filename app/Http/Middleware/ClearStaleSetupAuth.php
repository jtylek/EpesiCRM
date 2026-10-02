<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ClearStaleSetupAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (Schema::hasTable('users')) {
                return $next($request);
            }
        } catch (Throwable) {
        }

        $guard = Auth::guard('web');

        if ($guard instanceof SessionGuard) {
            $request->session()->forget($guard->getName());

            $recaller = $guard->getRecallerName();
            $request->cookies->remove($recaller);
            Cookie::queue(Cookie::forget($recaller));
        }

        return $next($request);
    }
}
