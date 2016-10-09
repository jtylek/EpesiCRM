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
            ->addOption('create-user', null, InputOption::VALUE_NONE, 'Create user')
            ->addOption('create-company', null, InputOption::VALUE_NONE, 'Create company related to contact')
            ->addOption('count', null, InputOption::VALUE_REQUIRED, 'Count of generated records');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        \Variable::set('anonymous_setup', 1);
        $count = $input->getOption('count') ?: 1;

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

        if($input->getOption('create-user')) {
            $headers[] = '<fg=white;options=bold>Login</fg=white;options=bold>';
            $headers[] = '<fg=white;options=bold>Password</fg=white;options=bold>';
        }

        $table->setHeaders($headers);


        $progress->start();
        for ($i = 0; $i < $count; $i++) {
            $faker = \Faker\Factory::create();
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
            $values['birth_date'] = $faker->dateTimeBetween($startDate = '-30 years', $endDate = 'now', $timezone = date_default_timezone_get())->format('Y-m-d');

            $row = [$values['first_name'], $values['last_name']];



            if ($input->getOption('create-company')) {
                $values['create_company'] = 1;
                $values['create_company_name'] = $faker->company;
                $row[] = $values['create_company_name'];
            }

            if ($input->getOption('create-user')) {
                $values['login'] = 'new';
                $values['username'] = $faker->userName;
                $values['set_password'] = $faker->password;
                $values['confirm_password'] = $values['set_password'];
                $values['admin'] = 0;
                $row[] = $values['username'];
                $row[] = $values['set_password'];
            }

            $id = \Utils_RecordBrowserCommon::new_record('contact', $values);
            array_unshift($row, $id);
            $table->addRow($row);
            $progress->advance();
        }
        $progress->finish();
        $output->write('', true);
        $table->render();
    }
}