<?php

namespace App\Http\Responses;

use App\Models\User;
use App\Support\UiState;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as Responsable;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Signs the user in where they were last — the page they logged out from or
 * were on when the session expired — unless a page they asked for explicitly
 * is waiting (Laravel's "intended" URL), then there; the panel's home otherwise.
 */
class LoginResponse implements Responsable
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $user = auth()->user();
        $last = $user instanceof User ? UiState::lastUrl($user) : null;

        if ($last && parse_url($last, PHP_URL_HOST) === $request->getHost()) {
            return redirect()->intended($last);
        }

        return redirect()->intended(Filament::getUrl());
    }
}
