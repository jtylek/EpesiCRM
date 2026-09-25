<?php

namespace Database\Seeders;

use App\Enums\RecordPermission;
use App\Enums\RecordPriority;
use App\Enums\RecordStatus;
use App\Models\User;
use App\Support\DemoData;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * The demo records: an own company, a manager and an employee (both with the
 * password "password"), customers, a vendor, and calls, tasks and meetings
 * spread across permission levels and creators, so the ownership scope in
 * HasOwnershipVisibility has something real to filter — then generated ones
 * up to 100 companies, 100 contacts, 30 tasks, calls and meetings, and a
 * shoutbox conversation. Every row is remembered (App\Support\DemoData), so
 * Administration → Demo data can remove it all again.
 *
 * Run by DatabaseSeeder for development, and by the setup wizard's "load demo
 * data" option with the administrator the wizard just created.
 */
class DemoDataSeeder extends Seeder
{
    /** Generated on top of the hand-written ones: 100 companies, 100 contacts, 30 of each activity. */
    protected const COMPANIES = 97;

    protected const CONTACTS = 96;

    protected const ACTIVITIES = 28;

    /** [city, country code, phone prefix] */
    protected const CITIES = [
        ['New York', 'US', '+1'], ['Chicago', 'US', '+1'], ['Austin', 'US', '+1'], ['Seattle', 'US', '+1'], ['Boston', 'US', '+1'],
        ['Denver', 'US', '+1'], ['Toronto', 'CA', '+1'], ['London', 'GB', '+44'], ['Manchester', 'GB', '+44'], ['Dublin', 'IE', '+353'],
        ['Warszawa', 'PL', '+48'], ['Kraków', 'PL', '+48'], ['Wrocław', 'PL', '+48'], ['Gdańsk', 'PL', '+48'], ['Poznań', 'PL', '+48'],
        ['Berlin', 'DE', '+49'], ['München', 'DE', '+49'], ['Hamburg', 'DE', '+49'], ['Wien', 'AT', '+43'], ['Praha', 'CZ', '+420'],
        ['Paris', 'FR', '+33'], ['Lyon', 'FR', '+33'], ['Madrid', 'ES', '+34'], ['Milano', 'IT', '+39'], ['Amsterdam', 'NL', '+31'],
    ];

    protected const COMPANY_WORDS = [
        'Blue', 'Northern', 'Silver', 'Green', 'Golden', 'Red', 'Bright', 'Summit', 'Harbor', 'Pioneer', 'Crystal', 'Maple',
        'Eastern', 'Granite', 'Swift', 'Clear', 'Noble', 'Prime', 'Urban', 'Coastal', 'Alpine', 'Evergreen', 'Iron', 'Oak',
    ];

    protected const COMPANY_NOUNS = [
        'River', 'Peak', 'Bridge', 'Field', 'Lake', 'Stone', 'Valley', 'Harbor', 'Forest', 'Point', 'Ridge', 'Gate',
        'Arrow', 'Anchor', 'Crown', 'Falcon', 'Lantern', 'Compass', 'Beacon', 'Meadow',
    ];

    protected const COMPANY_KINDS = [
        'Logistics', 'Consulting', 'Foods', 'Software', 'Builders', 'Media', 'Energy', 'Health', 'Travel', 'Furniture',
        'Printing', 'Motors', 'Textiles', 'Engineering', 'Design', 'Security', 'Supplies', 'Analytics', 'Labs', 'Partners',
    ];

    protected const STREETS = [
        'Main Street', 'Market Street', 'Park Avenue', 'Oak Lane', 'River Road', 'High Street', 'Station Road', 'ul. Długa',
        'ul. Krakowska', 'ul. Mickiewicza', 'Hauptstraße', 'Bahnhofstraße', 'Rue de la Paix', 'Calle Mayor', 'Via Roma',
    ];

