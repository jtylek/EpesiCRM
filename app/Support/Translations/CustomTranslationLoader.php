<?php

namespace App\Support\Translations;

use Illuminate\Contracts\Translation\Loader;

/**
 * Laravel's translation loader, with the custom translations merged in last,
 * over the core's lang/, every module's lang/ and the packages' own.
 *
 * A JSON path of their own would not do: FileLoader reads the paths added
 * with addJsonPath() (the modules') before lang/, so lang/<code>.json would
 * win over them. And since __() looks a key up in the JSON translations
 * before any group file, a custom entry also replaces a group line such as
 * record_labels.status.open or filament-actions::edit.single.label.
 *
 * Machine translations go underneath: <code>.machine.json beside each
 * <code>.json, written by the DeepL pass and kept apart so a rerun never
 * touches a reviewed translation. A reviewed one in <code>.json wins, and a
 * string in neither falls back to its English.
 */
class CustomTranslationLoader implements Loader
{
    public function __construct(protected Loader $loader) {}

    public function load($locale, $group, $namespace = null)
    {
        $lines = $this->loader->load($locale, $group, $namespace);

        if ($group === '*' && $namespace === '*') {
            // array_replace, not array_merge: a numeric key keeps its number.
            return array_replace($this->machineTranslations($locale), $lines, CustomTranslations::for($locale));
        }

        return $lines;
    }

    /**
     * Every <code>.machine.json, read in FileLoader's own order (the modules'
     * paths, then lang/), so a later path wins as it does for <code>.json.
     *
     * @return array<string, string>
     */
    protected function machineTranslations(string $locale): array
    {
        if (! method_exists($this->loader, 'jsonPaths') || ! method_exists($this->loader, 'paths')) {
            return [];
        }

        $lines = [];

        foreach ([...$this->loader->jsonPaths(), ...$this->loader->paths()] as $path) {
            if (is_file($file = "{$path}/{$locale}.machine.json")) {
                $lines = array_replace($lines, (array) json_decode((string) file_get_contents($file), true));
            }
        }

        return $lines;
    }

    public function addNamespace($namespace, $hint)
    {
        $this->loader->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->loader->addJsonPath($path);
    }

    public function namespaces()
    {
        return $this->loader->namespaces();
    }

    /**
     * FileLoader's own methods beyond the contract (addPath(), jsonPaths()).
     *
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->loader->{$method}(...$parameters);
    }
}
