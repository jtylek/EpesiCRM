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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UninstallModuleCommand extends Command
{
    protected function configure(){
        $this
            ->setName('module:uninstall')
            ->setDescription('Uninstall EPESI module')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Module name'
            )
        ;
    }
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $module_name = $input->getArgument('name');
        $module = DB::GetRow("SELECT * FROM available_modules WHERE name = %s",$module_name);
        if(!$module)
            throw new \Exception('Module not found');

        if (ModuleManager::is_installed($module['name']) < 0) {
            $output->writeln('<fg=yellow>Module ' . $module_name . ' not installed</fg=yellow>');
            return Command::SUCCESS;
        }

        if (ModuleManager::uninstall($module['name'])) {
            $output->writeln('<fg=green>Module ' . $module_name . ' uninstalled</fg=green>');
            return Command::SUCCESS;
        }

        $output->writeln('<fg=red>Module ' . $module_name . ' uninstalling error</fg=red>');
        return Command::FAILURE;
    }
}