    protected const FIRST_NAMES = [
        'Anna', 'Piotr', 'Katarzyna', 'Tomasz', 'Magdalena', 'Łukasz', 'Agnieszka', 'Michał', 'Joanna', 'Paweł',
        'Emma', 'Liam', 'Olivia', 'Noah', 'Sophia', 'James', 'Isabella', 'Lucas', 'Mia', 'Ethan',
        'Hannah', 'Jonas', 'Lena', 'Felix', 'Clara', 'Chloé', 'Louis', 'Lucía', 'Marco', 'Giulia', 'Daan', 'Sarah',
    ];

    protected const LAST_NAMES = [
        'Nowak', 'Kowalski', 'Wiśniewska', 'Wójcik', 'Kamińska', 'Lewandowski', 'Zielińska', 'Szymański', 'Dąbrowski', 'Król',
        'Smith', 'Johnson', 'Brown', 'Taylor', 'Miller', 'Wilson', 'Clark', 'Walker', 'Young', 'Hall',
        'Müller', 'Schmidt', 'Fischer', 'Weber', 'Dubois', 'Martin', 'García', 'Rossi', 'Bianchi', 'de Vries', "O'Brien", 'Novák',
    ];

    protected const JOB_TITLES = [
        'CEO', 'CFO', 'Sales Manager', 'Office Manager', 'Purchasing Manager', 'Accountant', 'Project Manager',
        'IT Administrator', 'Marketing Specialist', 'Operations Director', 'Account Manager', 'Assistant', 'Owner', 'Engineer',
    ];

    protected const TASK_TITLES = [
        'Send the quote to {company}', 'Prepare the contract for {company}', 'Follow up with {contact}', 'Check the invoice from {company}',
        'Update the price list for {company}', 'Schedule the demo for {contact}', 'Collect feedback from {contact}',
        'Renew the support agreement with {company}', 'Send the product catalogue to {contact}', 'Review the order from {company}',
    ];

    protected const CALL_SUBJECTS = [
        'Question about the last delivery', 'Price negotiation', 'Introduction call with {contact}', 'Payment reminder for {company}',
        'Order status', 'Support request from {contact}', 'Follow-up after the meeting', 'New project enquiry from {company}',
    ];

    protected const MEETING_TITLES = [
        'Kick-off with {company}', 'Quarterly review with {company}', 'Product demo for {contact}', 'Contract signing — {company}',
        'Lunch with {contact}', 'Site visit at {company}', 'Planning session', 'Training for {company}',
    ];

    protected const NOTES = [
        'Bring the updated figures.', 'They asked for a discount on larger orders.', 'Decision expected by the end of the month.',
        'Check the previous correspondence first.', 'Very interested in the new version.', 'Needs approval from their management.',
        'Send a summary afterwards.', null, null,
    ];

    protected const SHOUTS = [
        'Good morning, everyone!', 'Coffee machine on the 2nd floor is fixed.', 'Who has the projector key?',
        'Reminder: team meeting at 10:00.', 'Can you check the quote before I send it?', 'New price list is in the shared folder.',
        'Great job on the Blue River deal!', 'I will be out of the office tomorrow afternoon.', 'Server maintenance tonight at 22:00.',
        'Lunch order closes at 11:30.', 'Anyone going to the trade fair next week?', 'Please update your calendars for the holidays.',
        'The printer is out of toner again.', 'Thanks for covering the call yesterday.', 'Client from Warszawa called back — all good.',
        'Parking lot will be closed on Friday.', 'Remember to log your calls in the CRM.', 'Pizza in the kitchen!',
        'Quarterly numbers look good.', 'Can someone help me with the new report?', 'Welcome to our new colleague!',
        'The delivery for Silver Peak is delayed by two days.', 'Birthday cake at 15:00.', 'Updated the contact list for the conference.',
        'Have a great weekend!',
    ];

