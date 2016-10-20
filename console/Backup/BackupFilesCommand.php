<?php

namespace Epesi\Console\Backup;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BackupFilesCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('backup:files')
            ->setDescription('Backup EPESI files')
            ->addArgument(
                'file',
                InputArgument::OPTIONAL,
                'file or directory to backup'
            )
            ->addOption(
                'output', 'o',
                InputOption::VALUE_REQUIRED,
                'output file'
            )
            ->addOption(
                'force', 'f',
                InputOption::VALUE_NONE,
                'force overwrite backup file'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file = $input->getArgument('file');
        $file_description = 'Only ' . $file;
        if (!$file) {
            $file = '.';
            $file_description = 'All files';
        }
        $output_file = $input->getOption('output');
        $overwrite = $input->getOption('force') ? true : false;
        $st = new SymfonyStyle($input, $output);

        require_once 'include/backups.php';
        $util = \BackupUtil::default_instance();
        $description = "EPESI ver " . EPESI_VERSION . " rev " . EPESI_REVISION . ' - ' . $file_description;
        $backup = $util->create_backup($file, $description, $output_file, $overwrite);
        $st->writeln('Created backup:');
        $st->writeln(sprintf("<fg=yellow>[%s]</> <fg=green>%s</> (File: %s)", $backup->get_date('Y-m-d H:i:s'), $backup->get_description(), $backup->get_file()));
    }
}
