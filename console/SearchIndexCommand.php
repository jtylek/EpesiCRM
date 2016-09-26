<?php

namespace Epesi\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SearchIndexCommand extends Command
{

    protected function configure()
    {
        $this
            ->setName('search:index')
            ->setDescription('Index records')
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
        if ($recordset) {
            $recordsets = array($recordset);
        }
        // count total
        $st->writeln("Counting total records to index...");
        $total = $this->getTotal($recordsets);
        if (!$total) {
            $st->writeln('Nothing to index!');
            return;
        }

        $st->progressStart($total);
        do {
            $indexed = 0;
            \Utils_RecordBrowserCommon::indexer(500, $indexed);
            $st->progressAdvance($indexed);
        } while ($indexed);
        $st->progressFinish();
        $st->writeln("done!");
    }

    private function getTotal($recordsets)
    {
        $total = 0;
        foreach ($recordsets as $recordset) {
            $total += \DB::GetOne("SELECT count(*) FROM {$recordset}_data_1 WHERE active=1 AND indexed=0");
        }
        return $total;
    }
}
