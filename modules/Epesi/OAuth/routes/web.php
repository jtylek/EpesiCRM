<?php

use Epesi\Modules\OAuth\Http\Controllers\CallbackController;
use Illuminate\Support\Facades\Route;

// The redirect URI every provider's app is registered with
// (Administration → OAuth Apps shows it). The session tells the flows apart.
Route::middleware('web')
    ->get('oauth/callback', CallbackController::class)
    ->name('epesi.oauth.callback');
