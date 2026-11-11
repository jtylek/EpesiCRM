<?php

namespace Epesi\Modules\Appearance\Support;

/**
 * The logo as e-mails show it: the picture on a white rounded plate, drawn into
 * the image itself. Mail apps in dark mode (Gmail) turn every white CSS
 * background dark but leave pictures alone, so a plate made with CSS would vanish
 * and a logo with a white background would show as a bare white box.
 *
 * Drawn at twice the size it is shown at (WIDTH by HEIGHT), so it stays sharp
 * on phones.
 */
class MailLogoPlate
{
    /** The plate is as wide as the mail's card (CSS pixels); the mail scales it to fit. */
    public const WIDTH = 570;

    public const HEIGHT = 88;

    private const SCALE = 2;

    /** The most the logo itself may take, centred on the plate. */
    private const LOGO_BOX = [260, 52];

    private const RADIUS = 10;

    /**
     * @return string|null PNG bytes, or null when the picture can't be read
     *                     (an SVG, or no GD).
     */
    public static function render(string $bytes): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $logo = @imagecreatefromstring($bytes);

        if ($logo === false) {
            return null;
        }

        $s = self::SCALE;
        [$boxW, $boxH] = [self::LOGO_BOX[0] * $s, self::LOGO_BOX[1] * $s];
        [$width, $height] = [self::WIDTH * $s, self::HEIGHT * $s];
        $radius = self::RADIUS * $s;

        $srcW = imagesx($logo);
        $srcH = imagesy($logo);
        $ratio = min($boxW / $srcW, $boxH / $srcH);
        $w = max(1, (int) round($srcW * $ratio));
        $h = max(1, (int) round($srcH * $ratio));

        $padX = intdiv($width - $w, 2);
        $padY = intdiv($height - $h, 2);

        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 255, 255, 255, 127));

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, $radius, 0, $width - 1 - $radius, $height - 1, $white);
        imagefilledrectangle($canvas, 0, $radius, $width - 1, $height - 1 - $radius, $white);

        foreach ([[$radius, $radius], [$width - 1 - $radius, $radius], [$radius, $height - 1 - $radius], [$width - 1 - $radius, $height - 1 - $radius]] as [$cx, $cy]) {
            imagefilledellipse($canvas, $cx, $cy, $radius * 2, $radius * 2, $white);
        }

        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $logo, $padX, $padY, 0, 0, $w, $h, $srcW, $srcH);

        ob_start();
        imagepng($canvas);
        $png = (string) ob_get_clean();

        return $png === '' ? null : $png;
    }
}
