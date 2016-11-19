<?php

namespace Epesi\Console\Backup;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Ifsnop\Mysqldump\Mysqldump;

class ListBackupsCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('backup:list')
            ->setDescription('List backups');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $st = new SymfonyStyle($input, $output);
        require_once 'include/backups.php';
        $util = \BackupUtil::default_instance();
        foreach ($util->list_backups() as $backup) {
            $st->writeln(sprintf("<fg=yellow>[%s]</> <fg=green>%s</> (File: %s)", $backup->get_date('Y-m-d H:i:s'), $backup->get_description(), $backup->get_file()));
        }
    }
}
