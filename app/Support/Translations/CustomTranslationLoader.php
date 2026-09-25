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
 */
class CustomTranslationLoader implements Loader
{
    public function __construct(protected Loader $loader) {}

    public function load($locale, $group, $namespace = null)
    {
        $lines = $this->loader->load($locale, $group, $namespace);

        if ($group === '*' && $namespace === '*') {
            // array_replace, not array_merge: a numeric key keeps its number.
            return array_replace($lines, CustomTranslations::for($locale));
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
