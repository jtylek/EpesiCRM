<?php

namespace Tests\Feature;

use App\Filament\Administration\Pages\BackgroundWorker;
use App\Models\User;
use App\Support\Background\BackgroundWorkerHealth;
use Epesi\Modules\Mail\Jobs\UnreadMailCheck;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Services\UnreadCounter;
use Epesi\Modules\Mail\Services\UnreadMailCheckDispatcher;
use Filament\Facades\Filament;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use RuntimeException;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

class BackgroundWorkerTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_mail_check_queues_one_job_per_account_when_enabled(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::create([
            'user_id' => $user->id,
            'name' => 'Work',
            'email' => 'me@example.test',
            'imap_host' => 'imap.example.test',
        ]);
        config(['epesi-background.enabled' => true]);
        config(['queue.connections.epesi-background.after_commit' => false]);
        Bus::fake();

        $this->assertNull(app(UnreadMailCheckDispatcher::class)->dispatch($account));

        Bus::assertDispatched(UnreadMailCheck::class, function (UnreadMailCheck $job): bool {
            return $job->connection === 'epesi-background' && $job->queue === 'epesi-background';
        });
    }

    public function test_health_reports_pending_reserved_oldest_and_failed_jobs(): void
    {
        config(['epesi-background.enabled' => true]);
        $now = now()->timestamp;

        DB::table('jobs')->insert([
            'queue' => 'epesi-background',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $now,
            'created_at' => $now - 40,
        ]);
        DB::table('jobs')->insert([
            'queue' => 'epesi-background',
            'payload' => '{}',
            'attempts' => 1,
            'reserved_at' => $now - 5,
            'available_at' => $now,
            'created_at' => $now - 10,
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => 'worker-test',
            'connection' => 'epesi-background',
            'queue' => 'epesi-background',
            'payload' => '{}',
            'exception' => 'test',
            'failed_at' => now(),
        ]);

        $status = app(BackgroundWorkerHealth::class)->status();

        $this->assertTrue($status['enabled']);
        $this->assertTrue($status['available']);
        $this->assertSame(1, $status['pending']);
        $this->assertSame(1, $status['reserved']);
        $this->assertGreaterThanOrEqual(40, $status['oldest_age']);
        $this->assertSame(1, $status['failed']);
    }

    public function test_the_mail_job_is_unique_per_account(): void
    {
        $job = new UnreadMailCheck(7);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('7', $job->uniqueId());
        $this->assertSame('8', (new UnreadMailCheck(8))->uniqueId());
        $this->assertGreaterThan($job->tries * $job->timeout + array_sum($job->backoff), $job->uniqueFor);
    }

    public function test_enabled_without_a_recent_successful_drain_is_overdue(): void
    {
        $health = app(BackgroundWorkerHealth::class);

        config(['epesi-background.enabled' => false]);
        $this->assertFalse($health->status()['overdue'], 'a disabled worker is not overdue');

        config(['epesi-background.enabled' => true]);
        $this->assertTrue($health->status()['overdue'], 'enabled and never drained');

        $run = $health->beginRun();
        $health->finishRun($run, true);
        $this->assertFalse($health->status()['overdue'], 'just drained');

        DB::table('epesi_background_worker_runs')->update(['finished_at' => now()->subSeconds(BackgroundWorkerHealth::OVERDUE_AFTER + 60)]);
        $this->assertTrue($health->status()['overdue'], 'last drain too long ago');
    }

    public function test_old_runs_are_pruned_but_the_last_successful_one_stays(): void
    {
        $health = app(BackgroundWorkerHealth::class);
        $old = now()->subDays(60);

        foreach ([true, false, false] as $successful) {
            DB::table('epesi_background_worker_runs')->insert(['started_at' => $old, 'finished_at' => $old, 'successful' => $successful, 'created_at' => $old, 'updated_at' => $old]);
        }
        $recent = $health->beginRun();
        $health->finishRun($recent, false, 'recent failure');

        $this->assertSame(2, $health->pruneRuns(30));
        $this->assertDatabaseCount('epesi_background_worker_runs', 2);
        $this->assertTrue(DB::table('epesi_background_worker_runs')->where('successful', true)->exists(), 'the last successful run stays');
        $this->assertTrue(DB::table('epesi_background_worker_runs')->where('id', $recent)->exists(), 'recent runs stay');
    }

    public function test_a_queue_that_cannot_be_reached_falls_back_to_the_synchronous_check(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::create(['user_id' => $user->id, 'name' => 'Work', 'email' => 'me@example.test', 'imap_host' => 'imap.example.test']);
        config(['epesi-background.enabled' => true]);
        $this->mock(Dispatcher::class)->shouldReceive('dispatch')->andThrow(new RuntimeException('queue down'));

        // Queuing throws, so the dispatcher runs the check itself and returns its result.
        $counter = $this->mock(UnreadCounter::class);
        $counter->shouldReceive('check')->once()->with(\Mockery::on(fn (MailAccount $given): bool => $given->is($account)))->andReturn(['count' => 3, 'error' => null]);

        $this->assertSame(['count' => 3, 'error' => null], app(UnreadMailCheckDispatcher::class)->dispatch($account));
    }

    public function test_the_health_page_warns_when_enabled_but_not_draining(): void
    {
        config(['epesi-background.enabled' => true]);
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::test(BackgroundWorker::class)
            ->assertSuccessful()
            ->assertSee('nothing has been drained')
            ->assertSee('epesi:background:drain');
    }

    public function test_disabled_drain_is_a_safe_noop(): void
    {
        config(['epesi-background.enabled' => false]);

        $this->artisan('epesi:background:drain')
            ->expectsOutput('The Epesi background worker is disabled.')
            ->assertSuccessful();

        $this->assertDatabaseCount('epesi_background_worker_runs', 0);
    }
}
