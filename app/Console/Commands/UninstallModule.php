<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Services\Modules\ModuleException;
use App\Services\Modules\ModuleInstaller;
use Illuminate\Console\Command;

class UninstallModule extends Command
{
    protected $signature = 'module:uninstall {module : module id, e.g. epesi/roundcube}
        {--keep-data : leave its tables, lists, history and files for a reinstall}';

    protected $description = 'Remove an installed module and, unless --keep-data, everything it put in the database';

    public function handle(ModuleInstaller $installer): int
    {
        $id = $this->argument('module');
        $module = Module::query()->where('module_id', $id)->first();

        if (! $module) {
            $this->components->error("No module installed with id {$id}.");

            return self::FAILURE;
        }

        $keep = (bool) $this->option('keep-data');

        $this->components->warn($keep
            ? "This removes modules/{$module->path}. Its tables keep their data."
            : "This removes modules/{$module->path} and deletes all of its data: tables, lists, history, links and files.");

        if (! $this->confirm("Uninstall {$id}?", false)) {
            return self::SUCCESS;
        }

        try {
            $report = $installer->uninstall($module, ! $keep);
        } catch (ModuleException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Uninstalled {$id}. ".$report->summary());

        return self::SUCCESS;
    }
}
