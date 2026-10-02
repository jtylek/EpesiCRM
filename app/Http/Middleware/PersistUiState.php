<?php

namespace App\Http\Middleware;

use App\Support\Impersonation;
use App\Support\UiState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * After each request, keeps the signed-in user's screen state in step with the
 * database (see App\Support\UiState): the lists' state when it changed, and the
 * page they are on. Registered as persistent middleware so Livewire's own
 * requests — where filters and columns change — are covered too.
 *
 * Not while impersonating: the administrator's clicks must not overwrite the
 * account they are looking at.
 */
class PersistUiState
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = Auth::user();

        if ($user === null || Impersonation::impersonatorId() !== null || ! $request->hasSession()) {
            return $response;
        }

        UiState::save($user);

        if ($url = $this->pageUrl($request, $response)) {
            UiState::saveLastUrl($user, $url);
        }

        return $response;
    }

    /**
     * The page the user is looking at: a successful HTML page load, or — for a
     * Livewire update — the page it was sent from, whose address may have
     * changed since it loaded (a tab in the query string).
     */
    private function pageUrl(Request $request, Response $response): ?string
    {
        if ($request->hasHeader('X-Livewire') && $request->isMethod('POST')) {
            $url = (string) $request->headers->get('referer');
        } elseif ($request->isMethod('GET') && $response->getStatusCode() === 200
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $url = $request->fullUrl();
        } else {
            return null;
        }

        if ($url === '' || parse_url($url, PHP_URL_HOST) !== $request->getHost()) {
            return null;
        }

        // Login, password reset and the like are never a place to come back to.
        $path = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');

        return preg_match('~/(login|logout|password-reset)(/|$)~', $path) ? null : $url;
    }
}
