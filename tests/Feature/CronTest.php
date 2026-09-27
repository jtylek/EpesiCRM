<?php

namespace Tests\Feature;

use App\Filament\Administration\Pages\Cron;
use App\Models\User;
use App\Services\Cron\CronLog;
use App\Services\Cron\CronRunner;
use App\Services\Cron\CronToken;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Livewire\Livewire;
use RuntimeException;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Epesi's cron.php and Administration → Cron: the scheduled tasks run in
 * this process from cron.php (epesi:cron), the cron URL and the page, and
 * what they did is recorded — also when `schedule:run` runs them.
 */
class CronTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected Schedule $schedule;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // A schedule of the test's own: the modules' tasks would run for real,
        // and `schedule:run` would start them as processes on the app's database.
        $this->app->instance(Schedule::class, $this->schedule = new Schedule);
        $this->travelTo(Date::parse('2026-09-30 10:07:00'));

        // A user is what makes the system count as installed.
        $this->admin = $this->userWithRole('super_admin');

        // As the server's cron: whatever terminal the test itself runs in.
        CronLog::$terminal = false;
    }

    protected function tearDown(): void
    {
        CronLog::$terminal = null;

        parent::tearDown();
    }

    /** Runs cron.php at each of these times today, as the server's cron would. */
    protected function cronAt(string ...$times): void
    {
        foreach ($times as $time) {
            $this->travelTo(Date::parse("2026-09-30 {$time}"));
            $this->artisan('epesi:cron');
        }
    }

    protected function command(string $signature, callable $callback): void
    {
        $this->app->make(ConsoleKernel::class)->registerCommand(new ClosureCommand($signature, $callback));
    }

    public function test_cron_runs_the_due_tasks_in_this_process(): void
    {
        $this->command('cron-test:models {--model=*}', function () {
            $this->line(implode(',', $this->option('model')));
        });
        $models = $this->schedule->command('cron-test:models', ['--model' => [User::class]])->everyMinute();
        $hello = $this->schedule->call(function () {
            echo 'Hello from a closure';
        })->name('Say hello')->everyMinute();
        $nightly = $this->schedule->command('cron-test:models')->dailyAt('00:00');

        // Straight to the runner: under $this->artisan() a command writes to
        // the test's console, not to the runner's buffer.
        $results = app(CronRunner::class)->runDue('cli');

        $this->assertSame(['cron-test:models --model='.User::class, 'Say hello'], array_column($results, 'task'));
        $this->assertSame(['ok', 'ok'], array_column($results, 'status'));

        // The command ran here, its arguments intact, backslashes and all.
        $this->assertSame('ok', CronLog::task($models)->last_status);
        $this->assertSame(User::class, CronLog::task($models)->last_output);
        $this->assertSame('Hello from a closure', CronLog::task($hello)->last_output);
        $this->assertNull(CronLog::task($nightly)->last_started_at, 'not due at 10:07');

        $call = DB::table('cron_calls')->sole();
        $this->assertSame('cli', $call->via);
        $this->assertSame(2, (int) $call->tasks);
        $this->assertSame(0, (int) $call->failed);
    }

    public function test_cron_php_says_what_it_ran(): void
    {
        $this->schedule->call(fn () => null)->name('Say hello')->everyMinute();
        $this->schedule->call(fn () => null)->name('Tidy up')->everyMinute();

        $this->artisan('epesi:cron')
            ->expectsOutputToContain('Say hello: done')
            ->expectsOutputToContain('Tidy up: done')
            ->assertSuccessful();
    }

    public function test_a_task_runs_once_a_minute_however_often_cron_is_called(): void
    {
        $this->schedule->call(fn () => null)->name('Every minute')->everyMinute();

        $this->artisan('epesi:cron')->expectsOutputToContain('Every minute: done');

        $this->travelTo(Date::parse('2026-09-30 10:07:40'));
        $this->artisan('epesi:cron')->expectsOutput('No tasks were due.');

        $this->travelTo(Date::parse('2026-09-30 10:08:05'));
        $this->artisan('epesi:cron')->expectsOutputToContain('Every minute: done');
    }

    public function test_a_task_whose_time_cron_missed_catches_up(): void
    {
        $this->schedule->call(fn () => null)->name('Nightly')->dailyAt('00:00');

        // Seen for the first time: it waits for its next due time.
        $this->artisan('epesi:cron')->expectsOutput('No tasks were due.');

        // The host's cron runs at odd minutes: never at 00:00.
        $this->travelTo(Date::parse('2026-10-01 00:13:00'));
        $this->artisan('epesi:cron')->expectsOutputToContain('Nightly: done');

        $this->travelTo(Date::parse('2026-10-01 00:28:00'));
        $this->artisan('epesi:cron')->expectsOutput('No tasks were due.');
    }

    public function test_a_failed_task_is_recorded_with_what_it_said(): void
    {
        Exceptions::fake();
        $this->command('cron-test:fetch', function () {
            $this->error('The mail server said no.');

            return 1;
        });
        $fetch = $this->schedule->command('cron-test:fetch')->everyMinute();
        $coffee = $this->schedule->call(fn () => throw new RuntimeException('Out of coffee'))->name('Make coffee')->everyMinute();

        $this->assertSame(['failed', 'failed'], array_column(app(CronRunner::class)->runDue('cli'), 'status'));

        $this->assertSame('failed', CronLog::task($fetch)->last_status);
        $this->assertStringContainsString('The mail server said no.', CronLog::task($fetch)->last_output);
        $this->assertSame('failed', CronLog::task($coffee)->last_status);
        $this->assertStringContainsString('Out of coffee', CronLog::task($coffee)->last_output);
        $this->assertSame(2, (int) DB::table('cron_calls')->value('failed'));
        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'Out of coffee');

        // cron.php says so, and so does its exit code.
        $this->travelTo(Date::parse('2026-09-30 10:08:00'));
        $this->artisan('epesi:cron')
            ->expectsOutputToContain('cron-test:fetch: FAILED')
            ->expectsOutputToContain('Make coffee: FAILED')
            ->assertFailed();
    }

    public function test_a_command_that_is_not_registered_says_so(): void
    {
        Exceptions::fake();
        $missing = $this->schedule->command('cron-test:missing')->everyMinute();

        $this->assertSame(['failed'], array_column(app(CronRunner::class)->runDue('url'), 'status'));
        $this->assertStringContainsString('"cron-test:missing" isn\'t registered here', CronLog::task($missing)->last_output);
    }

    public function test_a_task_that_does_not_apply_is_left_out(): void
    {
        $this->schedule->call(fn () => null)->name('Only on demo')->everyMinute()->when(fn (): bool => false);

        $this->artisan('epesi:cron')->expectsOutput('No tasks were due.');
    }

    public function test_schedule_run_is_recorded_as_well(): void
    {
        $tidy = $this->schedule->call(fn () => null)->name('Tidy up')->everyMinute();

        // Outside tests, Laravel turns Symfony's command events into its own
        // CommandStarting/CommandFinished, which tell that schedule:run ran.
        $kernel = $this->app->make(ConsoleKernel::class);
        $kernel->rerouteSymfonyCommandEvents();
        $kernel->setArtisan(null);

        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertSame('schedule:run', CronLog::lastCall()->via);
        $this->assertSame('ok', CronLog::task($tidy)->last_status);
        $this->assertSame(1, (int) DB::table('cron_calls')->value('tasks'));
    }

    public function test_the_cron_url_runs_cron_only_with_its_token(): void
    {
        $this->schedule->call(fn () => null)->name('Tidy up')->everyMinute();

        $this->get('/cron')->assertForbidden();
        $this->get('/cron?token=wrong')->assertForbidden();
        $this->get('/cron?token[]=x')->assertForbidden();
        $this->assertSame(0, DB::table('cron_calls')->count());

        $this->get(CronToken::url())
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Tidy up: done');
        $this->assertSame('url', CronLog::lastCall()->via);

        // No session: nothing to set a cookie for.
        $this->get(CronToken::url())->assertCookieMissing(config('session.cookie'));

        // A new token: the old URL stops working.
        $old = CronToken::url();
        CronToken::regenerate();
        $this->get($old)->assertForbidden();
    }

    public function test_cron_waits_until_epesi_is_set_up(): void
    {
        config(['setup.marker_path' => storage_path('framework/testing/no-such-marker.json')]);
        User::query()->delete();
        $ran = false;
        $this->schedule->call(function () use (&$ran) {
            $ran = true;
        })->everyMinute();

        $this->artisan('epesi:cron')->expectsOutput('epesi is not set up yet.')->assertSuccessful();
        $this->get('/cron?token='.CronToken::current())->assertStatus(503);
        $this->assertFalse($ran);
    }

    public function test_the_command_line_is_split_back_into_its_arguments(): void
    {
        $this->assertSame(['reminders:send'], CronRunner::split('reminders:send'));
        $this->assertSame(['model:prune', '--model=App\Models\User'], CronRunner::split("model:prune --model='App\\Models\\User'"));
        $this->assertSame(['model:prune', '--model=App\Models\User'], CronRunner::split('model:prune --model="App\\Models\\User"'));
        $this->assertSame(['say', "it's", 'a "b" c', '--n=5'], CronRunner::split("say 'it'\\''s' \"a \\\"b\\\" c\" --n=5"));
    }

    public function test_the_page_shows_how_to_set_up_cron_and_every_task(): void
    {
        $this->schedule->call(fn () => null)->name('Tidy up')->everyMinute();
        $this->schedule->call(fn () => null)->name('Only on demo')->everyMinute()->when(fn (): bool => false);
        $this->schedule->command('inspire')->dailyAt('03:00')->timezone('Europe/Warsaw');

        $this->actingAs($this->admin)
            ->get(Cron::getUrl(panel: 'administration'))
            ->assertOk()
            ->assertSee('Cron has never run')
            ->assertSee(base_path('cron.php'))
            ->assertSee(CronToken::url(), escape: false)
            ->assertSee('Tidy up')
            ->assertDontSee('Only on demo')
            ->assertSee('Every day at 03:00 Europe/Warsaw');

        $this->assertSame('!', Cron::getNavigationBadge());

        // One call could be anyone's: not yet a sign of cron.
        $this->cronAt('10:07:00');
        $this->travel(1)->minutes();
        $this->get(Cron::getUrl(panel: 'administration'))
            ->assertSee("Cron isn't running on a schedule")
            ->assertSee('The tasks last ran 1 minute ago, by cron.php.')
            ->assertSee('Calls of cron in the last hour: 1, not yet regular.');
        $this->assertSame('!', Cron::getNavigationBadge());

        // Every minute for ten minutes is.
        $this->cronAt('10:08:00', '10:17:00');
        $this->travel(1)->minutes();
        $this->get(Cron::getUrl(panel: 'administration'))
            ->assertSee('Cron is running')
            ->assertSee('Runs in the last hour: 3.')
            ->assertSee('1 minute ago · by cron');
        $this->assertNull(Cron::getNavigationBadge());

        // Late: nothing for longer than config('cron.late_after') minutes.
        $this->travelTo(Date::parse('2026-09-30 11:17:00'));
        $this->get(Cron::getUrl(panel: 'administration'))->assertSee('Cron has stopped: it last ran 1 hour ago');
        $this->assertSame('!', Cron::getNavigationBadge());
    }

    public function test_cron_counts_as_running_once_it_is_called_regularly(): void
    {
        // A few runs close together, as someone trying it out would.
        $this->cronAt('10:07:00', '10:07:30', '10:08:00', '10:09:00');
        $this->assertFalse(Cron::isRunning());

        // Ten minutes after the first, in three different minutes: a schedule.
        $this->cronAt('10:17:00');
        $this->assertTrue(Cron::isRunning());

        // A host that allows cron only every 15 minutes gets there too.
        DB::table('cron_calls')->delete();
        $this->cronAt('11:03:00', '11:18:00');
        $this->assertFalse(Cron::isRunning());
        $this->cronAt('11:33:00');
        $this->assertTrue(Cron::isRunning());
    }

    public function test_runs_by_hand_are_told_apart_from_cron(): void
    {
        $tidy = $this->schedule->call(fn () => null)->name('Tidy up')->everyMinute();

        Filament::setCurrentPanel('administration');
        $this->actingAs($this->admin);

        // "Run cron jobs manually" on the page.
        Livewire::test(Cron::class)->callAction('runDue');

        $this->assertSame('browser', CronLog::task($tidy)->last_via);
        $this->travel(1)->minutes();
        $this->get(Cron::getUrl(panel: 'administration'))
            ->assertSee("Cron isn't running on a schedule")
            ->assertSee('The tasks last ran 1 minute ago, manually, from this page.')
            ->assertSee('Nothing calls cron on a schedule')
            ->assertSee('1 minute ago · manually');
        $this->assertSame('!', Cron::getNavigationBadge());

        // cron.php typed in a terminal.
        CronLog::$terminal = true;
        $this->cronAt('10:09:00');

        $this->assertSame('terminal', CronLog::lastCall()->via);
        $this->assertSame('terminal', CronLog::task($tidy)->last_via);
        $this->travel(1)->minutes();
        $this->get(Cron::getUrl(panel: 'administration'))
            ->assertSee('The tasks last ran 1 minute ago, from a terminal.')
            ->assertSee('1 minute ago · from a terminal');

        // The cron URL opened twice in a browser: not a schedule either.
        $this->travelTo(Date::parse('2026-09-30 10:11:00'));
        $this->get(CronToken::url())->assertOk();
        $this->travelTo(Date::parse('2026-09-30 10:12:00'));
        $this->get(CronToken::url())->assertOk();

        $this->assertFalse(Cron::isRunning());
        $this->assertSame('url', CronLog::task($tidy)->last_via);
        $this->travel(1)->minutes();
        $this->get(Cron::getUrl(panel: 'administration'))
            ->assertSee('The tasks last ran 1 minute ago, through the cron URL.')
            ->assertSee('Calls of cron in the last hour: 3, not yet regular.');

        // An hour on, it never ran on a schedule, so it hasn't "stopped".
        $this->travelTo(Date::parse('2026-09-30 11:30:00'));
        $this->get(Cron::getUrl(panel: 'administration'))
            ->assertSee("Cron isn't running on a schedule")
            ->assertSee('The tasks last ran 1 hour ago, through the cron URL. Nothing calls cron on a schedule')
            ->assertDontSee('Cron has stopped');
    }

    public function test_schedule_run_typed_in_a_terminal_is_a_run_by_hand(): void
    {
        $this->schedule->call(fn () => null)->name('Tidy up')->everyMinute();
        $kernel = $this->app->make(ConsoleKernel::class);
        $kernel->rerouteSymfonyCommandEvents();
        $kernel->setArtisan(null);
        CronLog::$terminal = true;

        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertSame('terminal', CronLog::lastCall()->via);
    }

    public function test_tasks_are_run_from_the_page(): void
    {
        $ran = 0;
        $tidy = $this->schedule->call(function () use (&$ran) {
            $ran++;
        })->name('Tidy up')->everyMinute();

        Filament::setCurrentPanel('administration');
        $this->actingAs($this->admin);

        Livewire::test(Cron::class)
            ->callAction('runDue')
            ->assertNotified('Done');
        $this->assertSame(1, $ran);

        // Not a sign that cron is set up.
        $this->assertNull(CronLog::lastCall());

        // One task, due or not: a run by hand too.
        Livewire::test(Cron::class)
            ->callAction(TestAction::make('run')->table(CronLog::key($tidy)))
            ->assertNotified('Done');
        $this->assertSame(2, $ran);
        $this->assertSame('browser', CronLog::task($tidy)->last_via);
        $this->assertSame(2, DB::table('cron_calls')->where('via', 'browser')->count());
        $this->assertNull(CronLog::lastCall());
    }

    public function test_the_status_follows_a_run_from_the_page_without_a_reload(): void
    {
        $tidy = $this->schedule->call(fn () => null)->name('Tidy up')->everyMinute();

        Filament::setCurrentPanel('administration');
        $this->actingAs($this->admin);

        // The status is redrawn by the same request that runs the tasks.
        Livewire::test(Cron::class)
            ->assertSee('Cron has never run')
            ->callAction('runDue')
            ->assertDontSee('Cron has never run')
            ->assertSee("Cron isn't running on a schedule")
            ->assertSee('The tasks last ran 0 seconds ago, manually, from this page.');

        // And after running one task, once the page has aged.
        $this->travel(10)->minutes();

        Livewire::test(Cron::class)
            ->assertSee('The tasks last ran 10 minutes ago, manually, from this page.')
            ->callAction(TestAction::make('run')->table(CronLog::key($tidy)))
            ->assertDontSee('10 minutes ago, manually, from this page.')
            ->assertSee('The tasks last ran 0 seconds ago, manually, from this page.');
    }

    public function test_the_php_path_warning_is_hidden_when_it_can_be_confirmed(): void
    {
        // php_binary() only falls back to a guess when it can't find a real
        // command-line PHP, which never happens in a test process (it runs
        // under the CLI SAPI throughout) — this only checks the warning
        // stays out of the way when the path is known, not that it shows
        // when it isn't; that only happens from a real web request.
        $this->actingAs($this->admin)
            ->get(Cron::getUrl(panel: 'administration'))
            ->assertDontSee('The "php" above is a guess');
    }

    public function test_the_cron_url_is_replaced_from_the_page(): void
    {
        $old = CronToken::current();

        Filament::setCurrentPanel('administration');
        $this->actingAs($this->admin);

        Livewire::test(Cron::class)
            ->callAction(TestAction::make('newToken')->schemaComponent(schema: 'content'))
            ->assertNotified('The cron URL was replaced');

        $this->assertNotSame($old, CronToken::current());
    }

    public function test_only_administrators_see_the_page(): void
    {
        $this->actingAs($this->userWithRole('manager'))
            ->get(Cron::getUrl(panel: 'administration'))
            ->assertForbidden();
    }
}
