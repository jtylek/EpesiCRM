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

class InstallModuleCommand extends Command
{
    protected function configure(){
        $this
            ->setName('module:install')
            ->setDescription('Install EPESI module')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Module name'
            )
        ;
    }
    protected function execute(InputInterface $input, OutputInterface $output) {
        $module_name = $input->getArgument('name');
        \Base_SetupCommon::refresh_available_modules();
        $module = DB::GetRow("SELECT * FROM available_modules WHERE name = %s",$module_name);
        if(!$module)
            throw new \Exception('Module not found');

        if (ModuleManager::is_installed($module['name']) !== -1) {
            $output->writeln('<fg=yellow>Module ' . $module_name . ' already installed</fg=yellow>');
            return;
        }

        if (ModuleManager::install($module['name'])) {
            $output->writeln('<fg=green>Module ' . $module_name . ' installed</fg=green>');
        } else {
            $output->writeln('<fg=red>Module ' . $module_name . ' installing error</fg=red>');
        }
    }
}