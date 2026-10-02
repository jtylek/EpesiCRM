<?php

namespace Database\Seeders;

use App\Enums\RecordPermission;
use App\Enums\RecordPriority;
use App\Enums\RecordStatus;
use App\Models\User;
use App\Services\FileStorage;
use App\Support\Demo;
use App\Support\DemoData;
use Carbon\CarbonInterface;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\CompanyResource;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\ContactResource;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Meetings\Filament\Resources\Meetings\MeetingResource;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Filament\Resources\PhoneCalls\PhoneCallResource;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\TaskResource;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\RecordBrowser\Browsing\Favorites;
use Epesi\Modules\RecordBrowser\Browsing\RecentRecords;
use Epesi\Modules\Reminders\Reminders;
use Epesi\Modules\StickyNotes\Models\StickyNote;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * The demo records: an own company, a manager and an employee (both with the
 * password "password"), customers, a vendor, and calls, tasks and meetings
 * spread across permission levels and creators, so the ownership scope in
 * HasOwnershipVisibility has something real to filter — then generated ones
 * up to 100 companies, 100 contacts, 30 tasks, calls and meetings, a
 * shoutbox conversation, 100 notes, ten next actions on each user's priority
 * list, and 100 archived e-mails, including three hand-written conversations.
 * Every row is remembered
 * (App\Support\DemoData), so Administration → Demo data can remove it all
 * again.
 *
 * Run by DatabaseSeeder for development, and by the setup wizard's "load demo
 * data" option with the administrator the wizard just created.
 */
class DemoDataSeeder extends Seeder
{
    protected ?Randomizer $rng = null;

    protected int $notesCreated = 0;

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
        'Adams', 'Baker', 'Carter', 'Davis', 'Evans', 'Foster', 'Gray', 'Harris', 'Irwin', 'Jackson', 'King', 'Lewis',
        'Moore', 'Nelson', 'Parker', 'Reed', 'Scott', 'Turner', 'Ward', 'Wright', 'Bennett', 'Cooper', 'Edwards', 'Flores',
        'Green', 'Hughes', 'Jenkins', 'Kelly', 'Morgan', 'Price', 'Rivera', 'Sanders', 'Wood', 'Brooks', 'Collins', 'Diaz',
        'Ellis', 'Fisher', 'Graham', 'Howard', 'James', 'Mason', 'Ortiz', 'Perry', 'Russell', 'Stone', 'Torres', 'Bell',
        'Cruz', 'Murphy', 'Bailey', 'Barnes', 'Cole', 'Ford', 'Hayes', 'Long', 'Powell', 'Ross', 'Simmons', 'Watkins',
        'White', 'West', 'Grant', 'Pierce',
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

