<?php

namespace Epesi\Console\Develop;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreatePatchCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('dev:module:patch')
            ->setDescription('Create EPESI module files')
            ->addArgument(
                'module',
                InputArgument::REQUIRED,
                'Module name or location'
            )
            ->addArgument(
                'patch title',
                InputArgument::REQUIRED,
                'Patch Title'
            )
            ->addOption('core', 'c', InputOption::VALUE_NONE, 'Create patch without date in filename')
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $st = new SymfonyStyle($input, $output);

        $module_name = $input->getArgument('module');
        $patch_title = $input->getArgument('patch title');
        $core = $input->getOption('core');

        $module_name = str_replace('_', '/', $module_name);
        $parts = explode('/', $module_name);
        if ($parts[0] != 'modules') {
            array_unshift($parts, 'modules');
        }
        $parts[] = 'patches';
        $path = implode('/', $parts);
        if (!file_exists($path)) {
            mkdir($path);
        }
        $filename = ($core ? '' : date('Ymd_')) . preg_replace('/[^0-9a-z_]/', '_', strtolower($patch_title)) . '.php';
        $filepath = "$path/$filename";
        if (file_exists($filepath)) {
            $st->error("Patch already exists: $filepath");
        } else {
            file_put_contents($filepath, "<?php\n\ndefined(\"_VALID_ACCESS\") || die('Direct access forbidden');\n\n");
            $st->success("Patch created: $filepath");
        }
    }
}