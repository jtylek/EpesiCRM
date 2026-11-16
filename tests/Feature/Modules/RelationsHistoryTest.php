<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\CreateTask;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\EditTask;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\TaskResource;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\RecordBrowser\History\HistoryChanges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\ActivityLogStatus;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/** A belongsToMany field (Employees) is written to Record History, though a pivot sync fires no model event. */
class RelationsHistoryTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function setUp(): void
    {
        parent::setUp();

        activity()->enableLogging();
    }

    /** @return array{0: Contact, 1: Contact} */
    protected function people(): array
    {
        $user = $this->userWithRole('employee');
        $home = Company::create(['company_name' => 'Home']);
        Contact::create(['last_name' => 'Me', 'first_name' => 'Ann', 'company_id' => $home->id, 'user_id' => $user->id]);
        $this->actingAs($user);

        return [
            Contact::create(['last_name' => 'Worker', 'first_name' => 'Wendy', 'company_id' => $home->id]),
            Contact::create(['last_name' => 'Tylek', 'first_name' => 'Janusz', 'company_id' => $home->id]),
        ];
    }

    protected function summary(Activity $entry): string
    {
        return strip_tags((string) HistoryChanges::summary($entry, HistoryChanges::fieldsFor(TaskResource::class)));
    }

    public function test_create_and_edit_log_the_employees(): void
    {
        [$wendy, $janusz] = $this->people();

        Livewire::test(CreateTask::class)
            ->fillForm(['title' => 'Send offer', 'employees' => [$wendy->id]])
            ->call('create')
            ->assertHasNoFormErrors();

        $task = Task::sole();
        $created = Activity::query()->where('log_name', 'task')->where('event', 'created')->sole();
        $this->assertSame([(string) $wendy->id], $created->properties['attributes']['employees']);
        $this->assertStringContainsString('Employees: Wendy Worker', $this->summary($created));

        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->fillForm(['employees' => [$wendy->id, $janusz->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $updated = Activity::query()->where('log_name', 'task')->where('event', 'updated')->sole();
        $this->assertSame([(string) $wendy->id], $updated->properties['old']['employees']);
        $this->assertStringContainsString('+ Janusz Tylek', $this->summary($updated));
    }

    public function test_an_edit_merges_into_the_entry_of_the_same_save_and_unchanged_logs_nothing(): void
    {
        [$wendy, $janusz] = $this->people();
        $task = Task::create(['title' => 'Send offer']);
        $task->employees()->sync([$wendy->id]);
        Activity::query()->delete();

        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->fillForm(['employees' => [$wendy->id]])
            ->call('save');
        $this->assertSame(0, Activity::query()->where('log_name', 'task')->count());

        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->fillForm(['title' => 'Send the offer', 'employees' => [$janusz->id]])
            ->call('save');

        $entry = Activity::query()->where('log_name', 'task')->sole();
        $this->assertArrayHasKey('title', $entry->properties['attributes']);
        $this->assertSame([(string) $janusz->id], $entry->properties['attributes']['employees']);
        $this->assertStringContainsString('Wendy Worker', $this->summary($entry));
    }

    public function test_nothing_is_logged_when_the_activity_log_is_off(): void
    {
        [$wendy] = $this->people();
        $task = Task::create(['title' => 'Send offer']);
        Activity::query()->delete();

        app(ActivityLogStatus::class)->disable();
        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->fillForm(['employees' => [$wendy->id]])
            ->call('save');
        app(ActivityLogStatus::class)->enable();

        $this->assertSame(0, Activity::query()->where('log_name', 'task')->count());
    }
}