    /**
     * Notes on generated records, by morph alias: [title, text], each used
     * once. A text of several lines becomes a list.
     */
    protected const RECORD_NOTES = [
        'company' => [
            [null, 'Prefers to be contacted by e-mail.'],
            [null, 'Invoices must quote their purchase order number.'],
            [null, 'Closed between Christmas and New Year.'],
            [null, 'Asked for a product demo next quarter.'],
            ['Payment terms', '30 days from the invoice date; 2% discount when paid within 7 days.'],
            [null, 'New purchasing manager since last month; introduce ourselves.'],
            [null, 'Very happy with the last delivery.'],
            [null, 'Wants the catalogue in both English and Polish.'],
            [null, 'Pays on time; no reminders needed so far.'],
            ['Directions', 'Parking is behind the building, entrance from the side street.'],
            ['Delivery address', 'Goods go to the warehouse, not the head office, between 7:00 and 15:00 on weekdays.'],
            [null, 'Their financial year ends in March, so budgets are decided in February.'],
            [null, 'Buys spare parts elsewhere; worth offering our service package.'],
            ['Credit limit', 'Agreed at 20,000 EUR. Anything above needs prepayment.'],
            [null, 'Opened a second office last year and is still hiring.'],
            [null, 'All quotes go through their procurement portal, not by e-mail.'],
            [null, 'Met their team at the trade fair; they liked the mobile version.'],
            ['Reception', 'Visitors sign in at reception and wear a badge.'],
            [null, 'Merging with a partner company in the autumn; contacts may change.'],
            [null, 'No calls on Fridays, please: that is their stock-taking day.'],
            [null, 'Complained about a late delivery in the spring. Sorted out, but keep an eye on it.'],
            ['VAT', 'Reverse charge: always put their EU VAT number on the invoice.'],
        ],
        'contact' => [
            [null, 'Prefers phone calls to e-mail, best before 10:00.'],
            [null, 'Met at a logistics conference two years ago.'],
            [null, 'On leave until the end of next month; a deputy handles orders meanwhile.'],
            [null, 'Prefers to speak English.'],
            [null, 'Decides on anything over 5,000 EUR.'],
            ['Birthday', 'Early June. Send a card.'],
            [null, 'Asked to be copied on every quote for the company.'],
            [null, 'Very technical: send specifications, not brochures.'],
            [null, 'Recommended us to two other companies.'],
            [null, 'Out of the office on Mondays.'],
            [null, 'Moved from purchasing to project management; check who took over.'],
            [null, 'Likes short e-mails with one clear question.'],
            ['Assistant', 'Appointments go through the assistant, who keeps the calendar.'],
            [null, 'Interested in training for the team at the new office.'],
            [null, 'Prefers meetings in the afternoon.'],
            [null, 'Plays in the company football team; a good topic for small talk.'],
            [null, 'Signed the last contract; keep in the loop on renewals.'],
            [null, 'Wants the monthly report as a PDF, not a spreadsheet.'],
            [null, 'Was unhappy with our support response times. Call after every ticket for now.'],
            [null, 'The mobile number is private: only when urgent.'],
            ['LinkedIn', 'Connected; posts regularly about logistics.'],
            [null, 'Vegetarian: remember when booking lunch.'],
        ],
        'task' => [
            [null, 'Waiting for their purchase order number before this can go out.'],
            [null, 'Use the new quote template, not last year\'s.'],
            [null, 'The price list was approved by management on Monday.'],
            ['Checklist', "Prices checked\nDelivery dates confirmed with the warehouse\nStill missing: the signed terms and conditions"],
            [null, 'Half done; the rest once the figures from accounting arrive.'],
            [null, 'The draft is in the shared folder under Customers.'],
            [null, 'They asked for this in writing, so a phone call is not enough.'],
            [null, 'Check the discount with the manager before sending.'],
            [null, 'Postponed once already; the customer is waiting.'],
            [null, 'The previous version had the wrong VAT rate: double-check.'],
            [null, 'Attach the product sheets for the new range.'],
            ['Who gets it', 'Send it to purchasing and copy the project manager.'],
            [null, 'Legal must see the contract changes first.'],
            [null, 'A quick one: about 20 minutes.'],
            [null, 'Needs the serial numbers from the last delivery.'],
            [null, 'Remind them about the open invoice at the same time.'],
        ],
        'phone_call' => [
            [null, 'Ask about the delivery date for the second batch.'],
            [null, 'They want a call back after lunch.'],
            [null, 'Have the last invoice at hand; they will ask about it.'],
            ['Talking points', "Renewal date and price\nThe new support hours\nTraining for the new office"],
            [null, 'The line was busy twice; try the mobile number.'],
            [null, 'Promised to confirm the order by e-mail after the call.'],
            [null, 'Mention the discount on larger orders.'],
            [null, 'They are comparing us with two other suppliers.'],
            [null, 'Left a voicemail; waiting for them to call back.'],
            [null, 'Send the catalogue after the call.'],
            [null, 'Very friendly; interested in a longer contract.'],
            [null, 'Needs to check with their manager before agreeing.'],
            [null, 'Reception puts calls through only between 9:00 and 12:00.'],
            [null, 'Complained about the invoice layout; pass it on to accounting.'],
            [null, 'Agreed to meet next week; send an invitation.'],
            [null, 'Ask who replaces the purchasing manager.'],
        ],
        'meeting' => [
            ['Agenda', "Introductions\nTheir requirements for next year\nPrices and delivery terms\nNext steps"],
            [null, 'Bring printed copies of the proposal.'],
            [null, 'Book the small conference room and order coffee.'],
            [null, 'They will bring their IT administrator.'],
            [null, 'Parking is limited; come by train if possible.'],
            [null, 'Run the demo from the laptop; their Wi-Fi is unreliable.'],
            [null, 'Their CFO joins for the last half hour.'],
            ['Goals', "Agree on the rollout date\nFeedback on the pilot\nIntroduce the new account manager"],
            [null, 'Online; the link is in the invitation.'],
            [null, 'Take the samples from the warehouse the day before.'],
            [null, 'Dress code: business formal.'],
            [null, 'Lunch booked for 12:30 at the restaurant next door.'],
            [null, 'Allow extra travel time: roadworks on the way.'],
            [null, 'Bring the signed contract for their copy.'],
            [null, 'They asked for a short presentation, 15 minutes at most.'],
            [null, 'Confirm the time the day before.'],
            [null, 'Send the summary the same day.'],
        ],
    ];

    /**
     * Archived e-mail with the hand-written customer and vendor: who archived
     * it, and its day and time counted back from today. A reply names the
     * message it answers, which is what puts it in the same thread.
     */
    protected const MAILS = [
        [
            'id' => 'renewal-1@wayne.test', 'by' => 'employee', 'days' => 6, 'time' => '09:12',
            'from' => 'Bruce Wayne <bruce@wayne.test>', 'to' => 'Eli Employee <employee@example.com>',
            'subject' => 'Support agreement renewal',
            'text' => "Hi Eli,\n\nOur support agreement ends next month. Could you send us a renewal proposal? Please include five extra licences for the new office.\n\nBest regards,\nBruce Wayne\nCEO, Wayne Enterprises",
        ],
        [
            'id' => 'renewal-2@acme.test', 'reply' => 'renewal-1@wayne.test', 'by' => 'employee', 'days' => 5, 'time' => '14:40',
            'from' => 'Eli Employee <employee@example.com>', 'to' => 'Bruce Wayne <bruce@wayne.test>',
            'subject' => 'Re: Support agreement renewal',
            'text' => "Hi Bruce,\n\nThank you. I am preparing the proposal and will send it by Friday.\n\nKind regards,\nEli Employee\nAcme Corp",
        ],
        [
            'id' => 'renewal-3@wayne.test', 'reply' => 'renewal-2@acme.test', 'by' => 'employee', 'days' => 4, 'time' => '08:55',
            'from' => 'Bruce Wayne <bruce@wayne.test>', 'to' => 'Eli Employee <employee@example.com>',
            'subject' => 'Re: Support agreement renewal',
            'text' => "Thanks, Eli.\n\nCould you also quote a training day for the new team? Either of these would suit us:\n- the 14th or 15th of next month\n- any day the week after\n\nBruce",
            'html' => '<p>Thanks, Eli.</p><p>Could you also quote a <strong>training day</strong> for the new team? Either of these would suit us:</p><ul><li>the 14th or 15th of next month</li><li>any day the week after</li></ul><p>Bruce</p>',
        ],
        [
            'id' => 'prices-1@stark.test', 'by' => 'manager', 'days' => 10, 'time' => '11:05',
            'from' => 'Tony Stark <tony@stark.test>', 'to' => 'Morgan Manager <manager@example.com>',
            'subject' => 'Price list for the next quarter',
            'text' => "Hello Morgan,\n\nFrom the first of next month our prices for components go up by 3%. Delivery costs stay the same, and orders placed before then are invoiced at the current prices.\n\nTony Stark\nStark Industries",
        ],
        [
            'id' => 'prices-2@acme.test', 'reply' => 'prices-1@stark.test', 'by' => 'manager', 'days' => 9, 'time' => '10:20',
            'from' => 'Morgan Manager <manager@example.com>', 'to' => 'Tony Stark <tony@stark.test>',
            'subject' => 'Re: Price list for the next quarter',
            'text' => "Hi Tony,\n\nThanks for letting us know. We will place our order this week, at the current prices.\n\nMorgan Manager\nAcme Corp",
        ],
        [
            'id' => 'order-20431@stark.test', 'by' => 'manager', 'days' => 8, 'time' => '16:02',
            'from' => 'Stark Industries <sales@stark.test>', 'to' => 'Morgan Manager <manager@example.com>',
            'subject' => 'Order confirmation SI-20431',
            'text' => "Thank you for your order SI-20431.\n\nDelivery is expected within 5 working days. You will receive a tracking number when the goods are shipped.\n\nStark Industries, Sales",
        ],
    ];

