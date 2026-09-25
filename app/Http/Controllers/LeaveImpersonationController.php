<?php

namespace App\Http\Controllers;

use App\Filament\Administration\Resources\Users\UserResource;
use App\Support\Impersonation;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * The button on ImpersonationNotice's bar: back to the super_admin's own
 * account, on the Users page of the account they were logged in as.
 */
class LeaveImpersonationController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $impersonated = Auth::user();

        if (! $impersonated || ! Impersonation::impersonatorId()) {
            return redirect(Filament::getPanel('main')->getUrl());
        }

        Impersonation::stop();

        if (! Auth::check()) {
            return redirect(Filament::getPanel('main')->getLoginUrl());
        }

        return redirect(UserResource::getUrl('view', ['record' => $impersonated], panel: 'administration'));
    }
}
