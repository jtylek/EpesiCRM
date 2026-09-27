<?php

namespace Tests\Feature;

use App\Enums\RecordPermission;
use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HasResourceIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\EditTask;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\ViewTask;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Filament\Facades\Filament;
use Filament\Resources\Pages\Page as ResourcePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Every page's header is its sidebar icon and name, as on Contacts
 * ("(icon) Contacts > List"), never Filament's default large title. The tests
 * boot every module from its manifest, so a page a module adds is checked here
 * as well.
 */
class PageHeaderTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_every_panel_page_has_the_icon_breadcrumb_and_no_heading(): void
    {
        $missing = [];

        foreach (Filament::getPanels() as $panel) {
            $pages = $panel->getPages();

            foreach ($panel->getResources() as $resource) {
                foreach ($resource::getPages() as $registration) {
                    $pages[] = $registration->getPage();
                }
            }

            foreach (array_unique($pages) as $page) {
                // A vendor plugin's pages (Shield's Roles) aren't ours to change.
                if (! str_starts_with($page, 'App\\') && ! str_starts_with($page, 'Epesi\\')) {
                    continue;
                }

                $required = [
                    is_subclass_of($page, ResourcePage::class) ? HasResourceIconBreadcrumb::class : HasPageIconBreadcrumb::class,
                    HidesPageHeading::class,
                ];

                foreach (array_diff($required, class_uses_recursive($page)) as $trait) {
                    $missing[] = "{$page} (panel '{$panel->getId()}') lacks ".class_basename($trait);
                }
            }
        }

        $this->assertSame([], $missing);
    }

    public function test_the_dashboard_and_calendar_show_the_icon_breadcrumb(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        foreach (['/', '/calendar'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('fi-breadcrumbs', false)
                ->assertDontSee('fi-header-heading', false);
        }
    }

    public function test_a_record_page_leaves_the_record_title_out_of_the_breadcrumbs(): void
    {
        Filament::setCurrentPanel('main');
        $this->actingAs($this->userWithRole('employee'));
        $task = Task::create(['title' => 'Send offer', 'permission' => RecordPermission::Public]);

        foreach ([ViewTask::class => 'View', EditTask::class => 'Edit'] as $page => $label) {
            $breadcrumbs = array_values(array_map(strval(...), Livewire::test($page, ['record' => $task->getKey()])->instance()->getBreadcrumbs()));

            $this->assertCount(2, $breadcrumbs);
            $this->assertStringContainsString('Tasks', $breadcrumbs[0]);
            $this->assertSame($label, $breadcrumbs[1]);
        }
    }
}
