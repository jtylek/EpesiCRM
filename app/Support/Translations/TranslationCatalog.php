<?php

namespace App\Support\Translations;

use App\Models\Module;
use App\Support\Modules\ModuleRegistry;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Every string of the interface in one language, as the Translations page
 * lists it: its English text, where it comes from, the translation shipped
 * for it and the custom one.
 *
 * A string is known when some language has it, as for lang:import-epesi: the
 * keys of lang/*.json and of every enabled module's lang/*.json. So a
 * language without a string shows it untranslated. Also listed: the lines of
 * the core's own group files (lang/en/*.php, such as record_labels), the
 * labels and sections administrators gave custom fields, and every string
 * that already has a custom translation.
 */
class TranslationCatalog
{
    /** The language the strings are written in. */
    public const SOURCE_LOCALE = 'en';

    public const MISSING = 'missing';

    public const CUSTOM = 'custom';

    public const TRANSLATED = 'translated';

    /**
     * @return array<string, array{key: string, english: string, source: string, shipped: ?string, custom: ?string, status: string}> by key
     */
    public static function for(string $locale): array
    {
        $rows = [];

        // In the loader's order, modules before lang/, so that the shipped
        // translation here is the one __() returns.
        foreach (static::directories() as [$source, $directory]) {
            $files = collect(glob("{$directory}/*.json") ?: [])
                ->mapWithKeys(fn (string $file): array => [pathinfo($file, PATHINFO_FILENAME) => static::read($file)]);
            $english = $files->get(self::SOURCE_LOCALE, []);
            $shipped = $files->get($locale, []);

            foreach ($files->flatMap(fn (array $lines): array => array_keys($lines))->unique() as $key) {
                $key = (string) $key;
                $rows[$key] ??= static::row($key, $english[$key] ?? $key, $source);

                if (filled($shipped[$key] ?? null)) {
                    $rows[$key]['shipped'] = $shipped[$key];
                    $rows[$key]['source'] = $source;
                }
            }
        }

        $loader = app('translator')->getLoader();

        foreach (glob(lang_path(self::SOURCE_LOCALE.'/*.php')) ?: [] as $file) {
            $group = pathinfo($file, PATHINFO_FILENAME);
            $shipped = Arr::dot($loader->load($locale, $group, '*'));

            foreach (Arr::dot($loader->load(self::SOURCE_LOCALE, $group, '*')) as $item => $english) {
                if (is_string($english)) {
                    $key = "{$group}.{$item}";
                    $rows[$key] ??= static::row($key, $english, 'epesi');
                    $rows[$key]['shipped'] = filled($shipped[$item] ?? null) ? $shipped[$item] : null;
                }
            }
        }

        foreach (CustomField::query()->get(['label', 'section']) as $field) {
            foreach (array_filter([$field->label, $field->section]) as $text) {
                $rows[$text] ??= static::row($text, $text, __('Fields'));
            }
        }

        foreach (CustomTranslations::for($locale) as $key => $translation) {
            $key = (string) $key;
            $rows[$key] ??= static::row($key, $key, __('Custom'));
            $rows[$key]['custom'] = $translation;
        }

        foreach ($rows as &$row) {
            if ($locale === self::SOURCE_LOCALE) {
                $row['shipped'] ??= $row['english'];
            }

            $row['status'] = match (true) {
                $row['custom'] !== null => self::CUSTOM,
                $row['shipped'] === null => self::MISSING,
                default => self::TRANSLATED,
            };
        }
        unset($row);

        return $rows;
    }

    /**
     * Where the JSON translations are, with the name each is listed under.
     *
     * @return list<array{0: string, 1: string}>
     */
    protected static function directories(): array
    {
        $modules = config('modules.load', true) ? ModuleRegistry::enabled() : [];

        return collect($modules)
            ->map(fn (array $module): array => [
                Str::afterLast($module['path'], '/'),
                Module::directoryFor($module['path']).DIRECTORY_SEPARATOR.'lang',
            ])
            ->filter(fn (array $directory): bool => is_dir($directory[1]))
            ->push(['epesi', lang_path()])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected static function read(string $file): array
    {
        $lines = json_decode((string) file_get_contents($file), true);

        return is_array($lines) ? $lines : [];
    }

    /**
     * @return array{key: string, english: string, source: string, shipped: ?string, custom: ?string, status: string}
     */
    protected static function row(string $key, string $english, string $source): array
    {
        return [
            'key' => $key,
            'english' => $english,
            'source' => $source,
            'shipped' => null,
            'custom' => null,
            'status' => self::MISSING,
        ];
    }
}
