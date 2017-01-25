<?php

namespace Epesi\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShellCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('shell')
            ->setDescription('Run interactive shell');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        \Base_AclCommon::set_sa_user();
        \Psy\Shell::debug(get_defined_vars());
        // below not working in PHP >= 7.1
//        eval(\Psy\sh());
    }
}
