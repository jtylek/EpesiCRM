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
            ->setDescription('Rebuild EPESI default theme')
        ;
    }
    protected function execute(InputInterface $input, OutputInterface $output) {
        $output->writeln('Rebuilding themes...');
        Base_ThemeCommon::themeup();
        $output->writeln('Theme rebuilded!');
    }
}