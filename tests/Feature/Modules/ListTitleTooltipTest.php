<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use Epesi\Modules\CRM\Meetings\Filament\Resources\Meetings\Pages\ListMeetings;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Filament\Resources\PhoneCalls\Pages\ListPhoneCalls;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\ListTasks;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Hovering a Task's or Meeting's title, or a Phone Call's subject, in the list
 * shows its description — Epesi's hover on those lists.
 */
class ListTitleTooltipTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_the_title_shows_the_description_on_hover(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        Filament::setCurrentPanel(Filament::getPanel('main'));

        $cases = [
            [ListTasks::class, Task::class, 'title', []],
            [ListMeetings::class, Meeting::class, 'title', ['date' => '2026-10-01', 'time' => '10:00:00']],
            [ListPhoneCalls::class, PhoneCall::class, 'subject', ['called_at' => '2026-10-01 10:00:00']],
        ];

        foreach ($cases as [$page, $model, $column, $required]) {
            $record = fn (string $title, array $extra = []): Model => $model::create([$column => $title, 'permission' => RecordPermission::Public] + $extra + $required);
            $described = $record('With notes', ['description' => "Bring the contract.\nAsk about the discount."]);
            $bare = $record('Without notes');

            $list = Livewire::test($page)->assertCanSeeTableRecords([$described, $bare]);

            $this->assertSame("Bring the contract.\nAsk about the discount.", $this->tooltip($list, $column, $described), $model);
            $this->assertNull($this->tooltip($list, $column, $bare), $model);
        }
    }

    public function test_a_long_description_is_cut_short(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        Filament::setCurrentPanel(Filament::getPanel('main'));

        $task = Task::create(['title' => 'Long one', 'description' => str_repeat('word ', 300), 'permission' => RecordPermission::Public]);

        $tooltip = $this->tooltip(Livewire::test(ListTasks::class), 'title', $task);

        $this->assertLessThanOrEqual(503, mb_strlen($tooltip));
        $this->assertStringEndsWith('...', $tooltip);
    }

    private function tooltip(mixed $list, string $column, Model $record): ?string
    {
        return $list->instance()->getTable()->getColumn($column)->record($record)->getTooltip();
    }
}
