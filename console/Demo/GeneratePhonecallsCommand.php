<?php

namespace Epesi\Console\Demo;

use DB;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class GeneratePhonecallsCommand extends Command
{
    use BusinessHours;
    use ShortTitle;

    protected function configure()
    {
        $this
            ->setName('demo:generate:phonecalls')
            ->setDescription('Generate demo phonecalls')
            ->addOption('count', null, InputOption::VALUE_REQUIRED, 'Count of generated records')
            ->addOption('employee', null, InputOption::VALUE_REQUIRED, 'Assign only this employee (contact id or name substring) instead of a random 1-2 per record');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Utils_RecordBrowserCommon::new_record() stamps created_by with
        // Acl::get_user(), which reads $_SESSION['user'] - always empty in a
        // CLI context, which then fails to bind to the created_by column's
        // %d placeholder. Run as the real superadmin instead.
        //
        // This deliberately does NOT set anonymous_setup. It used to, and
        // never restored it - which left every install where demo data had
        // been generated permanently in "any visitor is a super-admin" mode.
        // It was never needed either: new_record() runs no ACL check at all,
        // it only reads Acl::get_user() for created_by, and set_sa_user()
        // supplies a genuine admin=2 user for anything downstream that does
        // check. See AI-private/anonymous-setup-hardening.md.
        \Acl::set_sa_user();
        if (!\Acl::get_user()) {
            $output->writeln('<error>No super-admin (user_login.admin=2) found - run the setup wizard first.</error>');
            return Command::FAILURE;
        }
        $count = $input->getOption('count') ?: 1;

        // Employees is restricted (CRM_PhoneCallCommon::employees_crits()) to
        // contacts belonging to the operator's own company - same company
        // your own contact record's company_name points at, per
        // CRM_ContactsCommon::get_main_company()/employee_crits(). Assigning
        // employees from the demo customer/company pool instead (as this
        // command used to) produces phonecalls whose "Employees" links show
        // a crossed-out eye icon in the UI - visually present but not
        // actually valid employees. This tool doesn't create employees
        // itself (see demo-data.md) - it only picks among ones that already
        // exist.
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

        $contacts = DB::GetAll('SELECT id, f_work_phone AS work_phone, f_mobile_phone AS mobile_phone, f_home_phone AS home_phone FROM contact_data_1 WHERE active=1');
        if (!$contacts) {
            $output->writeln('<error>No contacts found - run demo:generate:contacts first.</error>');
            return Command::FAILURE;
        }

        // CRM_PhoneCallCommon::display_phone() reads the 'phone' field as a
        // selector into whichever contact/company record 'customer' points
        // to (1=Mobile, 2=Work, 3=Home, 4=company's own Phone) - not a phone
        // number itself. Only offer a selector for a number the chosen
        // customer actually has, so the generated call always resolves to a
        // real number instead of the "---" display_phone() falls back to.
        $contact_phone_options = [];
        foreach ($contacts as $c) {
            $opts = [];
            if ($c['mobile_phone']) $opts[] = 1;
            if ($c['work_phone']) $opts[] = 2;
            if ($c['home_phone']) $opts[] = 3;
            if ($opts) $contact_phone_options[$c['id']] = $opts;
        }
        $companies = DB::GetAll('SELECT id, f_phone AS phone FROM company_data_1 WHERE active=1');
        $company_ids_with_phone = array_column(array_filter($companies, fn($c) => $c['phone']), 'id');

        $progress = new ProgressBar($output, $count);

        $table = new Table($output);
        $table->setHeaders([
            '<fg=white;options=bold>Id</fg=white;options=bold>',
            '<fg=white;options=bold>Subject</fg=white;options=bold>',
            '<fg=white;options=bold>Date and Time</fg=white;options=bold>',
        ]);

        $faker = \Faker\Factory::create();
        $progress->start();
        for ($i = 0; $i < $count; $i++) {
            $when = $faker->dateTimeBetween('-30 days', '+30 days');
            $employees = (array) $faker->randomElements($employee_ids, min(count($employee_ids), $faker->numberBetween(1, 2)));

            $customer = '';
            $phone = '';
            if ($contact_phone_options && (!$company_ids_with_phone || $faker->boolean(70))) {
                $cid = $faker->randomElement(array_keys($contact_phone_options));
                $customer = 'P:' . $cid;
                $phone = $faker->randomElement($contact_phone_options[$cid]);
            } elseif ($company_ids_with_phone) {
                $customer = 'C:' . $faker->randomElement($company_ids_with_phone);
                $phone = 4;
            }

            $values = [];
            $values['subject'] = $this->short_title($faker);
            $values['customer'] = $customer;
            $values['phone'] = $phone;
            $values['permission'] = $faker->randomElement([0, 1, 2]);
            $values['description'] = $faker->sentence(10);
            $values['employees'] = $employees;
            $values['status'] = $faker->randomElement([0, 1, 2, 3, 4]);
            $values['priority'] = $faker->randomElement([0, 1, 2]);
            // Faker's date, but a working-hours clock time - nobody demos 03:47 calls.
            // gmdate(), not date(): the slot is seconds-from-midnight, not a timestamp,
            // so a local timezone offset would shift every record.
            $values['date_and_time'] = $when->format('Y-m-d')
                . ' ' . gmdate('H:i:s', $this->business_hours_start($faker));

            $id = \Utils_RecordBrowserCommon::new_record('phonecall', $values);
            $table->addRow([$id, $values['subject'], $values['date_and_time']]);
            $progress->advance();
        }
        $progress->finish();
        $output->write('', true);
        $table->render();

        return Command::SUCCESS;
    }
}
