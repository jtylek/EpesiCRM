<?php

namespace App\Support\Background;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class BackgroundWorkerHealth
{
    /** Seconds without a successful drain after which an enabled worker counts as not running (the cron entry runs every minute). */
    public const OVERDUE_AFTER = 300;

    public function enabled(): bool
    {
        return (bool) config('epesi-background.enabled', false);
    }

    /**
     * @return array{enabled: bool, available: bool, last_successful_drain: Carbon|null, pending: int, reserved: int, oldest_age: int|null, failed: int, error: string|null, overdue: bool}
     */
    public function status(): array
    {
        $status = [
            'enabled' => $this->enabled(),
            'available' => false,
            'last_successful_drain' => null,
            'pending' => 0,
            'reserved' => 0,
            'oldest_age' => null,
            'failed' => 0,
            'error' => null,
            'overdue' => false,
        ];

        try {
            $queue = (string) config('epesi-background.queue', 'epesi-background');
            $now = now()->timestamp;
            $jobs = DB::table('jobs')->where('queue', $queue);

            $status['available'] = true;
            $status['pending'] = (clone $jobs)->whereNull('reserved_at')->count();
            $status['reserved'] = (clone $jobs)->whereNotNull('reserved_at')->count();

            $oldest = (clone $jobs)->min('created_at');
            $status['oldest_age'] = $oldest === null ? null : max(0, $now - (int) $oldest);
            $status['failed'] = DB::table('failed_jobs')->where('queue', $queue)->count();

            $last = DB::table('epesi_background_worker_runs')
                ->where('successful', true)
                ->latest('finished_at')
                ->value('finished_at');
            $status['last_successful_drain'] = $last === null ? null : Carbon::parse($last);
        } catch (Throwable $e) {
            $status['error'] = $e->getMessage();
        }

        // Enabled, but nothing has drained for a few cron intervals (or ever): jobs are being
        // queued that nobody works off, so this must not look healthy.
        $status['overdue'] = $status['enabled']
            && ($status['last_successful_drain'] === null
                || $status['last_successful_drain']->lt(now()->subSeconds(self::OVERDUE_AFTER)));

        return $status;
    }

    /**
     * One run is recorded per drain, normally every minute: keep a month of them, and always the
     * last successful one, which the health page shows.
     */
    public function pruneRuns(int $days = 30): int
    {
        $keep = DB::table('epesi_background_worker_runs')->where('successful', true)->max('id');

        return DB::table('epesi_background_worker_runs')
            ->where('started_at', '<', now()->subDays($days))
            ->when($keep !== null, fn ($query) => $query->where('id', '!=', $keep))
            ->delete();
    }

    public function beginRun(): int
    {
        return (int) DB::table('epesi_background_worker_runs')->insertGetId([
            'started_at' => now(),
            'successful' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function finishRun(int $run, bool $successful, ?string $error = null): void
    {
        // Not whereKey(): that is Eloquent's. On a query builder it becomes a dynamic `where key = ?`
        // that matches nothing on SQLite (a quoted unknown name reads as a string) and is an
        // unknown column on MySQL, so a run would never be marked finished.
        DB::table('epesi_background_worker_runs')->where('id', $run)->update([
            'finished_at' => now(),
            'successful' => $successful,
            'error' => $error,
            'updated_at' => now(),
        ]);
    }
}
