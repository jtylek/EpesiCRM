<?php

namespace App\Services\Modules;

use App\Models\Module;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleRegistry;
use App\Support\Modules\VersionConstraint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * Installs, enables, disables and removes zip-distributed modules.
 *
 * Deliberately pure PHP — ZipArchive, file moves, Artisan::call() — with no
 * shell out and no package registry, so it works on hosting where neither is
 * available and can run inside the request instead of needing a queue worker
 * (this app's QUEUE_CONNECTION is `database`, so a queued job would sit
 * untouched until someone runs one).
 */
class ModuleInstaller
{
    public function installFromZip(string $zipPath, bool $allowUpdate = false): Module
    {
        $archive = new ModuleArchive($zipPath);
        $manifest = $archive->manifest();

        $this->assertCoreVersion($manifest);
        $this->assertRequirements($manifest);

        $existing = Module::query()->where('module_id', $manifest->id)->first();
        $target = Module::directoryFor($manifest->path);

        if ($existing && ! $allowUpdate) {
            throw new ModuleException("{$manifest->id} is already installed (version {$existing->version}).");
        }

        // A directory with no module row of its own is leftover files — except
        // when it is only there as the parent of modules that *are* registered
        // (installing Epesi/CRM after Epesi/CRM/Contacts), which is legitimate.
        if (! $existing && is_dir($target) && $this->nestedModulePaths($manifest->path) === []) {
            throw new ModuleException("The directory {$manifest->path} already exists but no module is registered for it — remove it first.");
        }

        // Children living inside this module's directory would go into the
        // backup with it and be deleted along with it — silent loss of another
        // module's files. Their paths are captured before anything moves, and
        // they are lifted back out of the backup once the new version is in
        // place.
        $nested = $this->nestedModulePaths($manifest->path);

        $staging = rtrim((string) config('modules.staging_path'), '/\\').DIRECTORY_SEPARATOR.Str::random(12);
        $backup = null;

        try {
            File::ensureDirectoryExists($staging);
            $staged = $archive->extractTo($staging);
            $archive->close();

            if (is_dir($target)) {
                $backup = $target.'.replaced-'.time();
                $this->moveDirectory($target, $backup);
            }

            File::ensureDirectoryExists(dirname($target));
            $this->moveDirectory($staged, $target);

            if ($backup) {
                $this->restoreNested($backup, $target, $nested);
            }

            try {
                $this->runMigrations($target);
            } catch (Throwable $exception) {
                $this->restore($target, $backup, $nested);

                throw new ModuleException('The module was not installed — its migrations failed: '.$exception->getMessage(), previous: $exception);
            }

            $module = $this->record($manifest, $existing);

            $this->afterChange();

            if ($backup) {
                File::deleteDirectory($backup);
            }

            return $module;
        } finally {
            $archive->close();
            File::deleteDirectory($staging);
        }
    }

    /**
     * Registers a module whose files are already in place — a first-party module
     * shipped inside the core tree, a directory unpacked by hand on hosting where
     * the web user can't write, or a lost row whose files survived. This is how
     * a module gets a `modules` row without a zip ever being involved.
     */
    public function registerExisting(string $path): Module
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        $directory = Module::directoryFor($path);
        $manifestFile = $directory.DIRECTORY_SEPARATOR.'module.json';

        if (! is_file($manifestFile)) {
            throw new ModuleException("No module.json found in modules/{$path}.");
        }

        $manifest = ModuleManifest::fromJson((string) file_get_contents($manifestFile));

        if ($manifest->path !== $path) {
            throw new ModuleException("module.json declares path \"{$manifest->path}\" but the module is at \"{$path}\".");
        }

        $this->assertCoreVersion($manifest);
        $this->assertRequirements($manifest);

        $this->runMigrations($directory);

        $module = $this->record($manifest, Module::query()->where('module_id', $manifest->id)->first());

        $this->afterChange();

