<?php

use Epesi\Modules\StoreServer\Http\Controllers\CatalogController;
use Epesi\Modules\StoreServer\Http\Controllers\DownloadController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

/*
 * The only unauthenticated surface in the application. Both endpoints are
 * read-only, throttled, and touch nothing but the store's own tables.
 *
 * SubstituteBindings is listed explicitly because this group is deliberately
 * outside `web` (no session, no CSRF) — and without it implicit route-model
 * binding doesn't fail loudly, it hands the controller a *blank* model built by
 * the container, which then looks like a mysterious 404.
 */
Route::prefix('store-api')->middleware([SubstituteBindings::class])->group(function (): void {
    Route::get('/catalog', CatalogController::class)
        ->middleware('throttle:120,1')
        ->name('epesi-store.catalog');

    // `signed` is what authorises a download: the catalog hands out short-lived
    // signed URLs, so this route needs no session and no token of its own.
    Route::get('/download/{release}', DownloadController::class)
        ->middleware(['signed', 'throttle:30,1'])
        ->name('epesi-store.download');
});
