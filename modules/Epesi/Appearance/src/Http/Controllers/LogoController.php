<?php

namespace Epesi\Modules\Appearance\Http\Controllers;

use Epesi\Modules\Appearance\Models\AppearanceSetting;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams the logo an administrator uploaded for the login page or the customer
 * portal. Public (the login page is), and an SVG can't run script from here: the
 * sandbox CSP and nosniff make it a picture only, even opened on its own.
 */
class LogoController
{
    public function __invoke(string $kind): Response
    {
        abort_unless(isset(AppearanceSetting::LOGO_COLUMNS[$kind]), 404);

        $path = AppearanceSetting::logoPath($kind);
        $disk = AppearanceSetting::logoDisk();

        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=31536000',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
