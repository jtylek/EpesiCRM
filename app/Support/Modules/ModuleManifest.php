<?php

namespace App\Support\Modules;

use App\Services\Modules\ModuleException;

/**
 * A module's `module.json`, validated. This is the only thing that states a
 * zip-distributed module's PSR-4 mapping (there is no composer.json in the
 * loop), so the namespace/path fields are load-bearing rather than
 * decorative.
 */
class ModuleManifest
{
    /**
     * @param  array<int, string>  $panels
     * @param  array<int, string>  $requires
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $version,
        public readonly string $path,
        public readonly string $namespace,
        public readonly ?string $providerClass,
        public readonly ?string $pluginClass,
        public readonly array $panels,
        public readonly string $epesiCore,
        public readonly array $requires,
        public readonly bool $core,
        public readonly array $raw,
    ) {}

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw new ModuleException('module.json is not valid JSON.');
        }

        return static::fromArray($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['id', 'name', 'version', 'path', 'namespace'] as $required) {
            if (! isset($data[$required]) || ! is_string($data[$required]) || trim($data[$required]) === '') {
                throw new ModuleException("module.json is missing the required \"{$required}\" field.");
            }
        }

        $id = trim($data['id']);
        $path = trim($data['path']);
        $namespace = trim($data['namespace']);
        $version = trim($data['version']);

        if (! preg_match('/^[a-z0-9][a-z0-9._-]*\/[a-z0-9][a-z0-9._-]*$/', $id)) {
            throw new ModuleException("Invalid module id \"{$id}\" — expected lowercase \"vendor/name\".");
        }

        // Two segments minimum ("Epesi/Notes"), more allowed so first-party
        // features can be grouped the way old Epesi's modules/ tree groups them
        // ("Epesi/CRM/Contacts", where CRM is a plain directory that owns
        // nothing). Nesting a module *inside another module* works too, but see
        // ModuleInstaller's guards — a parent's uninstall or zip update has to
        // be told its children exist.
        if (! preg_match('/^[A-Za-z][A-Za-z0-9]*(\/[A-Za-z][A-Za-z0-9]*)+$/', $path)) {
            throw new ModuleException("Invalid module path \"{$path}\" — expected \"Vendor/Name\", optionally nested (\"Vendor/Group/Name\").");
        }

        if (! preg_match('/^([A-Za-z_][A-Za-z0-9_]*\\\\)+$/', $namespace)) {
            throw new ModuleException("Invalid namespace \"{$namespace}\" — expected a PSR-4 prefix ending in a backslash.");
        }

        if (! preg_match('/^\d+\.\d+(\.\d+)?(-[0-9A-Za-z.]+)?$/', $version)) {
            throw new ModuleException("Invalid version \"{$version}\".");
        }

        $providerClass = static::classField($data, 'provider_class', $namespace);
        $pluginClass = static::classField($data, 'plugin_class', $namespace);

        $panels = array_values(array_filter(
            (array) ($data['panels'] ?? ['main']),
            fn ($panel): bool => is_string($panel) && $panel !== '',
        ));

        if ($pluginClass !== null && $panels === []) {
            throw new ModuleException('A module declaring plugin_class must also declare the panels it registers into.');
        }

        return new self(
            id: $id,
            name: trim($data['name']),
            description: isset($data['description']) && is_string($data['description']) ? trim($data['description']) : null,
            version: $version,
            path: $path,
            namespace: $namespace,
            providerClass: $providerClass,
            pluginClass: $pluginClass,
            panels: $panels,
            epesiCore: isset($data['epesi_core']) && is_string($data['epesi_core']) ? trim($data['epesi_core']) : '*',
            requires: array_values(array_filter((array) ($data['requires'] ?? []), 'is_string')),
            core: (bool) ($data['core'] ?? false),
            raw: $data,
        );
    }

    /**
     * A module may only nominate classes inside its own namespace — otherwise a
     * manifest could point provider_class at an arbitrary application class and
     * have the installer register it.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function classField(array $data, string $field, string $namespace): ?string
    {
        $value = $data[$field] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new ModuleException("module.json field \"{$field}\" must be a class name.");
        }

        $value = ltrim(trim($value), '\\');

        if (! str_starts_with($value, $namespace)) {
            throw new ModuleException("module.json field \"{$field}\" ({$value}) must be inside the module's own namespace {$namespace}.");
        }

        return $value;
    }

    public function migrationSlug(): string
    {
        return str_replace(['/', '-', '.'], '_', strtolower($this->id));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabaseRow(): array
    {
        return [
            'module_id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'path' => $this->path,
            'namespace' => $this->namespace,
            'provider_class' => $this->providerClass,
            'plugin_class' => $this->pluginClass,
            'panels' => $this->panels,
            'manifest' => $this->raw,
        ];
    }
}
