<?php

namespace Epesi\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SearchClearCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('search:clear')
            ->setDescription('Clear search index')
            ->addArgument(
                'recordset',
                InputArgument::OPTIONAL,
                'Recordset name'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $st = new SymfonyStyle($input, $output);
        $recordset = $input->getArgument('recordset');
        $recordsets = \DB::GetAssoc('SELECT tab, tab FROM recordbrowser_table_properties WHERE search_include>0');
        if ($recordset && !isset($recordsets[$recordset])) {
            $st->error('Invalid recordset.');
            $st->writeln('Use one of the following:');
            $st->listing($recordsets);
            return;
        }
        $st->writeln("Clearing search index...");
        if ($recordset) {
            \Utils_RecordBrowserCommon::clear_search_index($recordset);
        } else {
            $st->progressStart(count($recordsets));
            foreach ($recordsets as $tab) {
                \Utils_RecordBrowserCommon::clear_search_index($tab);
                $st->progressAdvance();
            }
            $st->progressFinish();
        }
        $st->writeln("done!");
    }
}
