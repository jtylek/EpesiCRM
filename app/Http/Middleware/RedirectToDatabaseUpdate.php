<?php

namespace App\Http\Middleware;

use App\Filament\Administration\Pages\DatabaseUpdate;
use App\Services\Setup\SystemUpdate;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * While a newer release's database changes are waiting (SystemUpdate), the
 * CRM's pages would fail on tables that don't exist yet — so an
 * administrator is sent to Administration → Database update, as Epesi sent
 * them to update.php, and everyone else gets a short "being updated" page
 * instead of an error. Guests pass: the login page needs no new tables.
 *
 * Page loads only: registered as ordinary (not persistent) panel middleware,
 * so Livewire's own requests don't pass through it.
 */
class RedirectToDatabaseUpdate
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user === null || SystemUpdate::waiting() === 0) {
            return $next($request);
        }

        if ($user->hasRole('super_admin')) {
            return redirect(DatabaseUpdate::getUrl(panel: 'administration'));
        }

        return response()->view('epesi.updating', status: 503, headers: ['Retry-After' => '300']);
    }
}
