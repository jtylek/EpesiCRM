<?php

namespace Epesi\Console\Demo;

use DB;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class GenerateMeetingsCommand extends Command
{
    use BusinessHours;
    use ShortTitle;

    /** Meeting lengths, in seconds: 1h to 3h in half-hour steps. */
    const DURATIONS = [3600, 5400, 7200, 9000, 10800];

    protected function configure()
    {
        $this
            ->setName('demo:generate:meetings')
            ->setDescription('Generate demo meetings')
            ->addOption('count', null, InputOption::VALUE_REQUIRED, 'Count of generated records')
            ->addOption('employee', null, InputOption::VALUE_REQUIRED, 'Assign only this employee (contact id or name substring) instead of a random 1-2 per record');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        \Variable::set('anonymous_setup', 1);
        // Utils_RecordBrowserCommon::new_record() stamps created_by with
        // Acl::get_user(), which reads $_SESSION['user'] - always empty in a
        // CLI context, which then fails to bind to the created_by column's
        // %d placeholder. Run as the first superadmin (user id 1).
        \Acl::set_user(1);
        $count = $input->getOption('count') ?: 1;

        // Employees is restricted (CRM_MeetingCommon::employees_crits()) to
        // contacts belonging to the operator's own company - same company
        // your own contact record's company_name points at, per
        // CRM_ContactsCommon::get_main_company()/employee_crits(). Assigning
        // employees from the demo customer/company pool instead (as this
        // command used to) produces meetings whose "Employees" links show a
        // crossed-out eye icon in the UI - visually present but not actually
        // valid employees. This tool doesn't create employees itself (see
        // demo-data.md) - it only picks among ones that already exist.
        $my_company = \CRM_ContactsCommon::get_main_company();
        $employee_ids = $my_company > 0
            ? DB::GetCol('SELECT id FROM contact_data_1 WHERE active=1 AND (f_company_name=%d OR f_related_companies LIKE %s)', array($my_company, '%__' . $my_company . '__%'))
            : [];
        if (!$employee_ids) {
            $output->writeln('<error>No employees found for your company - set up your own contact/company and clone it to add employees before generating demo data.</error>');
            return Command::FAILURE;
        }

        if ($employee = $input->getOption('employee')) {
            $matches = is_numeric($employee)
                ? array_intersect($employee_ids, [(int) $employee])
                : DB::GetCol(
                    "SELECT id FROM contact_data_1 WHERE id IN (" . implode(',', $employee_ids) . ") AND (f_first_name LIKE %s OR f_last_name LIKE %s OR CONCAT(f_first_name,' ',f_last_name) LIKE %s)",
                    ['%' . $employee . '%', '%' . $employee . '%', '%' . $employee . '%']
                );
            if (count($matches) !== 1) {
                $output->writeln('<error>' . (count($matches) === 0 ? "No employee matching \"$employee\"." : "\"$employee\" matches more than one employee - be more specific.") . '</error>');
                return Command::FAILURE;
            }
            $employee_ids = array_values($matches);
        }

        $contact_ids = DB::GetCol('SELECT id FROM contact_data_1 WHERE active=1');
        if (!$contact_ids) {
            $output->writeln('<error>No contacts found - run demo:generate:contacts first.</error>');
            return Command::FAILURE;
        }
        $company_ids = DB::GetCol('SELECT id FROM company_data_1 WHERE active=1');
        $customer_pool = array_merge(
            array_map(fn($id) => 'P:' . $id, $contact_ids),
            array_map(fn($id) => 'C:' . $id, $company_ids)
        );

        $progress = new ProgressBar($output, $count);

        $table = new Table($output);
        $table->setHeaders([
            '<fg=white;options=bold>Id</fg=white;options=bold>',
            '<fg=white;options=bold>Title</fg=white;options=bold>',
            '<fg=white;options=bold>Date</fg=white;options=bold>',
            '<fg=white;options=bold>Start</fg=white;options=bold>',
            '<fg=white;options=bold>End</fg=white;options=bold>',
        ]);

        $faker = \Faker\Factory::create();
        $progress->start();
        for ($i = 0; $i < $count; $i++) {
            $when = $faker->dateTimeBetween('-30 days', '+30 days');
            $employees = (array) $faker->randomElements($employee_ids, min(count($employee_ids), $faker->numberBetween(1, 2)));
            $customers = $customer_pool ? (array) $faker->randomElements($customer_pool, min(count($customer_pool), $faker->numberBetween(0, 2))) : [];

            // Duration is picked first because it constrains how late the meeting can
            // start: the window is what the whole meeting has to fit inside, so a 3h
            // meeting can start no later than 17:00 for a 20:00 day_end.
            $duration = $faker->randomElement(self::DURATIONS);
            $start = $this->business_hours_start($faker, $duration);

            $values = [];
            $values['title'] = $this->short_title($faker);
            $values['date'] = $when->format('Y-m-d');
            // gmdate(), not date(): $start is seconds-from-midnight, not a real
            // timestamp, so a local timezone offset would shift every meeting.
            $values['time'] = '1970-01-01 ' . gmdate('H:i:s', $start);
            $values['duration'] = $duration;
            $values['description'] = $faker->sentence(10);
            $values['employees'] = $employees;
            $values['customers'] = $customers;
            $values['status'] = $faker->randomElement([0, 1, 2, 3, 4]);
            $values['priority'] = $faker->randomElement([0, 1, 2]);
            $values['permission'] = $faker->randomElement([0, 1, 2]);

            $id = \Utils_RecordBrowserCommon::new_record('crm_meeting', $values);
            // Start/End columns make the DAY_START..DAY_END window verifiable by eye.
            $table->addRow([
                $id, $values['title'], $values['date'],
                gmdate('H:i', $start), gmdate('H:i', $start + $duration),
            ]);
            $progress->advance();
        }
        $progress->finish();
        $output->write('', true);
        $table->render();

        return Command::SUCCESS;
    }
}
