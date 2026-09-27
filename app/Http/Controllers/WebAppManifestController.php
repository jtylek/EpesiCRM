<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * The web app manifest (resources/views/filament/components/web-app-head.blade.php
 * links it): lets the browser install epesi as an app, in a window of its
 * own with no address bar.
 *
 * Built here rather than kept as a file in public/, because its scope has to
 * be epesi's own address as the app generates it. The installed window shows
 * an address strip over any page outside the scope, and in a subfolder the
 * dashboard is /epesi-laravel, which a file's "./" scope (/epesi-laravel/)
 * doesn't cover. The id is the same address, so two installations on one
 * host are two apps.
 */
class WebAppManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $home = url('/');

        return response()->json([
            'id' => $home,
            'name' => 'epesi',
            'short_name' => 'epesi',
            'description' => 'epesi CRM',
            'start_url' => $home,
            'scope' => $home,
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#ffffff',
            'icons' => [
                ['src' => asset('images/pwa/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => asset('images/pwa/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
            ],
        ], headers: ['Content-Type' => 'application/manifest+json'], options: JSON_UNESCAPED_SLASHES);
    }
}
