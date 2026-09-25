<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Services\Modules\ModuleException;
use App\Services\Modules\ModuleInstaller;
use Illuminate\Console\Command;

class UninstallModule extends Command
{
    protected $signature = 'module:uninstall {module : module id, e.g. epesi/notes}';

    protected $description = 'Remove an installed module (its database tables are left alone)';

    public function handle(ModuleInstaller $installer): int
    {
        $id = $this->argument('module');
        $module = Module::query()->where('module_id', $id)->first();

        if (! $module) {
            $this->components->error("No module installed with id {$id}.");

            return self::FAILURE;
        }

        $this->components->warn("This removes modules/{$module->path}. Any tables the module created keep their data.");

        if (! $this->confirm("Uninstall {$id}?", false)) {
            return self::SUCCESS;
        }

        try {
            $installer->uninstall($module);
        } catch (ModuleException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Uninstalled {$id}.");

        return self::SUCCESS;
    }
}
