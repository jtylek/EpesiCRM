<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use App\Models\User;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\EditTask;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * `Field::crits()` narrows what a relation field offers, but never what the
 * record already links to — Tasks' Employees picker, scoped to your own
 * company's staff, is the case that broke: someone who'd since left showed
 * on the View page and vanished from the Edit form, still linked. They show
 * on the form and can be removed, but aren't offered as a choice.
 */
class RelationCritsTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    private Company $home;

    private Contact $colleague;

    private Contact $leaver;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('main'));

        $this->home = Company::create(['company_name' => 'Home']);
        $elsewhere = Company::create(['company_name' => 'Elsewhere']);

        $user = $this->userWithRole('employee');
        Contact::create(['last_name' => 'Me', 'first_name' => 'Ann', 'company_id' => $this->home->id, 'user_id' => $user->id]);
        $this->actingAs(User::find($user->id));

        $this->colleague = Contact::create(['last_name' => 'Colleague', 'first_name' => 'Cal', 'company_id' => $this->home->id]);
        $this->leaver = Contact::create(['last_name' => 'Leaver', 'first_name' => 'Lee', 'company_id' => $elsewhere->id]);
    }

    public function test_a_linked_record_outside_the_crits_can_be_seen_and_removed(): void
    {
        $task = Task::create(['title' => 'Dial', 'permission' => RecordPermission::Public]);
        $task->employees()->sync([$this->colleague->id, $this->leaver->id]);

        Livewire::test(EditTask::class, ['record' => $task->getKey()])
            ->assertSchemaStateSet(['employees' => [(string) $this->colleague->id, (string) $this->leaver->id]])
            ->fillForm(['employees' => [(string) $this->colleague->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$this->colleague->id], $task->employees()->pluck('contacts.id')->all());
    }

    public function test_a_linked_record_outside_the_crits_is_labelled_but_never_offered(): void
    {
        $task = Task::create(['title' => 'Dial', 'permission' => RecordPermission::Public]);
        $task->employees()->sync([$this->colleague->id, $this->leaver->id]);

        $page = Livewire::test(EditTask::class, ['record' => $task->getKey()]);
        $select = $page->instance()->form->getComponent(
            fn ($component): bool => $component instanceof Select && $component->getName() === 'employees',
        );

        $this->assertArrayHasKey($this->leaver->id, $select->getOptionLabels());
        $this->assertArrayNotHasKey($this->leaver->id, $select->getOptions());
        $this->assertArrayHasKey($this->colleague->id, $select->getOptions());
        $this->assertSame([], $select->getSearchResults('Leaver'));
    }

    public function test_the_crits_still_keep_new_choices_out(): void
    {
        $task = Task::create(['title' => 'Dial', 'permission' => RecordPermission::Public]);
        $task->employees()->sync([$this->colleague->id]);

        Livewire::test(EditTask::class, ['record' => $task->getKey()])
            ->fillForm(['employees' => [(string) $this->colleague->id, (string) $this->leaver->id]])
            ->call('save')
            ->assertHasFormErrors(['employees.1']);

        $this->assertSame([$this->colleague->id], $task->employees()->pluck('contacts.id')->all());
    }
}
