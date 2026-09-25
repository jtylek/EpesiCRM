<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Services\Modules\ModuleException;
use App\Services\Modules\ModuleInstaller;
use Illuminate\Console\Command;

/**
 * Shared body of module:enable and module:disable. The disable side is the
 * recovery path for a module that breaks the app at boot: run it with
 * MODULES_LOAD=false so the broken module is skipped while artisan starts up.
 */
abstract class SetModuleState extends Command
{
    abstract protected function shouldEnable(): bool;

    public function handle(ModuleInstaller $installer): int
    {
        $id = $this->argument('module');
        $module = Module::query()->where('module_id', $id)->first();

        if (! $module) {
            $this->components->error("No module installed with id {$id}.");

            return self::FAILURE;
        }

        try {
            $this->shouldEnable()
                ? $installer->enable($module)
                : $installer->disable($module);
        } catch (ModuleException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info($id.' is now '.($this->shouldEnable() ? 'enabled' : 'disabled').'.');

        return self::SUCCESS;
    }
}
