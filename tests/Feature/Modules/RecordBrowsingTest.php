<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use App\Enums\RecordStatus;
use App\Support\StatusField;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\EditCompany;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\ListCompanies;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\ViewCompany;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ListContacts;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Meetings\Filament\Resources\Meetings\Pages\ListMeetings;
use Epesi\Modules\CRM\PhoneCalls\Filament\Resources\PhoneCalls\Pages\ListPhoneCalls;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\ListTasks;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\ViewTask;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\RecordBrowser\Browsing\BrowseMode;
use Epesi\Modules\RecordBrowser\Browsing\Favorites;
use Epesi\Modules\RecordBrowser\Browsing\RecentRecords;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * The All / Favorites / Recent tabs on a recordset's list — Epesi's
 * RecordBrowser browse modes — and the CRM lists' defaults: Contacts and
 * Companies alphabetical; Tasks, Phone Calls and Meetings without closed ones.
 */
class RecordBrowsingTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_contacts_and_companies_list_alphabetically(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $zeta = $this->company('Zeta');
        $alpha = $this->company('Alpha');
        $mid = $this->company('Mid');

        Livewire::test(ListCompanies::class)
            ->assertCanSeeTableRecords([$alpha, $mid, $zeta], inOrder: true);

        $nowak = Contact::create(['last_name' => 'Nowak', 'first_name' => 'Anna', 'permission' => RecordPermission::Public]);
        $jan = Contact::create(['last_name' => 'Kowalski', 'first_name' => 'Jan', 'permission' => RecordPermission::Public]);
        $adam = Contact::create(['last_name' => 'Kowalski', 'first_name' => 'Adam', 'permission' => RecordPermission::Public]);

        Livewire::test(ListContacts::class)
            ->assertCanSeeTableRecords([$adam, $jan, $nowak], inOrder: true);
    }

    public function test_opening_a_record_puts_it_on_the_recent_list_which_keeps_fifty(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);

        $companies = collect(range(1, 51))->map(fn (int $i): Company => $this->company("Company {$i}"));

        foreach ($companies as $company) {
            $this->travel(1)->seconds();
            RecentRecords::visit($user, $company, 50);
        }

        $visits = RecentRecords::visitsOf($user, 'company');
        $this->assertCount(50, $visits);
        $this->assertArrayNotHasKey($companies[0]->id, $visits, 'the oldest visit fell off');

        $this->travel(1)->seconds();
        $this->get(ViewCompany::getUrl(['record' => $companies[0]]))->assertOk();

        $visits = RecentRecords::visitsOf($user, 'company');
        $this->assertCount(50, $visits);
        $this->assertArrayHasKey($companies[0]->id, $visits, 'opening it again brings it back');
        $this->assertArrayNotHasKey($companies[1]->id, $visits, '... and pushes the next oldest out');

        $this->travel(1)->seconds();
        $this->get(EditCompany::getUrl(['record' => $companies[1]]))->assertOk();
        $this->assertArrayHasKey($companies[1]->id, RecentRecords::visitsOf($user, 'company'), 'the Edit page counts too');
    }

    public function test_the_recent_tab_shows_the_latest_visit_first_and_is_remembered(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);

        $alpha = $this->company('Alpha');
        $beta = $this->company('Beta');
        $never = $this->company('Gamma');

        RecentRecords::visit($user, $alpha, 50);
        $this->travel(1)->seconds();
        RecentRecords::visit($user, $beta, 50);

        Livewire::test(ListCompanies::class)
            ->assertSet('activeTab', 'all')
            ->assertSeeHtml('epesi-browse-tabs')
            ->assertSeeHtml("\$set('activeTab', 'recent')")
            ->assertTableColumnHidden('recordbrowser_visited_at')
            ->set('activeTab', 'recent')
            ->assertCanSeeTableRecords([$beta, $alpha], inOrder: true)
            ->assertCanNotSeeTableRecords([$never])
            ->assertTableColumnVisible('recordbrowser_visited_at')
            ->assertTableColumnStateSet('recordbrowser_visited_at', now()->toDateTimeString(), $beta);

        $this->assertSame(BrowseMode::Recent, BrowseMode::rememberedFor($user, 'company'));

        Livewire::test(ListCompanies::class)
            ->assertSet('activeTab', 'recent')
            ->sortTable('company_name')
            ->assertCanSeeTableRecords([$alpha, $beta], inOrder: true);

        Livewire::test(ListContacts::class)->assertSet('activeTab', 'all');
    }

    public function test_a_star_marks_a_favorite_and_the_favorites_tab_lists_them(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);

        $alpha = $this->company('Alpha');
        $beta = $this->company('Beta');

        Livewire::test(ListCompanies::class)
            ->callAction(TestAction::make('favorite')->table($beta));
        $this->assertTrue(Favorites::has($user, $beta));

        Livewire::test(ListCompanies::class)
            ->set('activeTab', 'favorites')
            ->assertCanSeeTableRecords([$beta])
            ->assertCanNotSeeTableRecords([$alpha]);

        Livewire::test(ViewCompany::class, ['record' => $beta->getKey()])
            ->callAction('favorite');
        $this->assertFalse(Favorites::has($user, $beta));
    }

    public function test_a_record_made_private_leaves_everyone_elses_tabs(): void
    {
        $author = $this->userWithRole('employee');
        $other = $this->userWithRole('employee');

        $this->actingAs($author);
        $company = $this->company('Secret plans');
        Favorites::add($other, $company);
        RecentRecords::visit($other, $company, 50);

        $company->update(['permission' => RecordPermission::Private]);

        $this->actingAs($other);
        Livewire::test(ListCompanies::class)
            ->set('activeTab', 'recent')
            ->assertCanNotSeeTableRecords([$company])
            ->set('activeTab', 'favorites')
            ->assertCanNotSeeTableRecords([$company]);
    }

    public function test_tasks_phone_calls_and_meetings_have_the_tabs_and_hide_finished_records(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);

        // As a request would have it: Phone Calls' Contact filter names its
        // options after the contact resource, found through the panel.
        Filament::setCurrentPanel(Filament::getPanel('main'));

        foreach ([ListTasks::class, ListPhoneCalls::class, ListMeetings::class] as $list) {
            Livewire::test($list)
                ->assertSeeHtml('epesi-browse-tabs')
                ->assertSet('tableFilters.status.value', StatusField::NOT_CLOSED);
        }

        $task = fn (string $title, RecordStatus $status): Task => Task::create([
            'title' => $title, 'status' => $status, 'permission' => RecordPermission::Public,
        ]);
        $open = $task('Call the bank', RecordStatus::Open);
        $onHold = $task('Order toner', RecordStatus::OnHold);
        $closed = $task('Send the offer', RecordStatus::Closed);
        $canceled = $task('Book the venue', RecordStatus::Canceled);

        Livewire::test(ListTasks::class)
            ->assertCanSeeTableRecords([$open, $onHold])
            ->assertCanNotSeeTableRecords([$closed, $canceled])
            ->filterTable('status', RecordStatus::Closed->value)
            ->assertCanSeeTableRecords([$closed])
            ->assertCanNotSeeTableRecords([$open, $onHold, $canceled])
            ->filterTable('status', null)
            ->assertCanSeeTableRecords([$open, $onHold, $closed, $canceled]);

        $this->get(ViewTask::getUrl(['record' => $open]))->assertOk();
        $this->assertArrayHasKey($open->id, RecentRecords::visitsOf($user, 'task'));
    }

    protected function company(string $name): Company
    {
        return Company::create(['company_name' => $name, 'permission' => RecordPermission::Public]);
    }
}
