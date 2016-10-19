<?php

/**
 * Created by PhpStorm.
 * User: pjedwabny
 * Date: 08.09.15
 * Time: 21:10
 */
namespace Epesi\Console\Modules;

use DB;
use ModuleManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListModulesCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('module:list')
            ->setDescription('List EPESI modules')
            ->addOption('installed-only', 'i');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('installed-only'))
            $modules = DB::GetAssoc('SELECT available_modules.name, available_modules.version, modules.state FROM available_modules LEFT JOIN modules ON modules.name=available_modules.name where state is NOT NULL');
        else
            $modules = DB::GetAssoc('SELECT available_modules.name, available_modules.version, modules.state FROM available_modules LEFT JOIN modules ON modules.name=available_modules.name');

        $table = new Table($output);
        $table->setHeaders(array('<fg=white;options=bold>Name</fg=white;options=bold>', '<fg=white;options=bold>Version</fg=white;options=bold>', '<fg=white;options=bold>State</fg=white;options=bold>'));
        foreach ($modules as $module) {

            if ($module['state'] === (string)ModuleManager::MODULE_ENABLED)
                $state = "<fg=green>Active</fg=green>";
            if ($module['state'] === (string)ModuleManager::MODULE_DISABLED)
                $state = "<fg=yellow>Inactive</fg=yellow>";
            if ($module['state'] === null)
                $state = "<fg=red>Not installed</fg=red>";

            $table->addRow(array($module['name'], $module['version'], $state));
        }

        $table->render();
    }
}