        return $module;
    }

    public function enable(Module $module): void
    {
        if (! $module->directoryExists()) {
            throw new ModuleException("{$module->module_id} cannot be enabled — its files are missing from {$module->path}.");
        }

        $module->update(['enabled' => true]);

        $this->afterChange();
    }

    public function disable(Module $module): void
    {
        $this->assertNotCore($module, 'disabled');
        $this->assertNothingDependsOn($module, 'Disabling', enabledOnly: true);

        $module->update(['enabled' => false]);

        $this->afterChange();
    }

    /**
     * Removes the module's files and its registry row. Its database tables are
     * left alone on purpose: a module's `down()` migrations are rarely written
     * with real data in mind, and silently dropping tables holding a customer's
     * records is worse than leaving them behind. Old Epesi never had a real
     * undo here either.
     */
    public function uninstall(Module $module): void
    {
        $this->assertNotCore($module, 'uninstalled');
        $this->assertNothingDependsOn($module, 'Uninstalling', enabledOnly: false);

        // deleteDirectory() takes the whole subtree. A module nested inside this
        // one would lose its files while keeping its `modules` row, which then
        // refuses to enable ("its files are missing") with nothing to point at
        // what happened. Refuse instead and let the caller uninstall inside-out.
        $nested = $module->nestedModules();

        if ($nested->isNotEmpty()) {
            throw new ModuleException(
                "{$module->module_id} cannot be uninstalled — these modules live inside it: "
                .$nested->pluck('module_id')->implode(', ').'. Uninstall them first.',
            );
        }

        $directory = $module->directory();

        $module->delete();

        if (is_dir($directory)) {
            File::deleteDirectory($directory);
        }

        $this->pruneEmptyParents($directory);

        $this->afterChange();
    }

    /**
     * A module the app itself is built on. `ModuleServiceProvider` registers
     * PSR-4 only for enabled modules, so disabling one whose classes core code
     * extends fatals the very next request — before any screen could explain
     * why. There is no undo from the GUI at that point.
     */
    protected function assertNotCore(Module $module, string $verb): void
    {
        if ($module->isCore()) {
            throw new ModuleException("{$module->module_id} is a core module and cannot be {$verb}.");
        }
    }

    protected function assertNothingDependsOn(Module $module, string $action, bool $enabledOnly): void
    {
        $dependents = $module->dependents($enabledOnly);

        if ($dependents->isNotEmpty()) {
            throw new ModuleException(
                "{$action} {$module->module_id} would break "
                .$dependents->pluck('module_id')->implode(', ').', which require it.',
            );
        }
    }

    /**
     * Grouping directories ("Epesi/CRM") are created implicitly by an install
     * and own nothing, so removing the last module under one should leave no
     * empty shell behind. Walks up while each level is empty, stopping at
     * modules/ itself.
     */
    protected function pruneEmptyParents(string $directory): void
    {
        $root = realpath((string) config('modules.path')) ?: rtrim((string) config('modules.path'), '/\\');

        for ($parent = dirname($directory); ; $parent = dirname($parent)) {
            if (! is_dir($parent) || $parent === dirname($parent)) {
                return;
            }

            if ((realpath($parent) ?: $parent) === $root || ! File::isEmptyDirectory($parent)) {
                return;
            }

            File::deleteDirectory($parent);
        }
    }

    /**
     * Paths of registered modules living inside $path, relative to it.
     *
     * @return array<int, string>
     */
    protected function nestedModulePaths(string $path): array
    {
        return Module::query()
            ->where('path', 'like', $path.'/%')
            ->orderBy('path')
            ->pluck('path')
            ->map(fn (string $nested): string => substr($nested, strlen($path) + 1))
            ->all();
    }

    /**
     * Lifts nested child modules out of the replaced version's backup and back
     * into the freshly installed parent, so a parent's update leaves its
     * children where they were.
     *
     * @param  array<int, string>  $nested  child paths relative to the module directory
     */
    protected function restoreNested(string $backup, string $target, array $nested): void
    {
        foreach ($nested as $relative) {
            $separator = DIRECTORY_SEPARATOR;
            $from = $backup.$separator.str_replace('/', $separator, $relative);
            $to = $target.$separator.str_replace('/', $separator, $relative);

            if (! is_dir($from) || is_dir($to)) {
                continue;
            }

            File::ensureDirectoryExists(dirname($to));
            $this->moveDirectory($from, $to);
        }
    }

    protected function assertCoreVersion(ModuleManifest $manifest): void
    {
        $core = (string) config('modules.core_version');

        if (! VersionConstraint::satisfies($core, $manifest->epesiCore)) {
            throw new ModuleException("{$manifest->id} requires Epesi core {$manifest->epesiCore}; this installation is {$core}.");
        }
    }

    protected function assertRequirements(ModuleManifest $manifest): void
    {
        foreach ($manifest->requires as $required) {
            $installed = Module::query()->where('module_id', $required)->where('enabled', true)->exists();

            if (! $installed) {
                throw new ModuleException("{$manifest->id} requires the module {$required}, which is not installed and enabled.");
            }
        }
    }

    protected function runMigrations(string $moduleDirectory): void
    {
        $path = $moduleDirectory.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';

        if (! is_dir($path)) {
            return;
        }

        Artisan::call('migrate', [
            '--path' => $path,
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    protected function record(ModuleManifest $manifest, ?Module $existing): Module
    {
        $attributes = $manifest->toDatabaseRow() + [
            'enabled' => true,
            'installed_at' => $existing?->installed_at ?? now(),
        ];

        if ($existing) {
            $existing->update($attributes);

            return $existing;
        }

        return Module::create($attributes);
    }

    /**
     * Undo a failed install: drop whatever was moved into place and put the
     * previous version back.
     *
     * Nested children were already lifted from the backup into the new target,
     * so they have to go back into the backup first — the delete below takes the
     * whole subtree.
     *
     * @param  array<int, string>  $nested  child paths relative to the module directory
     */
    protected function restore(string $target, ?string $backup, array $nested = []): void
    {
        if ($backup && is_dir($backup)) {
            $this->restoreNested($target, $backup, $nested);
        }

        File::deleteDirectory($target);

        if ($backup && is_dir($backup)) {
            $this->moveDirectory($backup, $target);
        }
    }

    protected function moveDirectory(string $from, string $to): void
    {
        if (File::moveDirectory($from, $to)) {
            return;
        }

        // rename() fails across volumes (staging lives under storage/), so fall
        // back to a copy rather than failing the install.
        if (! File::copyDirectory($from, $to)) {
            throw new ModuleException("Could not move module files into {$to}.");
        }

        File::deleteDirectory($from);
    }

    /**
     * Caches that would otherwise hide a module that just appeared or vanished.
     *
     * Note what is *not* here: `shield:generate`. Filament panels are built
     * during service-provider registration, so the panel object in this process
     * predates the module — generating permissions now would enumerate
     * everything except the module just installed. It's an explicit action on
     * the Modules screen instead, which runs on a later request.
     */
    protected function afterChange(): void
    {
        ModuleRegistry::refresh();

        foreach (['config:clear', 'route:clear', 'view:clear', 'filament:optimize-clear'] as $command) {
            try {
                Artisan::call($command);
            } catch (Throwable) {
                // A cache that can't be cleared shouldn't fail the install; the
                // worst case is a stale panel until the next deploy clears it.
            }
        }
    }
}
