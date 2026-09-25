<?php

namespace App\Support\Translations;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * The translations made on Administration → Translations: Epesi's
 * data/Base_Lang/custom/<code>.php. One file per language on the
 * `translations` disk, in the shape of lang/<code>.json (English text =>
 * translation), so it can be sent to the developers as it is.
 *
 * CustomTranslationLoader loads them last, over every shipped translation.
 */
class CustomTranslations
{
    public const DISK = 'translations';

    public static function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter */
        return Storage::disk(self::DISK);
    }

    /**
     * @return array<string, string>
     */
    public static function for(string $locale): array
    {
        $file = static::file($locale);

        if ($file === null || ! static::disk()->exists($file)) {
            return [];
        }

        // A file broken by hand loses its own translations, not every page.
        $lines = json_decode((string) static::disk()->get($file), true);

        return is_array($lines)
            ? array_filter($lines, fn (mixed $line): bool => is_string($line) && $line !== '')
            : [];
    }

    /**
     * Sets a custom translation, or removes it when $translation is blank.
     */
    public static function put(string $locale, string $key, ?string $translation): void
    {
        $file = static::file($locale) ?? throw new InvalidArgumentException("\"{$locale}\" is not a language code.");
        $lines = static::for($locale);

        if (filled($translation)) {
            $lines[$key] = $translation;
        } else {
            unset($lines[$key]);
        }

        if ($lines === []) {
            static::disk()->delete($file);
        } else {
            static::disk()->put($file, static::encode($lines));
        }

        // This request has loaded the old ones already.
        app('translator')->setLoaded([]);
    }

    /**
     * Sorted and formatted as the lang/ files are, so it merges into one.
     *
     * @param  array<string, string>  $lines
     */
    public static function encode(array $lines): string
    {
        uksort($lines, fn (int|string $a, int|string $b): int => strcasecmp((string) $a, (string) $b));

        // JSON_FORCE_OBJECT: a key such as "1" would otherwise make a list.
        return json_encode($lines, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT)."\n";
    }

    /**
     * The file name, from a language code only: $locale can come from a
     * request.
     */
    protected static function file(string $locale): ?string
    {
        return preg_match('/^[a-z]{2,3}(_[A-Za-z]{2,4})?$/', $locale) === 1 ? "{$locale}.json" : null;
    }
}
