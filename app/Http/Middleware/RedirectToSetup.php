<?php

namespace App\Http\Middleware;

use App\Support\Setup\SetupState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends every panel request to the setup wizard until the system is
 * installed, and the administrator to the module setup pages until they are
 * done or skipped — what Epesi's index.php did by loading FirstRun instead of
 * Base while Base wasn't installed.
 */
class RedirectToSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! SetupState::isInstalled()) {
            return redirect()->route('filament.setup.install');
        }

        if (SetupState::finishPending() && Auth::user()?->hasRole('super_admin')) {
            return redirect()->route('filament.setup.finish');
        }

        return $next($request);
    }
}
