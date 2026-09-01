<?php

namespace Epesi\Console\Demo;

use DB;
use ModuleManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class GenerateContactsCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('demo:generate:contacts')
            ->setDescription('Generate demo contacts')
            ->addOption('create-company', null, InputOption::VALUE_NONE, 'Create company related to contact')
            ->addOption('count', null, InputOption::VALUE_REQUIRED, 'Count of generated records')
            ->addOption('employees', null, InputOption::VALUE_REQUIRED, 'Also generate this many EMPLOYEES of your own company (contacts the Employees picker in PhoneCalls/Meetings/Tasks will accept)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        \Variable::set('anonymous_setup', 1);
        // Utils_RecordBrowserCommon::new_record() stamps created_by with
        // Acl::get_user(), which reads $_SESSION['user'] - always empty in a
        // CLI context, which then fails to bind to the created_by column's
        // %d placeholder. Run as the first superadmin (user id 1).
        \Acl::set_user(1);
        $count = (int) ($input->getOption('count') ?: 0);
        $employees = (int) ($input->getOption('employees') ?: 0);
        // A bare `demo:generate:contacts` keeps its old behaviour of one contact,
        // but `--employees=N` on its own means just employees - not N employees
        // plus a stray customer from what used to be the default count.
        if (!$count && !$employees) $count = 1;

        if ($employees && ($rc = $this->generate_employees($employees, $output)) !== Command::SUCCESS) {
            return $rc;
        }
        if (!$count) return Command::SUCCESS;

        $progress = new ProgressBar($output, $count);

        $table = new Table($output);
        $headers = array(
            '<fg=white;options=bold>Id</fg=white;options=bold>',
            '<fg=white;options=bold>First Name</fg=white;options=bold>',
            '<fg=white;options=bold>Last Name</fg=white;options=bold>'
        );

        if($input->getOption('create-company')){
            $headers[] = '<fg=white;options=bold>Company</fg=white;options=bold>';
        }

        $table->setHeaders($headers);


        // One factory for the whole run - Factory::create() rebuilds the whole
        // provider stack, and it was being called once per generated row.
        $faker = \Faker\Factory::create();
        $progress->start();
        for ($i = 0; $i < $count; $i++) {
            $values = [];
            $values['submited'] = '';
            $values['last_name'] = $faker->lastName;
            $values['first_name'] = $faker->firstName;
            $values['country'] = $faker->countryCode;
            $values['permission'] = 0;
            $values['title'] = $faker->title;
            $values['work_phone'] = $faker->phoneNumber;
            $values['mobile_phone'] = $faker->phoneNumber;
            $values['fax'] = $faker->phoneNumber;
            $values['email'] = $faker->email;
            $values['web_address'] = $faker->url;
            $values['address_1'] = $faker->streetAddress;
            $values['address_2'] = $faker->streetAddress;
            $values['city'] = $faker->city;
            $values['postal_code'] = $faker->postcode;
            $values['home_phone'] = $faker->phoneNumber;
            $values['home_address_1'] = $faker->streetAddress;
            $values['home_address_2'] = $faker->streetAddress;
            $values['home_city'] = $faker->city;
            $values['home_country'] = $faker->countryCode;
            $values['home_postal_code'] = $faker->postcode;

            $row = [$values['first_name'], $values['last_name']];



            if ($input->getOption('create-company')) {
                $values['create_company'] = 1;
                $values['create_company_name'] = $faker->company;
                $row[] = $values['create_company_name'];
            }

            $id = \Utils_RecordBrowserCommon::new_record('contact', $values);
            array_unshift($row, $id);
            $table->addRow($row);
            $progress->advance();
        }
        $progress->finish();
        $output->write('', true);
        $table->render();

        return Command::SUCCESS;
    }

    /**
     * Generate contacts that count as EMPLOYEES of the operator's own company.
     *
     * The Employees picker in PhoneCalls/Meetings/Tasks is restricted by
     * CRM_{PhoneCall,Meeting,Tasks}Common::employees_crits() to contacts whose
     * f_company_name (or f_related_companies) is your own company - see
     * AI-shared/demo-data.md. Until now nothing created those, so the three
     * activity generators hard-failed on a fresh install and you had to make
     * your own contact by hand and clone it. This fills that pool.
     *
     * Your own company is not created here: CRM_ContactsCommon::get_main_company()
     * derives it from YOUR contact's company_name (via the contact whose `login`
     * field is your user id), so there is nothing to create it from until that
     * record exists. See AI-shared/test-suite-plan.md for where that should live.
     *
     * These contacts deliberately get NO login. demo-data.md records that
     * `--create-user` was removed outright because a demo contact receiving a
     * real base_user row is a security mistake, not a cosmetic one - an employee
     * only needs the matching company_name, never an account.
     */
    private function generate_employees($count, OutputInterface $output)
    {
        $my_company = \CRM_ContactsCommon::get_main_company();
        if ($my_company <= 0) {
            $output->writeln('<error>No main company - your own contact record has no company_name, so there is nothing to make these employees OF.</error>');
            $output->writeln('Create your own company and your own contact (the one whose "login" field is your user) first, then re-run.');
            return Command::FAILURE;
        }
        $company = \Utils_RecordBrowserCommon::get_record('company', $my_company);
        $output->writeln(sprintf('Generating %d employee(s) of <info>%s</info> (company #%d)',
            $count, $company['company_name'] ?? '?', $my_company));

        $progress = new ProgressBar($output, $count);
        $table = new Table($output);
        $table->setHeaders([
            '<fg=white;options=bold>Id</fg=white;options=bold>',
            '<fg=white;options=bold>First Name</fg=white;options=bold>',
            '<fg=white;options=bold>Last Name</fg=white;options=bold>',
            '<fg=white;options=bold>Title</fg=white;options=bold>',
        ]);

        $faker = \Faker\Factory::create();
        $progress->start();
        for ($i = 0; $i < $count; $i++) {
            $values = [];
            $values['submited'] = '';
            $values['first_name'] = $faker->firstName;
            $values['last_name'] = $faker->lastName;
            // jobTitle ("Sales Manager"), not title ("Prof.") - the contact
            // recordset's Title is a free-text position field sitting between
            // Group and Work Phone (ContactsInstall.php:52), not an honorific.
            $values['title'] = $faker->jobTitle;
            // The one field that actually makes this contact an employee.
            $values['company_name'] = $my_company;
            $values['permission'] = 0;
            $values['work_phone'] = $faker->phoneNumber;
            $values['mobile_phone'] = $faker->phoneNumber;
            $values['email'] = $faker->email;

            $id = \Utils_RecordBrowserCommon::new_record('contact', $values);
            $table->addRow([$id, $values['first_name'], $values['last_name'], $values['title']]);
            $progress->advance();
        }
        $progress->finish();
        $output->write('', true);
        $table->render();

        return Command::SUCCESS;
    }
}