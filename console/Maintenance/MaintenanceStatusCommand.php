<?php

/**
 * Created by PhpStorm.
 * User: pjedwabny
 * Date: 08.09.15
 * Time: 21:10
 */
namespace Epesi\Console\Maintenance;
use DB;
use MaintenanceMode;
use ModuleManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MaintenanceStatusCommand extends Command
{
    protected function configure(){
        $this
            ->setName('maintenance:status')
            ->setDescription('Get status of EPESI maintenance mode')
        ;
    }
    protected function execute(InputInterface $input, OutputInterface $output) {
        $status = MaintenanceMode::is_on() ? '<fg=green>enabled</fg=green>' : '<fg=red>disabled</fg=red>';
        $output->writeln("Maintenance mode status: $status");
    }
}