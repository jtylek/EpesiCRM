<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\ViewCompany;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\ViewTask;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\Reminders\Filament\RelationManagers\RemindersRelationManager;
use Filament\Actions\Testing\TestAction;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Each addon's tab on a View page carries how many records it lists, "0"
 * included, so the strip says which addons are worth opening.
 */
class AddonBadgesTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    /**
     * The addon Tab objects themselves — Record Info and History no longer
     * are Tabs (they're modal actions behind the kebab at the end of the
     * header actions row, see ViewRecord::recordInfoAndHistoryKebab()).
     *
     * @return array<Tab>
     */
    private function addonTabs(string $page, object $record): array
    {
        return Livewire::test($page, ['record' => $record->getRouteKey()])
            ->instance()
            ->getRelationManagersContentComponent()
            ->getDefaultChildComponents();
    }

    /**
     * @param  class-string  $page
     * @return array<string, ?string> tab label => badge
     */
    private function badges(string $page, object $record): array
    {
        return collect($this->addonTabs($page, $record))
            ->mapWithKeys(fn (Tab $tab): array => [$tab->getLabel() => $tab->getBadge()])
            ->all();
    }

    public function test_every_addon_tab_counts_its_records_and_shows_zero_when_empty(): void
    {
        $this->actingAs($this->userWithRole('manager'));
        $company = Company::create(['company_name' => 'Acme']);

        $empty = $this->badges(ViewCompany::class, $company);

        foreach (['Notes', 'Contacts', 'Tasks', 'Phone Calls', 'Meetings', 'E-mails'] as $addon) {
            $this->assertSame('0', $empty[$addon], "$addon should show 0");
        }

        Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann', 'company_id' => $company->id]);
        Contact::create(['last_name' => 'Jones', 'first_name' => 'Bob', 'company_id' => $company->id]);
        Attachment::addTo($company, 'First note', 'Hello');

        $filled = $this->badges(ViewCompany::class, $company);

        $this->assertSame('2', $filled['Contacts']);
        $this->assertSame('1', $filled['Notes']);
        $this->assertSame('0', $filled['Tasks']);
    }

    public function test_a_zero_is_gray_and_a_count_is_in_the_accent_color(): void
    {
        $this->actingAs($this->userWithRole('manager'));
        $company = Company::create(['company_name' => 'Acme']);
        Contact::create(['last_name' => 'Smith', 'first_name' => 'Ann', 'company_id' => $company->id]);

        $tabs = collect($this->addonTabs(ViewCompany::class, $company))
            ->keyBy(fn (Tab $tab): string => $tab->getLabel());

        $this->assertNull($tabs['Contacts']->getBadgeColor('1'));
        $this->assertSame('gray', $tabs['Tasks']->getBadgeColor('0'));
    }

    public function test_the_badge_is_on_the_rendered_page(): void
    {
        $this->actingAs($this->userWithRole('manager'));
        $company = Company::create(['company_name' => 'Acme']);

        $this->get(ViewCompany::getUrl(['record' => $company]))
            ->assertOk()
            ->assertSee('fi-badge', false);
    }

    public function test_the_reminders_count_is_only_the_reminders_the_user_may_see(): void
    {
        $author = $this->userWithRole('employee');
        $other = $this->userWithRole('employee');
        $manager = $this->userWithRole('manager');

        $this->actingAs($author);
        $task = Task::create(['title' => 'Call the bank', 'deadline' => '2026-09-25 14:00:00', 'permission' => RecordPermission::Public]);
        $reminder = $task->reminders()->create(['remind_at' => '2026-09-25 13:00:00']);
        $reminder->recipients()->sync([$author->id]);

        $this->assertSame('1', $this->badges(ViewTask::class, $task)['Reminders']);

        $this->actingAs($other);
        $this->assertSame('0', $this->badges(ViewTask::class, $task)['Reminders']);

        $this->actingAs($manager);
        $this->assertSame('1', $this->badges(ViewTask::class, $task)['Reminders']);
    }

    public function test_an_addon_action_tells_the_page_to_recount(): void
    {
        $manager = $this->userWithRole('manager');
        $this->actingAs($manager);
        $task = Task::create(['title' => 'Call the bank']);

        // Reminders stays writable on a View page (RemindersRelationManager
        // overrides isReadOnly()), unlike most addons.
        Livewire::test(RemindersRelationManager::class, ['ownerRecord' => $task, 'pageClass' => ViewTask::class])
            ->callAction(TestAction::make('create')->table(), data: ['timing' => 'at', 'remind_at' => now()->addHour()->toDateTimeString(), 'recipients' => [$manager->id]])
            ->assertDispatched('addon-changed');

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->dispatch('addon-changed')
            ->assertOk();
    }
}
