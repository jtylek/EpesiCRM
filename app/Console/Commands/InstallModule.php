<?php

namespace App\Console\Commands;

use App\Services\Modules\ModuleException;
use App\Services\Modules\ModuleInstaller;
use Illuminate\Console\Command;

/**
 * The CLI half of the install pipeline the Modules admin screen drives — and
 * the fallback when modules/ isn't writable by the web user, or when the
 * install action is switched off in config.
 */
class InstallModule extends Command
{
    protected $signature = 'module:install
        {zip : path to the module .zip}
        {--update : replace an already-installed module of the same id}';

    protected $description = 'Install a module from a zip archive';

    public function handle(ModuleInstaller $installer): int
    {
        $zip = $this->argument('zip');

        try {
            $module = $installer->installFromZip($zip, (bool) $this->option('update'));
        } catch (ModuleException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Installed {$module->module_id} {$module->version} into modules/{$module->path}");

        if ($module->plugin_class) {
            $commands = collect($module->panels ?? [])
                ->map(fn (string $panel): string => "`php artisan shield:generate --all --option=permissions --panel={$panel}`")
                ->implode(' and ');

            $this->components->warn("Run {$commands} if roles other than super_admin need access to its screens.");
        }

        return self::SUCCESS;
    }
}
