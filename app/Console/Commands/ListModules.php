<?php

namespace App\Console\Commands;

use App\Models\Module;
use Illuminate\Console\Command;

class ListModules extends Command
{
    protected $signature = 'module:list';

    protected $description = 'List installed modules';

    public function handle(): int
    {
        $modules = Module::query()->orderBy('module_id')->get();

        if ($modules->isEmpty()) {
            $this->components->info('No modules installed.');

            return self::SUCCESS;
        }

        $this->table(
            ['Module', 'Version', 'Path', 'Panels', 'Enabled', 'Files'],
            $modules->map(fn (Module $module): array => [
                $module->module_id,
                $module->version,
                $module->path,
                implode(', ', $module->panels ?? []),
                $module->enabled ? 'yes' : 'no',
                $module->directoryExists() ? 'present' : 'MISSING',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
