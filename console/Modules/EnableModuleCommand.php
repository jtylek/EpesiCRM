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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnableModuleCommand extends Command
{
    protected function configure(){
        $this
            ->setName('module:enable')
            ->setDescription('Enable EPESI module')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Module name'
            )
        ;
    }
    protected function execute(InputInterface $input, OutputInterface $output) {
        $module_name = $input->getArgument('name');
        $module = DB::GetRow("SELECT * FROM modules WHERE name = %s",$module_name);

        if(!$module)
            throw new \Exception('Module not found');

        if ($module['state'] == ModuleManager::MODULE_ENABLED){
            $output->writeln('<fg=yellow>Module ' . $module_name . ' already enabled</fg=yellow>');
            return;
        }

        ModuleManager::set_module_state($module['name'], ModuleManager::MODULE_ENABLED);
        $output->writeln('<fg=green>Module ' . $module_name . ' enabled</fg=green>');
    }
}