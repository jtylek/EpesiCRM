<?php

namespace App\Services\Modules;

use App\Models\Module;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\VersionConstraint;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * Every module directory under modules/, registered or not: what an
 * administrator sees under Administration → Modules → Discover modules.
 * A directory unpacked by hand, a working copy, or one whose `modules` row
 * was lost shows up here and can be registered (ModuleInstaller::registerExisting).
 */
class ModuleDiscovery
{
    public const REGISTERED = 'registered';

    public const AVAILABLE = 'available';

    public const BLOCKED = 'blocked';

    public const INVALID = 'invalid';

    /**
     * @return array<int, array{path: string, id: ?string, name: string, version: ?string, description: ?string, status: string, problem: ?string}>
     */
    public function scan(): array
    {
        $root = rtrim((string) config('modules.path'), '/\\');

        if (! is_dir($root)) {
            return [];
        }

        $registered = Module::query()->get(['module_id', 'path'])->keyBy('path');
        $enabled = Module::query()->where('enabled', true)->pluck('module_id')->all();
        $found = [];

        // Backups an update left behind are not modules of their own.
        $files = Finder::create()->files()->in($root)->name('module.json')->notPath('/\.replaced-\d+/');

        foreach ($files as $file) {
            $path = trim(str_replace('\\', '/', $file->getRelativePath()), '/');

            try {
                $manifest = ModuleManifest::fromJson($file->getContents());
            } catch (Throwable $exception) {
                $found[] = $this->entry($path, null, $path, null, null, self::INVALID, $exception->getMessage());

                continue;
            }

            $found[] = $this->entry($path, $manifest->id, $manifest->name, $manifest->version, $manifest->description, ...$this->status($manifest, $path, $registered->has($path), $enabled));
        }

        return collect($found)->sortBy('path')->values()->all();
    }

    /**
     * @param  array<int, string>  $enabled
     * @return array{0: string, 1: ?string}
     */
    protected function status(ModuleManifest $manifest, string $path, bool $registered, array $enabled): array
    {
        if ($registered) {
            return [self::REGISTERED, null];
        }

        if ($manifest->path !== $path) {
            return [self::INVALID, __('module.json declares the path :declared but the module is at :actual.', ['declared' => $manifest->path, 'actual' => $path])];
        }

        if (Module::query()->where('module_id', $manifest->id)->exists()) {
            return [self::BLOCKED, __('Another directory is already registered as :id.', ['id' => $manifest->id])];
        }

        if (! VersionConstraint::satisfies((string) config('modules.core_version'), $manifest->epesiCore)) {
            return [self::BLOCKED, __('Requires Epesi core :constraint.', ['constraint' => $manifest->epesiCore])];
        }

        $missing = array_values(array_diff($manifest->requires, $enabled));

        if ($missing !== []) {
            return [self::BLOCKED, __('Requires :modules, not installed and enabled.', ['modules' => implode(', ', $missing)])];
        }

        return [self::AVAILABLE, null];
    }

    /** @return array{path: string, id: ?string, name: string, version: ?string, description: ?string, status: string, problem: ?string} */
    protected function entry(string $path, ?string $id, string $name, ?string $version, ?string $description, string $status, ?string $problem): array
    {
        return compact('path', 'id', 'name', 'version', 'description', 'status', 'problem');
    }
}
