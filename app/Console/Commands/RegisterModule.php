<?php

namespace App\Console\Commands;

use App\Services\Modules\ModuleException;
use App\Services\Modules\ModuleInstaller;
use Illuminate\Console\Command;

/**
 * Registers a module already sitting in modules/ — how first-party modules that
 * ship inside the core tree get their row on a fresh install, and the developer
 * loop for a module being written in place (no package/install round trip).
 */
class RegisterModule extends Command
{
    protected $signature = 'module:register {path : module directory relative to modules/, e.g. Epesi/Notes}';

    protected $description = 'Register a module whose files are already in modules/';

    public function handle(ModuleInstaller $installer): int
    {
        try {
            $module = $installer->registerExisting($this->argument('path'));
        } catch (ModuleException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Registered {$module->module_id} {$module->version} from modules/{$module->path}");

        return self::SUCCESS;
    }
}