    public function run(User $admin): void
    {
        $this->notesCreated = 0;

        $ourCompany = $this->record(Company::class, [
            'company_name' => 'Acme Corp',
            'groups' => ['manager'],
            'permission' => RecordPermission::Private,
            'created_by' => $admin->id,
        ], $this->details(['city' => 'Springfield', 'country' => 'US'], ['work' => '+1 555 0100'], email: 'hello@acme.test'));

        $manager = $this->demoUser('Morgan Manager', 'manager@example.com');
        $manager->assignRole('manager');
        $managerContact = $this->record(Contact::class, [
            'first_name' => 'Morgan',
            'last_name' => 'Manager',
            'company_id' => $ourCompany->id,
            'groups' => ['office'],
            'permission' => RecordPermission::Private,
            'user_id' => $manager->id,
            'created_by' => $admin->id,
        ], $this->details(email: 'manager@example.com'));

        $employee = $this->demoUser('Eli Employee', 'employee@example.com');
        $employee->assignRole('employee');
        $employeeContact = $this->record(Contact::class, [
            'first_name' => 'Eli',
            'last_name' => 'Employee',
            'company_id' => $ourCompany->id,
            'groups' => ['office'],
            'permission' => RecordPermission::Private,
            'user_id' => $employee->id,
            'created_by' => $admin->id,
        ], $this->details(email: 'employee@example.com'));

        // A customer company the employee created themselves — visible to
        // them (created_by match) even though it's Private.
        $customer = $this->record(Company::class, [
            'company_name' => 'Wayne Enterprises',
            'groups' => ['customer'],
            'permission' => RecordPermission::Private,
            'created_by' => $employee->id,
        ], $this->details(['city' => 'Gotham', 'country' => 'US'], ['work' => '+1 555 0142'], email: 'contact@wayne.test'));

        $bruceWorkPhone = '+1 555 0199';
        $bruce = $this->record(Contact::class, [
            'first_name' => 'Bruce',
            'last_name' => 'Wayne',
            'company_id' => $customer->id,
            'title' => 'CEO',
            'groups' => ['customer'],
            'permission' => RecordPermission::PublicReadOnly,
            'created_by' => $employee->id,
        ], $this->details(['city' => 'Gotham', 'country' => 'US'], ['work' => $bruceWorkPhone], email: 'bruce@wayne.test'));

        // A public vendor anyone with panel access should see regardless of
        // who created it.
        $vendor = $this->record(Company::class, [
            'company_name' => 'Stark Industries',
            'groups' => ['vendor'],
            'permission' => RecordPermission::Public,
            'created_by' => $manager->id,
        ], $this->details(['city' => 'Malibu', 'country' => 'US'], ['work' => '+1 555 0175'], email: 'sales@stark.test'));

        $tony = $this->record(Contact::class, [
            'first_name' => 'Tony',
            'last_name' => 'Stark',
            'company_id' => $vendor->id,
            'title' => 'CEO',
            'groups' => ['customer'],
            'permission' => RecordPermission::Public,
            'created_by' => $manager->id,
        ], $this->details(email: 'tony@stark.test'));

        // Phone calls, tasks and meetings — spread across permission levels
        // and creators the same way, so HasOwnershipVisibility has real
        // cross-module data to filter, not just Companies/Contacts.
        $privateCall = $this->record(PhoneCall::class, [
            'subject' => 'Follow-up on invoice #1042',
            'customer_type' => $bruce->getMorphClass(),
            'customer_id' => $bruce->id,
            'phone_number' => $bruceWorkPhone,
            'permission' => RecordPermission::Private,
            'status' => RecordStatus::Open,
            'priority' => RecordPriority::High,
            'called_at' => now()->subDay(),
            'created_by' => $employee->id,
        ]);
        $privateCall->employees()->attach($employeeContact->id);

        $publicCall = $this->record(PhoneCall::class, [
            'subject' => 'Vendor pricing check-in',
            'customer_type' => $tony->getMorphClass(),
            // A call has one Customer (Field::customer()); contact takes
            // priority when both are candidates, same as Followup::close().
            'customer_id' => $tony->id,
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
        // refer to; the rest fills the lists to a realistic size. Seeded, so
        // every installation gets the same demo.
        $this->rng = new Randomizer(new Mt19937(2026));

        $activities = $this->generate([$admin, $manager, $employee], [$managerContact, $employeeContact], $admin);

        $this->priorityLists([$admin, $manager, $employee], [
            $privateCall, $publicCall, $privateTask, $publicTask, $privateMeeting, $publicMeeting, ...$activities,
        ]);
        $this->favoritesAndRecent(Demo::enabled() ? [$manager, $employee] : [$admin], $activities);

        $this->rng = null;

        $this->notes([
            [$ourCompany, $admin, 'Office hours', '<p>Monday to Friday, 8:00–16:00. The office is closed on public holidays.</p>', RecordPermission::Public, true],
            [$customer, $employee, 'Account overview', '<p>Customer since 2019. The support agreement is up for renewal next month.</p><ul><li>Decision maker: Bruce Wayne (CEO)</li><li>Prefers e-mail to phone calls</li><li>Invoices go to the finance department, not to Bruce</li></ul>', RecordPermission::Public, true],
            [$bruce, $employee, null, '<p>Met at the trade fair last spring. Interested in training for the team at their new office.</p>'],
            [$vendor, $manager, 'Delivery terms', '<p>Standard delivery within 5 working days; orders over 10,000 USD ship free of charge.</p><p>Prices for components go up by 3% next month.</p>', RecordPermission::Public, true],
            [$tony, $manager, null, '<p>Best reached in the morning, Pacific time.</p>', RecordPermission::PublicReadOnly],
            // Private: only Eli, who wrote it, and the managers see it.
            [$privateTask, $employee, null, '<p>Start from last year\'s proposal, then add the five extra licences and the training day.</p>', RecordPermission::Private],
            [$publicMeeting, $manager, 'Agenda', '<ol><li>Results of the last quarter</li><li>Plans for the next one</li><li>Questions</li></ol>'],
        ]);

        $this->mail($admin, $manager, $employee);
    }

    /**
     * About 100 companies and contacts, 30 tasks, phone calls and meetings, a
     * few days of shoutbox, and notes, from the lists at the end of this
     * class — not Faker, which the release zip doesn't include.
     *
     * @param  list<User>  $users  who created the records
     * @param  list<Contact>  $staff  contacts the tasks, calls and meetings are assigned to
     * @param  User  $setupUser  gets ten open tasks, five calls and five meetings
     * @return list<Task|PhoneCall|Meeting>
     */
    protected function generate(array $users, array $staff, User $setupUser): array
    {
        $companies = [];
        $cities = [];
        $phones = [];
        $domains = [];
        $taken = [];

        foreach (range(1, self::COMPANIES) as $i) {
            [$city, $country, $prefix] = $this->pick(self::CITIES);

            // Company e-mail is unique, and the domain comes from the name's
            // first two words, so those two are never repeated.
            do {
                $first = $this->pick(self::COMPANY_WORDS).' '.$this->pick(self::COMPANY_NOUNS);
            } while (isset($taken[$first]));
            $taken[$first] = true;

            $kind = $this->pick(self::COMPANY_KINDS);
            $name = match ($this->pick(['industry', 'group', 'partners', 'company', 'the'])) {
                'group' => $first.' Group',
                'partners' => $first.' Partners',
                'company' => $first.' & Co.',
                'the' => 'The '.$first.' '.$kind,
                default => $first.' '.$kind,
            };
            $domain = strtolower(str_replace(' ', '', $first)).'.test';

            // In the order the columns were once drawn in, so the fixed seed
            // still gives the same demo data.
            $phone = $this->phone($prefix);
            $address = [
                'address_1' => $this->rand(1, 180).' '.$this->pick(self::STREETS),
                'city' => $city,
                'postal_code' => sprintf('%05d', $this->rand(10000, 99999)),
                'country' => $country,
            ];

            $companies[] = $company = $this->record(Company::class, [
                'company_name' => $name,
                'short_name' => explode(' ', $first)[0],
                'groups' => [$this->pick(['customer', 'customer', 'customer', 'vendor', 'other'])],
                'permission' => $this->pick([RecordPermission::Public, RecordPermission::Public, RecordPermission::PublicReadOnly, RecordPermission::Private]),
                'created_by' => $this->pick($users)->id,
            ], $this->details($address, ['work' => $phone], 'https://www.'.$domain, email: 'office@'.$domain));
            $cities[$company->id] = [$city, $country];
            $phones[$company->id] = $phone;
            $domains[$company->id] = $domain;
        }

        $contacts = [];
        $mobiles = [];
        $emails = [];

        foreach (range(1, self::CONTACTS) as $i) {
            $first = $this->pick(self::FIRST_NAMES);
            $last = self::LAST_NAMES[$i - 1];
            // A few people with no company, as in any address book.
            $company = $this->rand(1, 10) === 1 ? null : $this->pick($companies);
            $domain = $company ? $domains[$company->id] : 'mail.test';

            // Contact e-mail is unique too: a namesake at the same company
            // gets a number.
            $local = strtolower($this->ascii($first).'.'.$this->ascii($last));
            $email = $local.'@'.$domain;
            for ($n = 2; isset($emails[$email]); $n++) {
                $email = $local.$n.'@'.$domain;
            }
            $emails[$email] = true;

            // The title and then the city, in the order they were once drawn in.
            $attributes = [
                'first_name' => $first,
                'last_name' => $last,
                'company_id' => $company?->id,
                'title' => $this->pick(self::JOB_TITLES),
            ];
            $mobile = $this->phone($company ? $this->prefixOf($cities[$company->id][1]) : '+1');
            // The company's city, else any.
            [$city, $country] = $company ? $cities[$company->id] : [$this->pick(self::CITIES)[0], 'US'];

            $contacts[] = $contact = $this->record(Contact::class, [
                ...$attributes,
                'groups' => ['customer'],
                'permission' => $this->pick([RecordPermission::Public, RecordPermission::Public, RecordPermission::PublicReadOnly, RecordPermission::Private]),
                'created_by' => $this->pick($users)->id,
            ], $this->details(['city' => $city, 'country' => $country], ['work' => $company ? $phones[$company->id] : null, 'mobile' => $mobile], email: $email));
            $mobiles[$contact->id] = $mobile;
        }

        // Past work mostly done, upcoming work mostly open.
        $status = fn (bool $past): RecordStatus => $past
            ? $this->pick([RecordStatus::Closed, RecordStatus::Closed, RecordStatus::Closed, RecordStatus::Canceled, RecordStatus::InProgress])
            : $this->pick([RecordStatus::Open, RecordStatus::Open, RecordStatus::InProgress, RecordStatus::OnHold]);
        $priority = fn (): RecordPriority => $this->pick([RecordPriority::Low, RecordPriority::Medium, RecordPriority::Medium, RecordPriority::High]);
        $permission = fn (): RecordPermission => $this->pick([RecordPermission::Public, RecordPermission::Public, RecordPermission::Private]);
        // During installation the demo runs before "Your company". That step
        // reuses this contact, keeping its activity assignments intact.
        $setupUserContact = Contact::query()->withoutGlobalScopes()->firstOrNew(['user_id' => $setupUser->id]);
        if (! $setupUserContact->exists) {
            $name = explode(' ', trim($setupUser->name), 2);
            $setupUserContact->forceFill([
                'first_name' => $name[0],
                'last_name' => $name[1] ?? '',
                'created_by' => $setupUser->id,
                'permission' => RecordPermission::Private,
            ])->save();
        }
        $tasks = $calls = $meetings = [];

        foreach (range(1, self::ACTIVITIES) as $i) {
            $contact = $this->pick($contacts);
            $company = $contact->company_id ? collect($companies)->firstWhere('id', $contact->company_id) : null;
            $words = ['{company}' => $company?->company_name ?? $contact->last_name, '{contact}' => $contact->first_name.' '.$contact->last_name];

            $deadline = $i <= 10
                ? today()->addDays(intdiv($i - 1, 2))->setTime($i % 2 ? 10 : 14, 0)
                : now()->startOfDay()->addDays($this->rand(-20, 40))->setTime($this->rand(8, 17), $this->pick([0, 30]));
            $tasks[] = $task = $this->record(Task::class, [
                'title' => strtr($this->pick(self::TASK_TITLES), $words),
                'description' => $this->pick(self::NOTES),
                'deadline' => $deadline,
                'timeless' => $this->rand(1, 4) === 1,
                'status' => $i <= 10 ? RecordStatus::Open : $status($deadline->isPast()),
                'priority' => $priority(),
                'permission' => $permission(),
                'created_by' => $this->pick($users)->id,
            ]);
            $taskEmployee = $i <= 10 ? $setupUserContact : $this->pick($staff);
            $task->employees()->attach($taskEmployee->id);
            $task->customers()->attach($contact->id);
            if ($company) {
                $task->customerCompanies()->attach($company->id);
            }

            $calledAt = $i <= 5
                ? today()->addDays($i - 1)->setTime(13, 0)
                : now()->startOfDay()->addDays($this->rand(-30, 7))->setTime($this->rand(8, 17), $this->pick([0, 15, 30, 45]));
            $calls[] = $call = $this->record(PhoneCall::class, [
                'subject' => strtr($this->pick(self::CALL_SUBJECTS), $words),
                'description' => $this->pick(self::NOTES),
                'customer_type' => $contact->getMorphClass(),
                // A call has one Customer; contact takes priority when both
                // are candidates, same as Followup::close().
                'customer_id' => $contact->id,
                'phone_number' => $mobiles[$contact->id],
                'called_at' => $calledAt,
                'status' => $i <= 5 ? RecordStatus::Open : $status($calledAt->isPast()),
                'priority' => $priority(),
                'permission' => $permission(),
                'created_by' => $this->pick($users)->id,
            ]);
            $callEmployee = $i <= 5 && $setupUserContact ? $setupUserContact : $this->pick($staff);
            $call->employees()->attach($callEmployee->id);

            $date = $i <= 5 ? today()->addDays($i <= 3 ? 0 : 1) : today()->addDays($this->rand(-15, 30));
            $meetings[] = $meeting = $this->record(Meeting::class, [
                'title' => strtr($this->pick(self::MEETING_TITLES), $words),
                'description' => $this->pick(self::NOTES),
                'date' => $date->toDateString(),
                'time' => $i <= 5 ? ['09:00', '12:00', '15:00', '09:00', '13:00'][$i - 1] : sprintf('%02d:%02d', $this->rand(8, 16), $this->pick([0, 30])),
                'duration_minutes' => $this->pick([15, 30, 30, 45, 60, 60, 90]),
                'status' => $i <= 5 ? RecordStatus::Open : $status($date->isPast()),
                'priority' => $priority(),
                'permission' => $permission(),
                'created_by' => $this->pick($users)->id,
            ]);
            $meetingEmployees = $i <= 5 && $setupUserContact
                ? [$setupUserContact->id]
                : collect($staff)->random($this->rand(1, count($staff)))->pluck('id')->all();
            $meeting->employees()->attach($meetingEmployees);
            $meeting->customers()->attach($contact->id);
            if ($company) {
                $meeting->customerCompanies()->attach($company->id);
            }

            // Nobody logs in as the administrator in the demo, so the
            // visitors' accounts get the reminders.
            if ($i <= 10) {
                $this->reminder($task, $users);
            }
            if ($i <= 5) {
                $this->reminder($call, $users);
                $this->reminder($meeting, $users);
            }
        }

        $this->shoutbox($users);
        foreach ($users as $user) {
            $this->stickyNotes($user);
        }

        // After everything above, so adding notes changed none of it.
        $records = ['company' => $companies, 'contact' => $contacts, 'task' => $tasks, 'phone_call' => $calls, 'meeting' => $meetings];

        foreach (self::RECORD_NOTES as $type => $notes) {
            foreach ($notes as [$title, $text]) {
                $record = $this->pick($records[$type]);
                $lines = explode("\n", $text);

                $this->notes([[
                    $record,
                    // A private record's notes are by whoever can see it.
                    $record->permission === RecordPermission::Private
                        ? collect($users)->firstWhere('id', $record->created_by)
                        : $this->pick($users),
                    $title,
                    count($lines) > 1
                        ? '<ul>'.implode('', array_map(fn (string $line): string => '<li>'.e($line).'</li>', $lines)).'</ul>'
                        : '<p>'.e($text).'</p>',
                    $this->pick([RecordPermission::Public, RecordPermission::Public, RecordPermission::Private]),
                    $title !== null && $this->rand(1, 3) === 1,
                    // Written over the last month, not all this second.
                    now()->subDays($this->rand(1, 30))->setTime($this->rand(8, 17), $this->rand(0, 59)),
                ]]);
            }
        }

        return [...$tasks, ...$calls, ...$meetings];
    }

    /**
     * Ten open tasks, phone calls and meetings on each user's priority list,
     * when the PriorityList module is installed: a mix of the three, from
     * what is assigned to them, falling back to all open work when unassigned.
     *
     * @param  list<User>  $users
     * @param  list<Task|PhoneCall|Meeting>  $activities
     */
    protected function priorityLists(array $users, array $activities): void
    {
        $list = 'Epesi\\Modules\\PriorityList\\PriorityList';

        if (! class_exists($list) || ! Schema::hasTable('epesi_priority_list_entries')) {
            return;
        }

        $open = array_filter($activities, fn (Model $record): bool => ! in_array($record->status, RecordStatus::finished(), true));

        foreach ($users as $user) {
            $theirs = array_filter($open, fn (Model $record): bool => $record->employees->contains('user_id', $user->id)) ?: $open;

            // Up to four of each kind, then ten of those in a random order.
            $picked = [];
            foreach (collect($theirs)->groupBy(fn (Model $record): string => $record->getMorphClass()) as $ofKind) {
                $ofKind = $ofKind->all();
                $ofKind = $this->rng()->shuffleArray($ofKind);
                array_push($picked, ...array_slice($ofKind, 0, 4));
            }
            $picked = $this->rng()->shuffleArray($picked);

            foreach (array_slice($picked, 0, $list::LIMIT) as $record) {
                $list::add($user, $record);
            }
        }
    }

    /**
     * Ten demo records of each CRM type in each selected user's Favorites and Recent lists.
     *
     * @param  list<User>  $users
     * @param  list<Task|PhoneCall|Meeting>  $activities
     */
    protected function favoritesAndRecent(array $users, array $activities): void
    {
        $candidates = collect($activities);
        foreach ([Company::class, Contact::class] as $recordClass) {
            // Pick shared demo records so employee favorites are visible, and
            // leave the administrator's real company and contact out of the sample.
            $model = new $recordClass;
            $candidates = $candidates->concat($recordClass::query()->withoutGlobalScopes()
                ->whereIn('id', DB::table('demo_records')
                    ->where('table_name', $model->getTable())->select('record_id'))
                ->whereIn('permission', [RecordPermission::Public, RecordPermission::PublicReadOnly])
                ->orderBy('id')->get());
        }

        foreach ($users as $user) {
            foreach ([
                Company::class => CompanyResource::class,
                Contact::class => ContactResource::class,
                Task::class => TaskResource::class,
                PhoneCall::class => PhoneCallResource::class,
                Meeting::class => MeetingResource::class,
            ] as $recordClass => $resource) {
                $records = $candidates
                    ->filter(fn (Model $record): bool => $record instanceof $recordClass)
                    ->pipe(fn (Collection $records): Collection => collect($this->rng()->shuffleArray($records->values()->all())))
                    ->take(10);

                foreach ($records as $record) {
                    Favorites::add($user, $record);
                    RecentRecords::visit($user, $record, $resource::getRecentLimit());
                }
            }
        }
    }

    /**
     * @param  list<User>  $users  who is reminded; the first one is the author
     */
    protected function reminder(Model $record, array $users): void
    {
        if (! class_exists(Reminders::class) || ! Schema::hasTable('epesi_reminders')) {
            return;
        }

        $reminder = $record->reminders()->create([
            'remind_at' => Reminders::startOf($record)->copy()->subMinutes(15),
            'before_minutes' => 15,
            'message' => 'Review the details and prepare for this activity.',
            'send_email' => false,
            'created_by' => $users[0]->id,
        ]);
        $reminder->recipients()->attach(array_map(fn (User $user): int => $user->id, $users));
    }

    /**
     * Notes on records, when the Attachments module is installed. Each is
     * [record, author, title, note (HTML), permission = Public, sticky = false,
     * written at = now].
     *
     * @param  list<array{0: Model, 1: User, 2: ?string, 3: string, 4?: RecordPermission, 5?: bool, 6?: CarbonInterface}>  $notes
     */
    protected function notes(array $notes): void
    {
        $note = 'Epesi\\Modules\\Attachments\\Models\\Attachment';

        if (! class_exists($note) || ! Schema::hasTable((new $note)->getTable())) {
            return;
        }

        foreach ($notes as $spec) {
            [$record, $author, $title, $text, $permission, $sticky, $at] = $spec + [4 => RecordPermission::Public, 5 => false, 6 => null];
            $noteNumber = ++$this->notesCreated;
            $text .= '<h3>Next steps</h3><p><strong>Customer follow-up:</strong> Review these details with the account team and confirm the customer’s priorities before the next conversation.</p><ul><li>Check the agreed scope and delivery dates.</li><li>Record decisions and assign an owner for every outstanding action.</li><li>Share a concise progress update at the next review.</li></ul><p><em>Demo example for the full-page note preview.</em></p>';
            $files = [];

            $fileSpec = $noteNumber <= 50
                ? ($noteNumber % 2 === 1 ? ['png', $this->demoImage()] : ['pdf', $this->demoPdf()])
                : null;

            if ($fileSpec !== null) {
                [$extension, $contents] = $fileSpec;
                $file = app(FileStorage::class)->put($contents, sprintf('demo-note-%03d.%s', $noteNumber, $extension));
                $file->forceFill(['created_by' => $author->id])->save();
                $files[] = (string) $file->getKey();
            }

            $this->record($note, [
                'title' => $title,
                'note' => $text,
                'files' => $files,
                'permission' => $permission,
                'sticky' => $sticky,
                'created_by' => $author->id,
                ...($at ? ['created_at' => $at, 'updated_at' => $at] : []),
            ])->attachTo($record);
        }
    }

    protected function demoImage(): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new \RuntimeException('The GD extension is required to generate demo note images.');
        }

        $image = imagecreatetruecolor(640, 400);
        $background = imagecolorallocate($image, 246, 248, 250);
        $header = imagecolorallocate($image, 31, 65, 77);
        $ink = imagecolorallocate($image, 45, 57, 66);
        $accent = imagecolorallocate($image, 28, 130, 116);
        $white = imagecolorallocate($image, 255, 255, 255);
        $panel = imagecolorallocate($image, 255, 255, 255);

        imagefill($image, 0, 0, $background);
        imagefilledrectangle($image, 0, 0, 639, 76, $header);
        imagestring($image, 5, 26, 24, 'THIS IS A DEMO IMAGE', $white);
        imagestring($image, 3, 26, 104, 'ACME CORP | CUSTOMER ACTIVITY', $ink);
        imagestring($image, 2, 26, 128, 'Weekly service requests', $ink);
        imagefilledrectangle($image, 24, 154, 616, 350, $panel);

        foreach ([92, 138, 108, 162, 126] as $index => $height) {
            $left = 68 + $index * 104;
            imagefilledrectangle($image, $left, 320 - $height, $left + 42, 320, $accent);
            imagestring($image, 2, $left + 10, 330, 'W'.($index + 1), $ink);
        }

        imagestring($image, 3, 440, 104, 'Sample preview', $ink);

        ob_start();
        $encoded = imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        if (! $encoded || ! is_string($contents)) {
            throw new \RuntimeException('Could not generate the demo PNG attachment.');
        }

        return $contents;
    }

