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
                'Full path to the output zip file'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $st = new SymfonyStyle($input, $output);

        $file = $input->getArgument('file');
        if (!$file) {
            $default = dirname(getcwd()) . DIRECTORY_SEPARATOR . 'epesi-' . EPESI_VERSION . '-r' . EPESI_REVISION . '.zip';
            $file = $st->ask('Full path to output zip file (missing directories will be created)', $default);
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
        // $sep is a complete alternative ("...(sep|$)"); $sep_chars is just the
        // characters, for embedding inside a [^...] class - nesting $sep's own
        // brackets inside another [^...] produces a malformed, silently-never-
        // matching regex.
        $sep = '[\\\\/]';
        $sep_chars = '\\\\/';
        $exclude = array(
            '^\.git(' . $sep . '|$)',
            '^\.claude(' . $sep . '|$)',
            '^\.github(' . $sep . '|$)',
            '^\.history(' . $sep . '|$)',
            '^data' . $sep . '.+', // keep the data/ directory entry itself, drop everything inside it
            '^temp(' . $sep . '|$)', // Smarty compile/cache/config output (see TEMP_DIR) - regenerated on first request
            '^[^' . $sep_chars . ']+\.zip$', // any leftover distribution/test zip sitting at the project root
            '^(?!README\.md$)[^' . $sep_chars . ']+\.md$', // root-level docs other than README.md
            // root-level dev/CI tooling that has no business in a runtime distribution.
            // NOTE: htaccess.txt is intentionally NOT excluded - setup.php's own
            // check_htaccess() copies it to build data/.htaccess during install
            // (copy('htaccess.txt','data/.htaccess')), so removing it would break
            // that step. .htaccess itself (this dev instance's own, already-tuned
            // copy) IS excluded, since setup.php generates the real one fresh.
            '^(\.htaccess|\.gitignore|debug\.php|PEAR\.php|phpstan.*|playbook\.yml|rector.*)$',
            // Static-analysis tooling (tools/composer.json + tools/vendor/, see that
            // file). Same reasoning as phpstan.*/rector.* above - the configs were
            // already excluded, so leaving the tools they configure in the package
            // would have been inconsistent. Excluded whether or not tools/vendor/ is
            // committed, so the gitignore decision can be revisited without this
            // needing to change.
            '^tools(' . $sep . '|$)',
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
