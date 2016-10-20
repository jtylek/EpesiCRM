<?php

namespace Epesi\Console\Backup;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BackupDbCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('backup:db')
            ->setDescription('Backup database')
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'backup filename'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $st = new SymfonyStyle($input, $output);
        $file = $input->getArgument('file');
        require_once 'include/backups.php';
        \BackupUtil::backup_db($file);
        $st->writeln('done');
    }
}
