<?php

namespace App\Services\Setup;

use App\Support\Modules\ModuleManifest;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * Which modules a setup profile installs, and in what order: every core module,
 * the profile's own list, and anything those require — sorted so a module
 * always comes after what it requires (ModuleInstaller refuses otherwise).
 */
class ModulePlan
{
    /** @var array<string, ModuleManifest>|null module id => manifest */
    protected ?array $available = null;

    /**
     * @param  array<int, string>  $paths  module paths from config('setup.profiles.*.modules')
     * @return array<int, ModuleManifest> in install order
     */
    public function for(array $paths): array
    {
        $available = $this->available();
        $byPath = collect($available)->keyBy(fn (ModuleManifest $m): string => $m->path);

        $wanted = collect($available)->filter(fn (ModuleManifest $m): bool => $m->core)->keys()->all();

        foreach ($paths as $path) {
            // A module missing from disk is skipped, as FirstRun did.
            if ($byPath->has($path)) {
                $wanted[] = $byPath[$path]->id;
            }
        }

        $ordered = [];
        $visiting = [];

        foreach (array_unique($wanted) as $id) {
            $this->visit($id, $available, $ordered, $visiting);
        }

        return array_values($ordered);
    }

    /**
     * @return array<string, ModuleManifest>
     */
    public function available(): array
    {
        if ($this->available !== null) {
            return $this->available;
        }

        $path = (string) config('modules.path');
        $manifests = [];

        foreach (is_dir($path) ? Finder::create()->files()->in($path)->name('module.json')->sortByName() : [] as $file) {
            try {
                $manifest = ModuleManifest::fromJson($file->getContents());
            } catch (Throwable) {
                continue;
            }

            $manifests[$manifest->id] = $manifest;
        }

        return $this->available = $manifests;
    }

    /**
     * @param  array<string, ModuleManifest>  $available
     * @param  array<string, ModuleManifest>  $ordered
     * @param  array<string, bool>  $visiting
     */
    protected function visit(string $id, array $available, array &$ordered, array &$visiting): void
    {
        if (isset($ordered[$id]) || isset($visiting[$id])) {
            return;
        }

        $manifest = $available[$id] ?? null;

        if ($manifest === null) {
            throw new SetupException("A module requires {$id}, which is not present under modules/.");
        }

        $visiting[$id] = true;

        foreach ($manifest->requires as $required) {
            $this->visit($required, $available, $ordered, $visiting);
        }

        $ordered[$id] = $manifest;
    }
}
