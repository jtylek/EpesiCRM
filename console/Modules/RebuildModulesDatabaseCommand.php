<?php

namespace Epesi\Console\Modules;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RebuildModulesDatabaseCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('module:rebuild')
            ->setDescription('Rebuild modules database')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $modules = \Base_SetupCommon::refresh_available_modules();

        $table = new Table($output);
        $table->setHeaders(array('<fg=white;options=bold>Name</fg=white;options=bold>', '<fg=white;options=bold>Version</fg=white;options=bold>'));
        foreach ($modules as $name => $module) {
            $table->addRow(array($name, $module[0]));
        }

        $table->render();
    }
}