    public function run(User $admin): void
    {
        $ourCompany = $this->record(Company::class, [
            'company_name' => 'Acme Corp',
            'phone' => '+1 555 0100',
            'email' => 'hello@acme.test',
            'city' => 'Springfield',
            'country' => 'US',
            'groups' => ['manager'],
            'permission' => RecordPermission::Private,
            'created_by' => $admin->id,
        ]);

        $manager = $this->demoUser('Morgan Manager', 'manager@example.com');
        $manager->assignRole('manager');
        $managerContact = $this->record(Contact::class, [
            'first_name' => 'Morgan',
            'last_name' => 'Manager',
            'company_id' => $ourCompany->id,
            'email' => 'manager@example.com',
            'groups' => ['office'],
            'permission' => RecordPermission::Private,
            'user_id' => $manager->id,
            'created_by' => $admin->id,
        ]);

        $employee = $this->demoUser('Eli Employee', 'employee@example.com');
        $employee->assignRole('employee');
        $employeeContact = $this->record(Contact::class, [
            'first_name' => 'Eli',
            'last_name' => 'Employee',
            'company_id' => $ourCompany->id,
            'email' => 'employee@example.com',
            'groups' => ['office'],
            'permission' => RecordPermission::Private,
            'user_id' => $employee->id,
            'created_by' => $admin->id,
        ]);

        // A customer company the employee created themselves — visible to
        // them (created_by match) even though it's Private.
        $customer = $this->record(Company::class, [
            'company_name' => 'Wayne Enterprises',
            'phone' => '+1 555 0142',
            'email' => 'contact@wayne.test',
            'city' => 'Gotham',
            'country' => 'US',
            'groups' => ['customer'],
            'permission' => RecordPermission::Private,
            'created_by' => $employee->id,
        ]);

        $bruce = $this->record(Contact::class, [
            'first_name' => 'Bruce',
            'last_name' => 'Wayne',
            'company_id' => $customer->id,
            'title' => 'CEO',
            'email' => 'bruce@wayne.test',
            'work_phone' => '+1 555 0199',
            'city' => 'Gotham',
            'country' => 'US',
            'groups' => ['customer'],
            'permission' => RecordPermission::PublicReadOnly,
            'created_by' => $employee->id,
        ]);

        // A public vendor anyone with panel access should see regardless of
        // who created it.
        $vendor = $this->record(Company::class, [
            'company_name' => 'Stark Industries',
            'phone' => '+1 555 0175',
            'email' => 'sales@stark.test',
            'city' => 'Malibu',
            'country' => 'US',
            'groups' => ['vendor'],
            'permission' => RecordPermission::Public,
            'created_by' => $manager->id,
        ]);

        $tony = $this->record(Contact::class, [
            'first_name' => 'Tony',
            'last_name' => 'Stark',
            'company_id' => $vendor->id,
            'title' => 'CEO',
            'email' => 'tony@stark.test',
            'groups' => ['customer'],
            'permission' => RecordPermission::Public,
            'created_by' => $manager->id,
        ]);

        // Phone calls, tasks and meetings — spread across permission levels
        // and creators the same way, so HasOwnershipVisibility has real
        // cross-module data to filter, not just Companies/Contacts.
        $privateCall = $this->record(PhoneCall::class, [
            'subject' => 'Follow-up on invoice #1042',
            'contact_id' => $bruce->id,
            'phone_number' => $bruce->work_phone,
            'permission' => RecordPermission::Private,
            'status' => RecordStatus::Open,
            'priority' => RecordPriority::High,
            'called_at' => now()->subDay(),
            'created_by' => $employee->id,
        ]);
        $privateCall->employees()->attach($employeeContact->id);

        $publicCall = $this->record(PhoneCall::class, [
            'subject' => 'Vendor pricing check-in',
            'contact_id' => $tony->id,
            'company_id' => $vendor->id,
            'permission' => RecordPermission::Public,
            'status' => RecordStatus::Closed,
            'priority' => RecordPriority::Medium,
            'called_at' => now()->subWeek(),
            'created_by' => $manager->id,
        ]);
        $publicCall->employees()->attach($managerContact->id);

        $privateTask = $this->record(Task::class, [
            'title' => 'Prepare Wayne Enterprises proposal',
            'description' => 'Draft the renewal proposal for review before Friday.',
            'permission' => RecordPermission::Private,
            'status' => RecordStatus::InProgress,
            'priority' => RecordPriority::High,
            'deadline' => now()->addDays(3),
            'created_by' => $employee->id,
        ]);
        $privateTask->employees()->attach($employeeContact->id);
        $privateTask->customers()->attach($bruce->id);

        $publicTask = $this->record(Task::class, [
            'title' => 'Quarterly vendor review',
            'permission' => RecordPermission::Public,
            'status' => RecordStatus::Open,
            'priority' => RecordPriority::Medium,
            'timeless' => true,
            'deadline' => now()->addMonth(),
            'created_by' => $manager->id,
        ]);
        $publicTask->employees()->attach([$managerContact->id, $employeeContact->id]);
        $publicTask->customerCompanies()->attach($vendor->id);

        $privateMeeting = $this->record(Meeting::class, [
            'title' => 'Wayne Enterprises renewal call',
            'date' => now()->addDays(2)->toDateString(),
            'time' => '14:00',
            'duration_minutes' => 30,
            'permission' => RecordPermission::Private,
            'status' => RecordStatus::Open,
            'priority' => RecordPriority::High,
            'created_by' => $employee->id,
        ]);
        $privateMeeting->employees()->attach($employeeContact->id);
        $privateMeeting->customers()->attach($bruce->id);

        $publicMeeting = $this->record(Meeting::class, [
            'title' => 'All-hands planning',
            'date' => now()->addWeek()->toDateString(),
            'time' => '09:00',
            'duration_minutes' => 60,
            'permission' => RecordPermission::Public,
            'status' => RecordStatus::Open,
            'priority' => RecordPriority::Medium,
            'created_by' => $manager->id,
        ]);
        $publicMeeting->employees()->attach([$managerContact->id, $employeeContact->id]);
        $publicMeeting->customerCompanies()->attach($vendor->id);

        // The hand-written records above are the ones tests and the docs
        // refer to; the rest fills the lists to a realistic size.
        $this->generate([$admin, $manager, $employee], [$managerContact, $employeeContact]);
    }

