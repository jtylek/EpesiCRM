<?php

use App\Http\Controllers\LeaveImpersonationController;
use Illuminate\Support\Facades\Route;

// The "main" Filament panel is mounted at the root path (see
// MainPanelProvider), so it owns "/" — no separate route needed here.

// Loaded after the panels' routes, so this wins over the setup panel's own
// root route (see SetupPanelProvider).
// By route name, not Route::redirect(): that one sends a bare "/setup/install",
// which loses the base path when epesi runs from a subfolder
// (http://localhost/epesi/public/ on XAMPP) and lands on a 404.
Route::get('/setup', fn () => redirect()->route('filament.setup.install'));

// POST, like logout: a link or an image elsewhere mustn't switch accounts.
Route::post('/impersonation/leave', LeaveImpersonationController::class)->name('impersonation.leave');
