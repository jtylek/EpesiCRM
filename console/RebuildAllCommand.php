<?php

/**
 * Created by PhpStorm.
 * User: pjedwabny
 * Date: 08.09.15
 * Time: 21:10
 */
namespace Epesi\Console;
use Cache;
use ModuleManager;
use Base_ThemeCommon;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RebuildAllCommand extends Command
{
    protected function configure(){
        $this
            ->setName('rebuild:all')
            ->setDescription('Rebuild EPESI default theme and cache')
        ;
    }
    protected function execute(InputInterface $input, OutputInterface $output) {
        $output->writeln('Rebuilding themes...');
        Base_ThemeCommon::themeup();
        $output->writeln('Theme rebuilt! Rebuilding cache...');
        Cache::clear();
        ModuleManager::create_common_cache();
        $output->writeln('Cache rebuilt!');
    }
}