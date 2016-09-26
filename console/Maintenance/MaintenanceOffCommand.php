<?php

namespace Epesi\Console\Maintenance;
use MaintenanceMode;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MaintenanceOffCommand extends Command
{
    protected function configure(){
        $this
            ->setName('maintenance:off')
            ->setDescription('Turn off EPESI maintenance mode')
        ;
    }
    protected function execute(InputInterface $input, OutputInterface $output) {
        MaintenanceMode::turn_off();
        $output->writeln("Turned off");
    }
}
