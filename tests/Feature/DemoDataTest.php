<?php

namespace Tests\Feature;

use App\Enums\RecordPermission;
use App\Filament\Administration\Pages\DemoDataPage;
use App\Models\User;
use App\Support\DemoData;
use Database\Seeders\DemoDataSeeder;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\Shoutbox\Models\Message;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * The demo data the setup wizard can load, and removing it again from
 * Administration → Demo data.
 */
class DemoDataTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected User $admin;

    protected Company $ownCompany;

    protected Contact $ownContact;

    protected function setUp(): void
    {
        parent::setUp();

        // As after setup: the administrator, with the contact and company
        // from the "Your company" step, then the demo data.
        $this->admin = $this->userWithRole('super_admin');
        $this->actingAs($this->admin);
        $this->ownCompany = Company::create(['company_name' => 'Kowalski Sp. z o.o.', 'permission' => RecordPermission::Public]);
        $this->ownContact = Contact::create(['first_name' => 'Jan', 'last_name' => 'Kowalski', 'company_id' => $this->ownCompany->id, 'user_id' => $this->admin->id]);

        (new DemoDataSeeder)->run($this->admin);
    }

    protected function records(string $model): int
    {
        return $model::query()->withoutGlobalScopes()->count();
    }

    public function test_the_demo_fills_the_lists(): void
    {
        $this->assertSame(101, $this->records(Company::class), '100 demo companies and your own');
        $this->assertSame(101, $this->records(Contact::class));
        $this->assertSame(30, $this->records(Task::class));
        $this->assertSame(30, $this->records(PhoneCall::class));
        $this->assertSame(30, $this->records(Meeting::class));
        $this->assertSame(25, Message::query()->count());
        $this->assertSame(3, User::query()->count(), 'you, the manager and the employee');

        // Spread over their creators, not all yours.
        $this->assertGreaterThan(1, Company::query()->withoutGlobalScopes()->distinct()->count('created_by'));
        $this->assertSame(0, Task::query()->withoutGlobalScopes()->whereNull('created_by')->count());
        $this->assertTrue(DemoData::present());
    }

    public function test_it_is_the_same_demo_every_time(): void
    {
        $generated = fn (): array => Company::query()->withoutGlobalScopes()->where('email', 'like', 'office@%')->orderBy('id')->pluck('company_name')->all();
        $first = $generated();

        DemoData::remove();
        (new DemoDataSeeder)->run($this->admin);

        $this->assertCount(97, $first);
        $this->assertSame($first, $generated());
    }

    public function test_removing_it_keeps_you_and_what_you_added(): void
    {
        $demoContact = Contact::query()->withoutGlobalScopes()->where('email', 'bruce@wayne.test')->sole();
        $demoCompany = $demoContact->company;

        // Something real, added after setup, that refers to demo records.
        $ownTask = Task::create(['title' => 'Call the bank', 'permission' => RecordPermission::Public]);
        $ownTask->customers()->attach($demoContact->id);
        $ownTask->customerCompanies()->attach($demoCompany->id);

        DemoData::remove();

        $this->assertSame([$this->admin->id], User::query()->pluck('id')->all());
        $this->assertSame([$this->ownCompany->id], Company::query()->withoutGlobalScopes()->withTrashed()->pluck('id')->all());
        $this->assertSame([$this->ownContact->id], Contact::query()->withoutGlobalScopes()->withTrashed()->pluck('id')->all());
        $this->assertSame([$ownTask->id], Task::query()->withoutGlobalScopes()->withTrashed()->pluck('id')->all());
        $this->assertSame(0, $this->records(PhoneCall::class));
        $this->assertSame(0, $this->records(Meeting::class));
        $this->assertSame(0, Message::query()->count());

        // Links to the demo records, and what hung off them, are gone.
        $this->assertSame(0, $ownTask->customers()->count());
        $this->assertSame(0, $ownTask->customerCompanies()->count());
        $this->assertSame(0, Activity::query()->where('subject_type', 'contact')->where('subject_id', $demoContact->id)->count());
        $this->assertSame(0, DB::table('model_has_roles')->where('model_id', '!=', $this->admin->id)->count());
        $this->assertSame(0, DB::table('epesi_watchdog_subscriptions')->whereNot(fn ($q) => $q->where('subscribable_type', 'task')->where('subscribable_id', $ownTask->id)
            ->orWhere(fn ($q) => $q->where('subscribable_type', 'contact')->where('subscribable_id', $this->ownContact->id))
            ->orWhere(fn ($q) => $q->where('subscribable_type', 'company')->where('subscribable_id', $this->ownCompany->id)))->count());

        $this->assertFalse(DemoData::present());
        $this->assertTrue($this->admin->fresh()->hasRole('super_admin'));
    }

    public function test_an_administrator_removes_it_from_the_administration_panel(): void
    {
        $this->get(DemoDataPage::getUrl(panel: 'administration'))
            ->assertOk()
            ->assertSee('This system contains demo data');

        Filament::setCurrentPanel('administration');
        Livewire::test(DemoDataPage::class)
            ->callAction('remove')
            ->assertNotified('The demo data was removed');

        $this->assertSame(1, $this->records(Company::class));
        $this->assertFalse(DemoDataPage::shouldRegisterNavigation(), 'gone from the menu');
    }

    public function test_only_administrators_can(): void
    {
        $this->actingAs($this->userWithRole('manager'));

        $this->get(DemoDataPage::getUrl(panel: 'administration'))->assertForbidden();
    }
}
