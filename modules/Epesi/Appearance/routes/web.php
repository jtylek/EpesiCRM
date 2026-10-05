<?php

use Epesi\Modules\Appearance\Http\Controllers\LogoController;
use Illuminate\Support\Facades\Route;

// The login pages' custom logos: public, since they are shown before anyone signs in.
Route::middleware('web')
    ->get('branding/{kind}', LogoController::class)
    ->whereIn('kind', ['login', 'login-dark', 'portal', 'portal-dark'])
    ->name('epesi.appearance.logo');
