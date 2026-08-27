<?php

namespace Epesi\Console\Demo;

use DB;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class GenerateShoutboxCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('demo:generate:shoutbox')
            ->setDescription('Generate demo shoutbox messages between employees')
            ->addOption('count', null, InputOption::VALUE_REQUIRED, 'Count of generated records');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $input->getOption('count') ?: 1;

        $employees = DB::GetAssoc('SELECT id, login FROM user_login WHERE active=1');
        if (count($employees) < 2) {
            $output->writeln('<error>Need at least 2 active user_login accounts to generate messages between employees.</error>');
            return Command::FAILURE;
        }
        $employee_ids = array_keys($employees);

        $progress = new ProgressBar($output, $count);

        $table = new Table($output);
        $table->setHeaders([
            '<fg=white;options=bold>Id</fg=white;options=bold>',
            '<fg=white;options=bold>From</fg=white;options=bold>',
            '<fg=white;options=bold>To</fg=white;options=bold>',
            '<fg=white;options=bold>Posted On</fg=white;options=bold>',
        ]);

        $faker = \Faker\Factory::create();
        $progress->start();
        for ($i = 0; $i < $count; $i++) {
            $from = $faker->randomElement($employee_ids);
            $to = $faker->randomElement(array_diff($employee_ids, [$from]));
            // format_message() runs stored text through BBCode parsing, matching the
            // real posting path (Shoutbox_0.php) which htmlspecialchars()-escapes
            // before insert.
            $message = htmlspecialchars(rtrim($faker->sentence($faker->numberBetween(3, 12)), '.'), ENT_QUOTES, 'UTF-8');
            $posted_on = $faker->dateTimeBetween('-30 days', 'now')->getTimestamp();

            DB::Execute(
                'INSERT INTO apps_shoutbox_messages(message,base_user_login_id,to_user_login_id,posted_on) VALUES(%s,%d,%d,%T)',
                [$message, $from, $to, $posted_on]
            );
            $id = DB::Insert_ID('apps_shoutbox_messages', 'id');

            $table->addRow([$id, $employees[$from], $employees[$to], date('Y-m-d H:i:s', $posted_on)]);
            $progress->advance();
        }
        $progress->finish();
        $output->write('', true);
        $table->render();

        return Command::SUCCESS;
    }
}
