<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\UiState;
use Illuminate\Auth\Events\Login;

/** Puts back the lists' filters, columns and the rest a user left at their last logout or expiry. */
class RestoreUiState
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User && session()->isStarted()) {
            UiState::restore($event->user);
        }
    }
}
