<?php

/**
 * Created by PhpStorm.
 * User: pjedwabny
 * Date: 08.09.15
 * Time: 21:10
 */
namespace Epesi\Console;
use Cache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CacheRebuildCommand extends Command
{
    protected function configure(){
        $this
            ->setName('cache:rebuild')
            ->setDescription('Clear EPESI internal cache (menus, common-method lookups, theme data)')
        ;
    }
    protected function execute(InputInterface $input, OutputInterface $output): int {
        Cache::clear();

        return Command::SUCCESS;
    }
}