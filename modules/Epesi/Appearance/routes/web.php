<?php

use Epesi\Modules\Appearance\Http\Controllers\LogoController;
use Epesi\Modules\Appearance\Http\Controllers\MailLogoController;
use Epesi\Modules\Appearance\Models\AppearanceSetting;
use Illuminate\Support\Facades\Route;

// The login pages' custom logos: public, since they are shown before anyone signs in.
Route::middleware('web')
    ->get('branding/{kind}', LogoController::class)
    ->whereIn('kind', array_keys(AppearanceSetting::LOGO_COLUMNS))
    ->name('epesi.appearance.logo');

// The same logo on a white plate, drawn into the picture, for e-mails.
Route::middleware('web')
    ->get('branding/{kind}/mail', MailLogoController::class)
    ->whereIn('kind', ['login', 'portal', 'dealer'])
    ->name('epesi.appearance.logo.mail');
