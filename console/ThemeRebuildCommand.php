<?php

/**
 * Created by PhpStorm.
 * User: pjedwabny
 * Date: 08.09.15
 * Time: 21:10
 */
namespace Epesi\Console;
use Base_ThemeCommon;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ThemeRebuildCommand extends Command
{
    protected function configure(){
        $this
            ->setName('theme:rebuild')
            ->setDescription('Deprecated - themes are served from modules/ and need no rebuild')
        ;
    }
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $output->writeln('Nothing to rebuild: templates, CSS and images are now read');
        $output->writeln('directly from modules/*/theme[_<theme>]/, so an edit is live');
        $output->writeln('immediately and there is no generated copy to refresh.');

        return Command::SUCCESS;
    }
}