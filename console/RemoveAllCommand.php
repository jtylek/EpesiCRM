<?php

namespace Epesi\Console;
use DB;
use Cache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class RemoveAllCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('remove:all')
            ->setDescription('Truncate database and remove all data from EPESI')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dir = __DIR__ . '/../data/';

        if(is_dir($dir)) {
            $this->truncateDir($output, $dir);
        }
        else {
            $output->writeln('Data directory doesn\'t exist or you don\'t have required permissions');
        }
        $this->truncateDb($output);
        $output->writeln('EPESI removed. Please install application again');
    }

    private function truncateDir($output, $dir) {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            if(!is_dir("$dir/$file")) unlink("$dir/$file");
            else {
                $handle = opendir("$dir/$file");
                closedir($handle);
                exec('rm -rf '."$dir/$file");
            }
        }
        $output->writeln('Data folder cleared!');
    }

    private function truncateDb ($output) {
        Cache::clear();
        DB::Execute('SET FOREIGN_KEY_CHECKS=0;');
        foreach(DB::MetaTables() as $k => $v) {
            DB::DropTable($v);
        }
        DB::Execute('SET FOREIGN_KEY_CHECKS=1;');
        $output->writeln('Database truncated!');
    }
}
