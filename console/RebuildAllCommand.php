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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RebuildAllCommand extends Command
{
    protected function configure(){
        $this
            ->setName('rebuild:all')
            ->setDescription('Rebuild EPESI cache')
        ;
    }
    protected function execute(InputInterface $input, OutputInterface $output) {
        // Theme rebuild dropped: themes are served straight from modules/*/theme[_<theme>]/
        // now, so Base_ThemeCommon::themeup() is a no-op (see its own docblock) - calling
        // it here just printed "Rebuilding themes..." for a step that did nothing.
        $output->writeln('Rebuilding cache...');
        Cache::clear();
        ModuleManager::create_common_cache();
        $output->writeln('Cache rebuilt!');

        return Command::SUCCESS;
    }
}