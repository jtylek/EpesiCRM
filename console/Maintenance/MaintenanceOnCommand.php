<?php

namespace Epesi\Console\Maintenance;
use MaintenanceMode;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MaintenanceOnCommand extends Command
{
    protected function configure(){
        $this
            ->setName('maintenance:on')
            ->setDescription('Turn on EPESI maintenance mode')
            ->addArgument(
                'message',
                InputArgument::OPTIONAL,
                'Message reported to the user'
            )
        ;
    }
    protected function execute(InputInterface $input, OutputInterface $output) {
        $message = $input->getArgument('message');
        MaintenanceMode::turn_on($message);
        $output->writeln("Turned on " . ($message ? "with message: $message" : "with default message"));
    }
}