    /**
     * About 100 companies and contacts, 30 tasks, phone calls and meetings,
     * and a few days of shoutbox, from the lists at the end of this class —
     * not Faker, which the release zip doesn't include. Seeded, so every
     * installation gets the same demo.
     *
     * @param  list<User>  $users  who created the records
     * @param  list<Contact>  $staff  contacts the tasks, calls and meetings are assigned to
     */
    protected function generate(array $users, array $staff): void
    {
        mt_srand(2026);

        $companies = [];
        $taken = [];

        foreach (range(1, self::COMPANIES) as $i) {
            [$city, $country, $prefix] = $this->pick(self::CITIES);

            // Company e-mail is unique, and the domain comes from the name's
            // first two words, so those two are never repeated.
            do {
                $first = $this->pick(self::COMPANY_WORDS).' '.$this->pick(self::COMPANY_NOUNS);
            } while (isset($taken[$first]));
            $taken[$first] = true;

            $name = $first.' '.$this->pick(self::COMPANY_KINDS);
            $domain = strtolower(str_replace(' ', '', $first)).'.test';

            $companies[] = $this->record(Company::class, [
                'company_name' => $name,
                'short_name' => explode(' ', $name)[0],
                'phone' => $this->phone($prefix),
                'email' => 'office@'.$domain,
                'web_address' => 'https://www.'.$domain,
                'address_1' => mt_rand(1, 180).' '.$this->pick(self::STREETS),
                'city' => $city,
                'postal_code' => sprintf('%05d', mt_rand(10000, 99999)),
                'country' => $country,
                'groups' => [$this->pick(['customer', 'customer', 'customer', 'vendor', 'other'])],
                'permission' => $this->pick([RecordPermission::Public, RecordPermission::Public, RecordPermission::PublicReadOnly, RecordPermission::Private]),
                'created_by' => $this->pick($users)->id,
            ]);
        }

        $contacts = [];
        $emails = [];

        foreach (range(1, self::CONTACTS) as $i) {
            $first = $this->pick(self::FIRST_NAMES);
            $last = $this->pick(self::LAST_NAMES);
            // A few people with no company, as in any address book.
            $company = mt_rand(1, 10) === 1 ? null : $this->pick($companies);
            $domain = $company ? substr((string) $company->email, strpos((string) $company->email, '@') + 1) : 'mail.test';

            // Contact e-mail is unique too: a namesake at the same company
            // gets a number.
            $local = strtolower($this->ascii($first).'.'.$this->ascii($last));
            $email = $local.'@'.$domain;
            for ($n = 2; isset($emails[$email]); $n++) {
                $email = $local.$n.'@'.$domain;
            }
            $emails[$email] = true;

            $contacts[] = $this->record(Contact::class, [
                'first_name' => $first,
                'last_name' => $last,
                'company_id' => $company?->id,
                'title' => $this->pick(self::JOB_TITLES),
                'email' => $email,
                'work_phone' => $company?->phone,
                'mobile_phone' => $this->phone($company ? $this->prefixOf($company->country) : '+1'),
                'city' => $company?->city ?? $this->pick(self::CITIES)[0],
                'country' => $company?->country ?? 'US',
                'groups' => ['customer'],
                'permission' => $this->pick([RecordPermission::Public, RecordPermission::Public, RecordPermission::PublicReadOnly, RecordPermission::Private]),
                'created_by' => $this->pick($users)->id,
            ]);
        }

        // Past work mostly done, upcoming work mostly open.
        $status = fn (bool $past): RecordStatus => $past
            ? $this->pick([RecordStatus::Closed, RecordStatus::Closed, RecordStatus::Closed, RecordStatus::Canceled, RecordStatus::InProgress])
            : $this->pick([RecordStatus::Open, RecordStatus::Open, RecordStatus::InProgress, RecordStatus::OnHold]);
        $priority = fn (): RecordPriority => $this->pick([RecordPriority::Low, RecordPriority::Medium, RecordPriority::Medium, RecordPriority::High]);
        $permission = fn (): RecordPermission => $this->pick([RecordPermission::Public, RecordPermission::Public, RecordPermission::Private]);

        foreach (range(1, self::ACTIVITIES) as $i) {
            $contact = $this->pick($contacts);
            $company = $contact->company_id ? collect($companies)->firstWhere('id', $contact->company_id) : null;
            $words = ['{company}' => $company?->company_name ?? $contact->last_name, '{contact}' => $contact->first_name.' '.$contact->last_name];

            $deadline = now()->startOfDay()->addDays(mt_rand(-20, 40))->setTime(mt_rand(8, 17), $this->pick([0, 30]));
            $task = $this->record(Task::class, [
                'title' => strtr($this->pick(self::TASK_TITLES), $words),
                'description' => $this->pick(self::NOTES),
                'deadline' => $deadline,
                'timeless' => mt_rand(1, 4) === 1,
                'status' => $status($deadline->isPast()),
                'priority' => $priority(),
                'permission' => $permission(),
                'created_by' => $this->pick($users)->id,
            ]);
            $task->employees()->attach($this->pick($staff)->id);
            $task->customers()->attach($contact->id);
            if ($company) {
                $task->customerCompanies()->attach($company->id);
            }

            $calledAt = now()->startOfDay()->addDays(mt_rand(-30, 7))->setTime(mt_rand(8, 17), $this->pick([0, 15, 30, 45]));
            $call = $this->record(PhoneCall::class, [
                'subject' => strtr($this->pick(self::CALL_SUBJECTS), $words),
                'description' => $this->pick(self::NOTES),
                'contact_id' => $contact->id,
                'company_id' => $company?->id,
                'phone_number' => $contact->mobile_phone,
                'called_at' => $calledAt,
                'status' => $status($calledAt->isPast()),
                'priority' => $priority(),
                'permission' => $permission(),
                'created_by' => $this->pick($users)->id,
            ]);
            $call->employees()->attach($this->pick($staff)->id);

            $date = now()->startOfDay()->addDays(mt_rand(-15, 30));
            $meeting = $this->record(Meeting::class, [
                'title' => strtr($this->pick(self::MEETING_TITLES), $words),
                'description' => $this->pick(self::NOTES),
                'date' => $date->toDateString(),
                'time' => sprintf('%02d:%02d', mt_rand(8, 16), $this->pick([0, 30])),
                'duration_minutes' => $this->pick([15, 30, 30, 45, 60, 60, 90]),
                'status' => $status($date->isPast()),
                'priority' => $priority(),
                'permission' => $permission(),
                'created_by' => $this->pick($users)->id,
            ]);
            $meeting->employees()->attach(collect($staff)->random(mt_rand(1, count($staff)))->pluck('id')->all());
            $meeting->customers()->attach($contact->id);
            if ($company) {
                $meeting->customerCompanies()->attach($company->id);
            }
        }

        $this->shoutbox($users);

        mt_srand();
    }

