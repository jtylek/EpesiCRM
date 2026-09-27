<?php

namespace Tests\Feature;

use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\ListTasks;
use Epesi\Modules\CRM\Tasks\Filament\Widgets\TasksWidget;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Every table's "Per page" choices and default, set once in
 * AppServiceProvider::offerRowsPerPageUpToAScreenful().
 */
class TablePaginationTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_a_list_shows_15_rows_and_a_widget_keeps_its_own_default(): void
    {
        Filament::setCurrentPanel('main');
        $this->actingAs($this->userWithRole('employee'));

        $list = Livewire::test(ListTasks::class)->assertSet('tableRecordsPerPage', 15);
        $this->assertSame([10, 15, 20, 25, 30], $list->instance()->getTable()->getPaginationPageOptions());

        Livewire::test(TasksWidget::class, ['appletSettings' => []])->assertSet('tableRecordsPerPage', 10);
    }
}
