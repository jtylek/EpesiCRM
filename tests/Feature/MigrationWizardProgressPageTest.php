<?php

namespace Tests\Feature;

use Epesi\Modules\MigrationWizard\Background\BackgroundRunner;
use Epesi\Modules\MigrationWizard\Filament\Pages\MigrationProgress;
use Epesi\Modules\MigrationWizard\Http\MigrationProgressJsonController;
use Epesi\Modules\MigrationWizard\Import\ImportProgress;
use Epesi\Modules\MigrationWizard\Orchestration\RunnerFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** The browser page needs no setup code; it exists only where a legacy installation is detected. */
class MigrationWizardProgressPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('setup');
        File::delete(storage_path('app/migration-wizard/state.json'));
    }

    protected function tearDown(): void
    {
        File::delete(storage_path('app/migration-wizard/state.json'));
        File::delete(storage_path('app/migration-wizard/background.json'));
        ImportProgress::clear();
        parent::tearDown();
    }

    public function test_the_page_opens_without_a_setup_code_when_a_legacy_installation_is_detected(): void
    {
        $this->app->instance('migration-wizard.legacy_detected', true);

        Livewire::test(MigrationProgress::class)
            ->assertSee('epesi migration wizard')
            ->assertDontSee('epesi setup')
            ->assertSee('Where the migration stands')
            ->assertSee('Confirm my backup')
            ->assertSee('Softaculous')
            ->assertDontSee('setup code')
            ->assertDontSee('Record the backup')
            ->assertDontSee('restore test')
            // Not yet confirmed: Continue must not show next to the backup form (there is nothing to continue).
            ->assertDontSee('Continue');
    }

    public function test_the_typed_backup_confirmation_is_the_only_backup_step(): void
    {
        $this->app->instance('migration-wizard.legacy_detected', true);

        Livewire::test(MigrationProgress::class)
            ->set('typed', 'wrong')
            ->call('confirmBackup')
            ->assertSet('message', 'Type "backed up" to confirm.')
            // Still unconfirmed: still just the one button.
            ->assertDontSee('Continue')
            ->set('typed', 'backed up')
            ->call('confirmBackup')
            ->assertSet('message', 'Your backup before the upgrade is confirmed.')
            ->assertDontSee('Confirm my backup')
            // Confirmed: now Continue is the only button.
            ->assertSee('Continue');

        $state = json_decode((string) file_get_contents(storage_path('app/migration-wizard/state.json')), true);
        $this->assertSame('backed_up', $state['phase']);
        $this->assertArrayHasKey('preupgrade_backup', $state);
    }

    public function test_without_a_legacy_installation_the_page_is_not_there(): void
    {
        $this->app->instance('migration-wizard.legacy_detected', false);

        Livewire::test(MigrationProgress::class)->assertStatus(404);
        $this->assertFileDoesNotExist(storage_path('app/migration-wizard/state.json'));
    }

    public function test_an_action_does_nothing_once_no_legacy_installation_is_detected(): void
    {
        $this->app->instance('migration-wizard.legacy_detected', true);
        $page = Livewire::test(MigrationProgress::class);

        $this->app->instance('migration-wizard.legacy_detected', false);
        // The page answers 404 to the very first update; a second update would have no snapshot to work from.
        $page->set('typed', 'backed up')->assertStatus(404);

        $this->assertFileDoesNotExist(storage_path('app/migration-wizard/state.json'));
    }

    public function test_it_is_not_available_in_demo_mode(): void
    {
        $this->app->instance('migration-wizard.legacy_detected', true);
        config(['demo.enabled' => true]);

        Livewire::test(MigrationProgress::class)->assertStatus(404);
    }

    public function test_the_administrator_form_says_what_is_wrong_and_does_not_start(): void
    {
        $this->app->instance('migration-wizard.legacy_detected', true);

        Livewire::test(MigrationProgress::class)
            ->set('adminId', 1)
            ->set('password', 'first-password')
            ->set('passwordAgain', 'other-password')
            ->call('saveAdministratorAndContinue')
            ->assertSet('adminError', 'The passwords do not match.')
            ->assertSet('message', null)
            ->assertSet('running', false)
            ->set('passwordAgain', 'first-password')
            ->call('saveAdministratorAndContinue')
            ->assertSet('adminError', null)
            ->assertSet('running', true);
    }

    public function test_continue_answers_at_once_and_the_step_starts_with_the_next_poll(): void
    {
        $this->app->instance('migration-wizard.legacy_detected', true);

        // Without the backup confirmation the step itself would refuse; Continue does not run it in its own request.
        Livewire::test(MigrationProgress::class)
            ->call('start')
            ->assertSet('running', true)
            ->assertSet('message', null)
            ->assertSeeHtml('wire:poll.2s.keep-alive="advance"')
            ->call('advance')
            ->assertSet('running', false)
            ->assertNotSet('message', null);
    }

    public function test_a_running_page_goes_to_the_sign_in_once_the_migration_is_finished(): void
    {
        $this->app->instance('migration-wizard.legacy_detected', true);
        $page = Livewire::test(MigrationProgress::class)->set('running', true);
        $notRunning = Livewire::test(MigrationProgress::class);

        File::ensureDirectoryExists(storage_path('app/migration-wizard'));
        File::put(storage_path('app/migration-wizard/state.json'), json_encode(['version' => 1, 'phase' => 'core_complete_with_preserved_data']));

        // Still running: the poll goes on until the browser has left, and without this it would end in a 404.
        $page->call('advance')
            ->assertSet('running', true)
            ->assertRedirect(url('/'));

        // Continue pressed at that moment goes there too.
        $notRunning->call('start')->assertRedirect(url('/'));

        // Opened again later, it goes there too instead of answering 404.
        Livewire::test(MigrationProgress::class)->assertRedirect(url('/'));
    }

    public function test_verification_and_cleanup_report_their_progress_through_progress_json(): void
    {
        ImportProgress::stage('Verifying the moved files', 0, 75152);
        ImportProgress::stage('Verifying the moved files', 75152, 75152);
        $data = (new MigrationProgressJsonController)()->getData(true);
        $this->assertSame('Verifying the moved files: 75,152 of 75,152 files', $data['line']);
        $this->assertNull($data['estimate'], 'a complete count has no time left to estimate');
        $this->assertFalse($data['lock_held']);

        ImportProgress::stage('Data cleanup: removing old tables', 3, 164, 'tables');
        $this->assertSame('Data cleanup: removing old tables: 3 of 164 tables', (new MigrationProgressJsonController)()->getData(true)['line']);

        ImportProgress::stage('Verifying data: counting the records');
        $this->assertSame('Verifying data: counting the records...', (new MigrationProgressJsonController)()->getData(true)['line']);

        ImportProgress::clear();
        $this->assertNull((new MigrationProgressJsonController)()->getData(true)['line']);
    }

    public function test_progress_json_is_not_there_in_demo_mode(): void
    {
        config(['demo.enabled' => true]);

        $this->expectException(HttpException::class);
        (new MigrationProgressJsonController)();
    }

    public function test_a_reopened_page_watches_the_background_process_that_is_still_running(): void
    {
        $this->app->instance('migration-wizard.legacy_detected', true);
        File::ensureDirectoryExists(storage_path('app/migration-wizard'));
        File::put(storage_path('app/migration-wizard/background.json'), json_encode(['started_at' => time() - 60]));

        RunnerFactory::lock()->withLock('test background', function (): void {
            Livewire::test(MigrationProgress::class)
                ->assertSet('background', true)
                ->assertSet('running', true)
                ->assertSee('runs on the server as a background process')
                ->assertDontSee('Another migration run is active')
                ->assertDontSee('Please keep this browser window open');
        });

        // Once it has ended, the page forgets it.
        Livewire::test(MigrationProgress::class)->assertSet('background', false);
        $this->assertNull(BackgroundRunner::startedAt());
    }
}