    /**
     * A few days of team chatter, when the Shoutbox module is installed.
     *
     * @param  list<User>  $users
     */
    protected function shoutbox(array $users): void
    {
        $message = 'Epesi\\Modules\\Shoutbox\\Models\\Message';

        if (! class_exists($message) || ! Schema::hasTable((new $message)->getTable())) {
            return;
        }

        foreach (self::SHOUTS as $i => $text) {
            $author = $users[$i % count($users)];
            // Every fifth one is a private message to the next person.
            $to = $i % 5 === 4 ? $users[($i + 1) % count($users)] : null;
            $at = now()->subMinutes((count(self::SHOUTS) - $i) * mt_rand(40, 200));

            $shout = new $message;
            $shout->forceFill([
                'user_id' => $author->id,
                'to_user_id' => $to?->id,
                'message' => $text,
                'created_at' => $at,
                'updated_at' => $at,
            ])->save();
            DemoData::remember($shout);
        }
    }

    /**
     * Creates a record with every attribute given, created_by included (not
     * fillable, so plain create() would drop it outside `db:seed`), and
     * remembers it as demo data.
     *
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @param  array<string, mixed>  $attributes
     * @return T
     */
    protected function record(string $class, array $attributes): Model
    {
        $record = new $class;
        $record->forceFill($attributes)->save();
        DemoData::remember($record);

        return $record;
    }

    /**
     * @template T
     *
     * @param  array<T>  $items
     * @return T
     */
    protected function pick(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }

    protected function phone(string $prefix): string
    {
        return $prefix.' '.mt_rand(200, 899).' '.mt_rand(100, 999).' '.sprintf('%03d', mt_rand(0, 999));
    }

    protected function prefixOf(?string $country): string
    {
        foreach (self::CITIES as [, $code, $prefix]) {
            if ($code === $country) {
                return $prefix;
            }
        }

        return '+1';
    }

    /** "Łukasz" → "Lukasz", for e-mail addresses. */
    protected function ascii(string $text): string
    {
        return preg_replace('/[^A-Za-z]/', '', strtr($text, [
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'Ł' => 'L', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'Ś' => 'S', 'ź' => 'z', 'ż' => 'z', 'Ż' => 'Z',
            'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss', 'é' => 'e', 'è' => 'e', 'ñ' => 'n', 'ç' => 'c',
        ])) ?: 'contact';
    }

    /**
     * Not through UserFactory: that needs Faker, a development package the
     * release zip leaves out, and the setup wizard loads this demo data.
     */
    protected function demoUser(string $name, string $email): User
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now()])->save();
        DemoData::remember($user);

        return $user;
    }
}
