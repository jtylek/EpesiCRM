<?php

namespace App\Services\Cron;

use Carbon\CarbonInterface;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * What Administration → Cron shows: when each scheduled task last ran and
 * how that went (Epesi's `cron` table), and when cron itself was called.
 *
 * It listens to the scheduler's own events, so a run by
 * `php artisan schedule:run` is recorded as well as one by cron.php
 * (CronRunner dispatches the same events). A failure to record never stops
 * a task: before the tables exist, on an update not yet applied, nothing is
 * recorded.
 *
 * Each call, and each task's last run, keeps how it was started ("via"):
 * cli (cron.php), schedule:run, url (the cron URL), terminal (cron.php or
 * schedule:run typed in a terminal) or browser (the Cron page's buttons).
 */
class CronLog
{
    /** How the calls are kept: long enough to show how often cron runs. */
    protected const KEEP_CALLS_HOURS = 48;

    /** The end of a task's output that is kept. */
    protected const OUTPUT_LIMIT = 10000;

    /** The call under way in this process, how it was started, and what it ran so far. */
    protected static ?int $call = null;

    protected static ?string $via = null;

    protected static int $ran = 0;

    /** Whether this process was started in a terminal; null: find out. Tests set it. */
    public static ?bool $terminal = null;

    /** @var array<string, true> by key: a failure is reported by Finished, Failed, or both */
    protected static array $failed = [];

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            ScheduledTaskStarting::class => 'taskStarting',
            ScheduledTaskFinished::class => 'taskFinished',
            ScheduledBackgroundTaskFinished::class => 'backgroundTaskFinished',
            ScheduledTaskFailed::class => 'taskFailed',
            CommandStarting::class => 'commandStarting',
            CommandFinished::class => 'commandFinished',
        ];
    }

    public function taskStarting(ScheduledTaskStarting $event): void
    {
        static::$ran++;

        static::write($event->task, [
            'last_started_at' => Date::now(),
            'last_finished_at' => null,
            'last_duration' => null,
            'last_status' => 'running',
            'last_output' => null,
        ]);

        // On its own: until the update that adds the column is applied, it
        // would fail the write above, and with it catching up (CronRunner::isDue()).
        rescue(fn () => DB::table('cron_tasks')->where('key', static::key($event->task))->update(['last_via' => static::$via]), report: false);
    }

    public function taskFinished(ScheduledTaskFinished $event): void
    {
        // No exit code yet: started in the background, or still running from
        // before (withoutOverlapping). It stays "running".
        if ($event->task->exitCode === null) {
            return;
        }

        static::finished($event->task, $event->runtime);
    }

    /** A background task of `schedule:run` reports back through schedule:finish. */
    public function backgroundTaskFinished(ScheduledBackgroundTaskFinished $event): void
    {
        $started = static::task($event->task)?->last_started_at;

        static::finished($event->task, $started ? round(Date::parse($started)->diffInSeconds(Date::now(), true), 2) : null);
    }

    public function taskFailed(ScheduledTaskFailed $event): void
    {
        static::$failed[static::key($event->task)] = true;

        static::write($event->task, [
            'last_finished_at' => Date::now(),
            'last_status' => 'failed',
            'last_output' => static::trim($event->exception->getMessage()),
        ]);
    }

    /** `php artisan schedule:run` is being called: cron is alive, unless someone typed it. */
    public function commandStarting(CommandStarting $event): void
    {
        if ($event->command === 'schedule:run') {
            static::startCall(static::fromTerminal() ? 'terminal' : 'schedule:run');
        }
    }

    public function commandFinished(CommandFinished $event): void
    {
        if ($event->command === 'schedule:run') {
            static::finishCall();
        }
    }

    /** What the task wrote, kept to be read on the Cron page. */
    public static function output(Event $task, string $output): void
    {
        static::write($task, ['last_output' => static::trim($output)]);
    }

    public static function startCall(string $via): void
    {
        static::$via = $via;
        static::$ran = 0;
        static::$failed = [];
        static::$call = rescue(function () use ($via): int {
            DB::table('cron_calls')->where('started_at', '<', Date::now()->subHours(self::KEEP_CALLS_HOURS))->delete();

            return DB::table('cron_calls')->insertGetId(['via' => $via, 'started_at' => Date::now()]);
        }, null, report: false);
    }

    public static function finishCall(): void
    {
        static::$via = null;

        if (static::$call === null) {
            return;
        }

        $call = static::$call;
        static::$call = null;

        rescue(fn () => DB::table('cron_calls')->where('id', $call)->update([
            'finished_at' => Date::now(),
            'tasks' => static::$ran,
            'failed' => count(static::$failed),
        ]), report: false);
    }

    /**
     * Rows for tasks cron hasn't seen before. Their creation time is where a
     * task's catching up starts from (CronRunner::isDue()).
     *
     * @param  list<Event>  $tasks
     */
    public static function see(array $tasks): void
    {
        $now = Date::now();

        rescue(fn () => DB::table('cron_tasks')->insertOrIgnore(array_map(
            fn (Event $task): array => ['key' => static::key($task), 'created_at' => $now, 'updated_at' => $now],
            $tasks,
        )), report: false);
    }

    public static function task(Event $task): ?object
    {
        return rescue(fn () => DB::table('cron_tasks')->where('key', static::key($task))->first(), report: false);
    }

    /**
     * @return Collection<string, object> by key
     */
    public static function tasks(): Collection
    {
        return rescue(fn () => DB::table('cron_tasks')->get()->keyBy('key'), collect(), report: false);
    }

    /**
     * The last call of cron itself, from the server's cron or through the
     * cron URL. The Cron page's buttons don't count unless $page: they say
     * nothing about whether cron is set up.
     */
    public static function lastCall(bool $page = false): ?object
    {
        return rescue(fn () => DB::table('cron_calls')
            ->unless($page, fn ($query) => $query->where('via', '!=', 'browser'))
            ->latest('started_at')
            ->latest('id')
            ->first(), report: false);
    }

    public static function callsSince(CarbonInterface $since): int
    {
        return (int) rescue(fn () => DB::table('cron_calls')->where('via', '!=', 'browser')->where('started_at', '>=', $since)->count(), 0, report: false);
    }

    /**
     * Whether cron is called on a schedule: lately (config('cron.late_after')),
     * and regularly in the last hour (see regular()). One call, or a few by
     * hand, look alike whichever way they came, so they don't make it. The
     * Cron page's buttons never count.
     */
    public static function runsOnSchedule(): bool
    {
        $minutes = static::callMinutes(Date::now()->subHour());

        return $minutes->isNotEmpty()
            && ($minutes->last() + 1) * 60 > Date::now()->subMinutes((int) config('cron.late_after'))->getTimestamp()
            && static::regular($minutes);
    }

    /**
     * Whether cron ran on a schedule at some point in the calls kept: what
     * tells "cron has stopped" from "cron was never set up".
     */
    public static function ranOnSchedule(): bool
    {
        return static::regular(static::callMinutes(Date::now()->subHours(self::KEEP_CALLS_HOURS)));
    }

    /**
     * The minutes (Unix time / 60) in which cron was called since $since,
     * each once, in order; the Cron page's own runs left out.
     *
     * @return Collection<int, int>
     */
    protected static function callMinutes(CarbonInterface $since): Collection
    {
        return collect(rescue(fn () => DB::table('cron_calls')
            ->where('via', '!=', 'browser')
            ->where('started_at', '>=', $since)
            ->pluck('started_at')
            ->all(), [], report: false))
            ->filter()
            ->map(fn (string $at): int => intdiv(Date::parse($at)->getTimestamp(), 60))
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * Calls in config('cron.regular_calls') different minutes of one hour at
     * least, the first and the last config('cron.regular_minutes') apart.
     *
     * @param  Collection<int, int>  $minutes  in order, each once
     */
    protected static function regular(Collection $minutes): bool
    {
        $calls = (int) config('cron.regular_calls');
        $span = (int) config('cron.regular_minutes');

        for ($first = 0, $last = 0; $first < $minutes->count(); $first++) {
            $last = max($last, $first);

            while ($last + 1 < $minutes->count() && $minutes[$last + 1] - $minutes[$first] <= 60) {
                $last++;
            }

            if ($last - $first + 1 >= $calls && $span <= $minutes[$last] - $minutes[$first]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Started by hand in a terminal: cron and Task Scheduler (as SYSTEM) give
     * a task none. A web request has no STDIN.
     */
    public static function fromTerminal(): bool
    {
        return static::$terminal ?? (defined('STDIN') && @stream_isatty(STDIN));
    }

    /** A task's row: its schedule and what it runs, which is what its mutex is named after. */
    public static function key(Event $task): string
    {
        return sha1($task->mutexName());
    }

    protected static function finished(Event $task, ?float $duration): void
    {
        if ($task->exitCode !== 0) {
            static::$failed[static::key($task)] = true;
        }

        static::write($task, [
            'last_finished_at' => Date::now(),
            'last_duration' => $duration,
            'last_status' => $task->exitCode === 0 ? 'ok' : 'failed',
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected static function write(Event $task, array $values): void
    {
        $now = Date::now();

        rescue(fn () => DB::table('cron_tasks')->upsert(
            [['key' => static::key($task), ...$values, 'created_at' => $now, 'updated_at' => $now]],
            ['key'],
            [...array_keys($values), 'updated_at'],
        ), report: false);
    }

    protected static function trim(string $output): ?string
    {
        $output = trim($output);

        return $output === '' ? null : mb_substr($output, -self::OUTPUT_LIMIT);
    }
}
