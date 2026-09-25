<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Seeds a language from an old epesi installation's translations —
 * modules/Base/Lang/lang/<code>.php, plus data/Base_Lang/custom/<code>.php
 * where an administrator changed them in epesi's Translations screen.
 *
 * epesi keyed its translations by the English text just as the JSON files
 * here do, so a string that reads the same in both gets epesi's
 * translation. The strings to look up are the ones some language here
 * already has (lang/*.json and each module's lang/*.json); each translation
 * is written next to the string's other languages, so a module's strings
 * stay in the module. Strings that are already translated are kept.
 */
class ImportEpesiTranslations extends Command
{
    protected $signature = 'lang:import-epesi
        {locale : language code, e.g. de — epesi\'s file name without .php}
        {path : the epesi installation (or its lang/<code>.php file)}
        {--dry-run : list what would be imported without writing}';

    protected $description = 'Import translations from an epesi installation';

    public function handle(): int
    {
        $locale = (string) $this->argument('locale');

        if (! preg_match('/^[a-z]{2,3}(_[A-Za-z]{2,4})?$/', $locale)) {
            $this->error("\"{$locale}\" is not a language code.");

            return self::FAILURE;
        }

        $epesi = $this->readEpesi((string) $this->argument('path'), $locale);

        if ($epesi === null) {
            return self::FAILURE;
        }

        $byLowercase = [];
        foreach ($epesi as $english => $translated) {
            $byLowercase[mb_strtolower($english)] ??= $translated;
        }

        $imported = 0;
        $untranslated = 0;

        foreach ($this->languageDirectories() as $directory => $keys) {
            $file = "{$directory}/{$locale}.json";
            $existing = is_file($file) ? (array) json_decode(File::get($file), true) : [];
            $added = [];

            foreach ($keys as $key) {
                if (filled($existing[$key] ?? null)) {
                    continue;
                }

                $translation = $epesi[$key] ?? $this->matchCase($key, $byLowercase[mb_strtolower($key)] ?? null);

                if ($translation === null) {
                    $untranslated++;

                    continue;
                }

                $added[$key] = $translation;
            }

            if ($added === []) {
                continue;
            }

            $imported += count($added);
            $this->line(sprintf('%-50s %d', Str::after($file, base_path().DIRECTORY_SEPARATOR), count($added)));

            if (! $this->option('dry-run')) {
                $merged = [...$existing, ...$added];
                uksort($merged, fn (string $a, string $b): int => strcasecmp($a, $b));
                File::put($file, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
            }
        }

        $this->components->info(sprintf(
            '%s %d translations; %d strings have none in epesi and need translating by hand.',
            $this->option('dry-run') ? 'Would import' : 'Imported',
            $imported,
            $untranslated,
        ));

        if (! array_key_exists($locale, (array) config('app.available_locales'))) {
            $this->components->warn("Add '{$locale}' to available_locales in config/app.php to offer it to users.");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>|null
     */
    protected function readEpesi(string $path, string $locale): ?array
    {
        $files = is_file($path)
            ? [$path]
            : array_filter([
                "{$path}/modules/Base/Lang/lang/{$locale}.php",
                "{$path}/data/Base_Lang/custom/{$locale}.php",
            ], 'is_file');

        if ($files === []) {
            $this->error("No epesi translations for \"{$locale}\" under {$path}.");

            return null;
        }

        $translations = [];

        foreach ($files as $file) {
            // epesi's files fill `global $translations`, one assignment per
            // line; the custom file comes second and wins.
            $GLOBALS['translations'] = [];
            (static function (string $file): void {
                include $file;
            })($file);

            $translations = [...$translations, ...array_filter((array) $GLOBALS['translations'], fn ($value): bool => is_string($value) && $value !== '')];
            unset($GLOBALS['translations']);
        }

        // epesi escaped quotes in its keys and values.
        return collect($translations)
            ->mapWithKeys(fn (string $value, $key): array => [stripslashes((string) $key) => stripslashes($value)])
            ->all();
    }

    /**
     * Every directory with JSON translations, with the strings found in them.
     *
     * @return array<string, list<string>>
     */
    protected function languageDirectories(): array
    {
        $directories = [lang_path(), ...glob(base_path('modules/*/*/lang'), GLOB_ONLYDIR) ?: [], ...glob(base_path('modules/*/*/*/lang'), GLOB_ONLYDIR) ?: []];

        return collect($directories)
            ->mapWithKeys(fn (string $directory): array => [$directory => collect(glob("{$directory}/*.json") ?: [])
                ->flatMap(fn (string $file): array => array_keys((array) json_decode(File::get($file), true)))
                ->unique()
                ->values()
                ->all()])
            ->filter()
            ->all();
    }

    /**
     * A case-insensitive match takes the case of the string it stands in for:
     * "contacts" (a model label mid-sentence) from epesi's "Contacts".
     */
    protected function matchCase(string $key, ?string $translation): ?string
    {
        if ($translation === null) {
            return null;
        }

        $first = mb_substr($key, 0, 1);

        return $first === mb_strtolower($first) && $first !== mb_strtoupper($first)
            ? mb_strtolower(mb_substr($translation, 0, 1)).mb_substr($translation, 1)
            : $translation;
    }
}
