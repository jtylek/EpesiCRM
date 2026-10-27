<?php

use Epesi\Modules\Roundcube\Http\Controllers\OAuthTokenController;
use Illuminate\Support\Facades\Route;

// Called by Roundcube's epesi_sso plugin from the server, not by a browser:
// no session, cookies or CSRF token, only the grant in the POST body. Not
// under epesi-webmail/, which the web server hands to Roundcube itself.
Route::post('epesi-roundcube/oauth-token', OAuthTokenController::class)
    ->name('epesi.roundcube.oauth-token');