    protected function demoPdf(): string
    {
        $stream = "BT\n/F1 22 Tf\n72 700 Td\n(This is a demo PDF) Tj\n/F1 12 Tf\n0 -32 Td\n(Sample document attached to a demo customer record.) Tj\nET\n";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}endstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $crossReferenceOffset = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$crossReferenceOffset}\n%%EOF\n";
    }

    /**
     * One hundred archived e-mails, when the Mail module is installed. Ten
     * are filed by the setup user; the rest are split between the demo staff.
     */
    protected function mail(User $admin, User $manager, User $employee): void
    {
        $archiver = 'Epesi\\Modules\\Mail\\Services\\MailArchiver';

        if (! class_exists($archiver) || ! Schema::hasTable('epesi_mails')) {
            return;
        }

        $messages = self::MAILS;
        $topics = ['delivery schedule', 'invoice details', 'product options', 'meeting notes', 'account update', 'service request', 'order status', 'renewal terms', 'pricing question', 'project timeline'];
        $adminEmail = $admin->contact?->collection('emails')->first()?->value ?? 'hello@acme.test';

        for ($index = 1; $index <= 94; $index++) {
            $assignedTo = $index <= 10 ? 'admin' : ($index % 2 === 0 ? 'manager' : 'employee');
            $topic = $topics[($index - 1) % count($topics)];
            $from = $index % 2 === 0
                ? 'Tony Stark <tony@stark.test>'
                : 'Bruce Wayne <bruce@wayne.test>';
            $to = match ($assignedTo) {
                'admin' => 'Setup User <'.$adminEmail.'>',
                'manager' => 'Morgan Manager <manager@example.com>',
                default => 'Eli Employee <employee@example.com>',
            };

            $messages[] = [
                'id' => sprintf('demo-mail-%03d@acme.test', $index),
                'by' => $assignedTo,
                'days' => 11 + $index,
                'time' => sprintf('%02d:%02d', 8 + ($index % 9), ($index * 7) % 60),
                'from' => $from,
                'to' => $to,
                'subject' => sprintf('Demo correspondence %03d: %s', $index, $topic),
                'text' => sprintf("Hello,\n\nThis is a sample message about %s. Please review the details and follow up with the customer when convenient.\n\nBest regards,\n%s", $topic, $index % 2 === 0 ? 'Tony Stark' : 'Bruce Wayne'),
            ];
        }

        foreach ($messages as $message) {
            $user = match ($message['by']) {
                'admin' => $admin,
                'manager' => $manager,
                default => $employee,
            };
            $mail = app($archiver)->archive($this->rawMail($message), $user);

            DemoData::remember($mail);
            DemoData::remember($mail->thread);
        }
    }

    /**
     * An RFC 822 message from one of self::MAILS: plain text, or
     * multipart/alternative when it has an HTML body too.
     *
     * @param  array<string, mixed>  $message
     */
    protected function rawMail(array $message): string
    {
        $date = now()->subDays($message['days'])->setTimeFromTimeString($message['time']);

        $lines = [
            'Message-ID: <'.$message['id'].'>',
            ...(isset($message['reply']) ? ['In-Reply-To: <'.$message['reply'].'>', 'References: <'.$message['reply'].'>'] : []),
            'Date: '.$date->toRfc2822String(),
            'From: '.$message['from'],
            'To: '.$message['to'],
            'Subject: '.$message['subject'],
            'MIME-Version: 1.0',
        ];

        $part = fn (string $type, string $body): array => [
            'Content-Type: '.$type.'; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $body,
        ];

        if (isset($message['html'])) {
            $lines = [
                ...$lines,
                'Content-Type: multipart/alternative; boundary="demo"',
                '',
                '--demo',
                ...$part('text/plain', $message['text']),
                '--demo',
                ...$part('text/html', $message['html']),
                '--demo--',
            ];
        } else {
            $lines = [...$lines, ...$part('text/plain', $message['text'])];
        }

        return str_replace("\n", "\r\n", implode("\n", $lines));
    }

    /**
     * Three Markdown sticky notes for the user (notes are private, so every
     * account is given its own), one per column of the Notes
     * tab. StickyNotes is a core module, so it is always installed.
     */
    protected function stickyNotes(User $user): void
    {
        $notes = [
            ['Today', 'yellow', "## Priorities\n\n1. Call **Wayne Enterprises** about the renewal\n2. Send the *updated quote* to Acme\n3. Prepare Friday's team meeting\n\n> Finish the first two before lunch."],
            ['Ideas', 'green', "- Weekly customer **newsletter**\n- Tag contacts by `industry`\n- Follow up on every phone call within 24 hours\n\nSee [Epesi](https://epesi.org) for more."],
            ['Meeting notes', 'blue', "### Sales sync\n\n- Pipeline reviewed\n- Two new leads assigned\n- Next review on **Monday**\n\n```\nAction: update the forecast\n```"],
        ];

        foreach ($notes as $col => [$title, $color, $body]) {
            $sticky = new StickyNote;
            $sticky->forceFill([
                'user_id' => $user->id,
                'title' => $title,
                'body' => $body,
                'color' => $color,
                'active' => true,
                'col' => $col,
                'position' => 0,
            ])->save();
            DemoData::remember($sticky);
        }
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
            $at = now()->subMinutes((count(self::SHOUTS) - $i) * $this->rand(40, 200));

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
     * remembers it as demo data, then its collections (details()).
     *
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @param  array<string, mixed>  $attributes
     * @param  array<string, list<array<string, mixed>>>  $collections  field => items
     * @return T
     */
    protected function record(string $class, array $attributes, array $collections = []): Model
    {
        $record = new $class;
        $record->forceFill($attributes)->save();
        DemoData::remember($record);

        foreach ($collections as $field => $items) {
            $record->syncCollection($field, $items);
        }

        return $record;
    }

    /**
     * A contact's or company's collections: its address as a Business one,
     * its numbers by kind (a blank one left out) and its website.
     *
     * @param  array<string, mixed>|null  $address
     * @param  array<string, ?string>  $phones  kind => number
     * @return array<string, list<array<string, mixed>>> field => items
     */
    protected function details(?array $address = null, array $phones = [], ?string $website = null, ?string $email = null, string $emailKind = 'work'): array
    {
        return [
            'addresses' => $address === null ? [] : [['kind' => 'business', ...$address]],
            'phones' => array_map(fn (string $kind, ?string $number): array => ['kind' => $kind, 'value' => $number], array_keys($phones), $phones),
            'online_accounts' => $website === null ? [] : [['kind' => 'website', 'value' => $website]],
            'emails' => $email === null ? [] : [['kind' => $emailKind, 'value' => $email]],
        ];
    }

    /**
     * @template T
     *
     * @param  array<T>  $items
     * @return T
     */
    /**
     * The demo's own generator: seeded for the same demo every time, and
     * apart from PHP's global one, which anything that saves a record may draw from.
     */
    protected function rng(): Randomizer
    {
        return $this->rng ??= new Randomizer;
    }

    protected function rand(int $min, int $max): int
    {
        return $this->rng()->getInt($min, $max);
    }

    protected function pick(array $items): mixed
    {
        return $items[$this->rand(0, count($items) - 1)];
    }

    protected function phone(string $prefix): string
    {
        return $prefix.' '.$this->rand(200, 899).' '.$this->rand(100, 999).' '.sprintf('%03d', $this->rand(0, 999));
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
