<?php

namespace Epesi\Console\Modules;

use ModuleManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EnableAllModuleCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('module:enable:all')
            ->setDescription('Enable all disabled modules')
            ->addOption('missing-files', 'f', InputOption::VALUE_NONE, 'Enable only those modules that were disabled because of missing files')
            ->addOption('disabled', 'd', InputOption::VALUE_NONE, 'Enable only those modules that were disabled by user')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $by_missing_files = $input->getOption('missing-files');
        $by_user = $input->getOption('disabled');
        $cnt = 0;
        if ($by_missing_files) {
            $cnt = ModuleManager::enable_modules(ModuleManager::MODULE_NOT_FOUND);
        }
        if ($by_user) {
            $cnt = ModuleManager::enable_modules(ModuleManager::MODULE_DISABLED);
        }
        if (!$by_missing_files && !$by_user) {
            $cnt = ModuleManager::enable_modules();
        }
        $output->writeln('Modules enabled: ' . $cnt);
    }
}