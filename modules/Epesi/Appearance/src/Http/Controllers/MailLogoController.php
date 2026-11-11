<?php

namespace Epesi\Modules\Appearance\Http\Controllers;

use Epesi\Modules\Appearance\Models\AppearanceSetting;
use Epesi\Modules\Appearance\Support\MailLogoPlate;
use Symfony\Component\HttpFoundation\Response;

/**
 * The uploaded logo on a white plate, as a picture for e-mails (MailLogoPlate
 * says why). Public like the logo itself; made once and kept on the logo disk.
 * A logo GD can't read (an SVG) is sent as it is.
 */
class MailLogoController
{
    public function __invoke(string $kind): Response
    {
        abort_unless(isset(AppearanceSetting::LOGO_COLUMNS[$kind]), 404);

        $path = AppearanceSetting::logoPath($kind) ?? AppearanceSetting::logoPath($kind.'-dark');
        $disk = AppearanceSetting::logoDisk();

        abort_unless($path && $disk->exists($path), 404);

        $cache = 'branding-mail/'.$kind.'-'.crc32($path).'-v2.png';

        if (! $disk->exists($cache)) {
            $png = MailLogoPlate::render((string) $disk->get($path));

            if ($png === null) {
                return redirect()->route('epesi.appearance.logo', ['kind' => AppearanceSetting::logoPath($kind) ? $kind : $kind.'-dark']);
            }

            $disk->put($cache, $png);
        }

        return $disk->response($cache, null, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
