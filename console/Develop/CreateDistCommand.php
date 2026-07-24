<?php

namespace Epesi\Console\Develop;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateDistCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('dev:dist:create')
            ->setDescription('Create a distributable EPESI package (zip) with an empty data/ directory')
            ->addArgument(
                'file',
                InputArgument::OPTIONAL,
                'Output zip file path'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $st = new SymfonyStyle($input, $output);

        $file = $input->getArgument('file');
        if (!$file) {
            $default = '../epesi-' . EPESI_VERSION . '-r' . EPESI_REVISION . '.zip';
            $file = $st->ask('Output zip file name', $default);
        }
        if (!preg_match('/\.zip$/i', $file)) {
            $file .= '.zip';
        }

        if (file_exists($file) && !$st->confirm("File \"$file\" already exists. Overwrite?", false)) {
            $st->writeln('Aborted.');
            return Command::FAILURE;
        }

        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
            $st->error("Could not create directory: $dir");
            return Command::FAILURE;
        }

        require_once 'include/backups.php';

        // RecursiveDirectoryIterator returns backslash-separated paths on Windows,
        // so the exclude regexes must match either separator, not just "/".
        $sep = '[\\\\/]';
        $exclude = array(
            '^\.git(' . $sep . '|$)',
            '^\.claude(' . $sep . '|$)',
            '^\.github(' . $sep . '|$)',
            '^\.history(' . $sep . '|$)',
            '^data' . $sep . '.+', // keep the data/ directory entry itself, drop everything inside it
        );
        // Guard against the output file landing inside the tree being archived
        // (e.g. a bare filename with no path) and trying to zip itself.
        $real_file = realpath($dir) . DIRECTORY_SEPARATOR . basename($file);
        $real_cwd = realpath('.');
        if (str_starts_with($real_file, $real_cwd . DIRECTORY_SEPARATOR)) {
            $relative = substr($real_file, strlen($real_cwd) + 1);
            $exclude[] = '^' . preg_quote($relative, '#') . '$';
        }

        $st->writeln("Building distribution package: $file");
        $archive = new \BackupArchive($file);
        $ok = $archive->create('.', $exclude);

        if (!$ok) {
            $st->error('Failed to create distribution package.');
            return Command::FAILURE;
        }

        $st->success('Created: ' . $file . ' (' . round(filesize($file) / 1024 / 1024, 1) . ' MB)');
        return Command::SUCCESS;
    }
}
