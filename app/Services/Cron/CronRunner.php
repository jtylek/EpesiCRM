<?php

namespace App\Services\Cron;

use Carbon\CarbonInterface;
use Cron\CronExpression;
use DateTimeZone;
use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Date;
use RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

/**
 * Epesi's cron.php: runs the scheduled tasks that are due, in this PHP
 * process. cron.php on the command line, the cron URL and "Run now" under
 * Administration → Cron all come here.
 *
 * `php artisan schedule:run` runs the same tasks, but starts each as a
 * process of its own. That needs proc_open(), which shared hosts often
 * disable, and a command-line PHP to start, which a web request doesn't
 * know. Here an Artisan command runs through the console kernel instead;
 * only a task that is a shell command (Schedule::exec()) still needs a
 * process.
 *
 * It also catches up, as Epesi's cron did. The scheduler runs a task only in
 * the minute its schedule names, so on a host whose cron runs every 15
 * minutes at 7, 22, 37 and 52 past, a task due at midnight never runs. Here
 * a task also runs when its last due time has passed since it last ran.
 * Either way a task runs at most once a minute, however often cron is
 * called, and its when()/skip() conditions still apply.
 */
class CronRunner
{
    public function __construct(
        protected Application $app,
        protected Dispatcher $events,
    ) {}

    /**
     * Every task that is due.
     *
     * @return list<array{task: string, status: string, duration: float, output: string}>
     */
    public function runDue(string $via): array
    {
        $tasks = $this->tasks();
        CronLog::see($tasks);
        CronLog::startCall($via);

        $results = [];

        try {
            foreach ($tasks as $task) {
                if ($this->isDue($task) && ($result = $this->run($task)) !== null) {
                    $results[] = $result;
                }
            }
        } finally {
            CronLog::finishCall();
        }

        return $results;
    }

    /**
     * One task, whatever its schedule: "Run now" on one row of the Cron page,
     * recorded as a run by hand from there. Null when it doesn't apply now
     * (its when()/skip() conditions).
     *
     * @return array{task: string, status: string, duration: float, output: string}|null
     */
    public function runNow(Event $task): ?array
    {
        CronLog::startCall('browser');

        try {
            return $this->run($task);
        } finally {
            CronLog::finishCall();
        }
    }

    /**
     * Every scheduled task, as `schedule:list` shows them.
     *
     * @return list<Event>
     */
    public function tasks(): array
    {
        // Loads routes/console.php's tasks, which a web request hasn't seen.
        $this->app->make(ConsoleKernel::class)->bootstrap();

        return $this->app->make(Schedule::class)->events();
    }

    public function find(string $key): ?Event
    {
        foreach ($this->tasks() as $task) {
            if (CronLog::key($task) === $key) {
                return $task;
            }
        }

        return null;
    }

    public function isDue(Event $task): bool
    {
        $row = CronLog::task($task);
        $lastStarted = $row?->last_started_at ? Date::parse($row->last_started_at) : null;

        if ($lastStarted?->greaterThanOrEqualTo(Date::now()->startOfMinute())) {
            return false;
        }

        if ($task->isDue($this->app)) {
            return true;
        }

        // Missed: due since it last ran, or since cron first saw it.
        $since = $lastStarted ?? ($row?->created_at ? Date::parse($row->created_at) : null);

        return $since !== null
            && ($task->runsInMaintenanceMode() || ! $this->app->isDownForMaintenance())
            && $task->runsInEnvironment($this->app->environment())
            && static::lastDue($task)->greaterThan($since);
    }

    /** The last time the task's schedule named, this minute included. */
    public static function lastDue(Event $task): CarbonInterface
    {
        $timezone = $task->timezone instanceof DateTimeZone ? $task->timezone->getName() : $task->timezone;

        return Date::instance((new CronExpression($task->expression))->getPreviousRunDate(Date::now(), 0, true, $timezone));
    }

    /**
     * What the task runs: an Artisan command line ("model:prune --model=…"),
     * or the name given to a closure.
     */
    public static function name(Event $task): string
    {
        $arguments = static::artisanArguments($task);

        if ($arguments !== null) {
            return implode(' ', $arguments);
        }

        return $task->description ?? $task->getSummaryForDisplay();
    }

    /** What it is for: the description the task was given, or its command's own. */
    public static function description(Event $task): ?string
    {
        if ($task instanceof CallbackEvent) {
            return null;
        }

        if (filled($task->description)) {
            return $task->description;
        }

        $command = static::artisanArguments($task)[0] ?? null;

        return $command !== null ? (app(ConsoleKernel::class)->all()[$command] ?? null)?->getDescription() : null;
    }

    /**
     * The Artisan command line of a task scheduled with Schedule::command(),
     * split into its words: its command string is "<php> artisan <command>",
     * with each argument quoted by ProcessUtils::escapeArgument(). Null for a
     * closure or a shell command.
     *
     * @return list<string>|null
     */
    public static function artisanArguments(Event $task): ?array
    {
        $prefix = Artisan::formatCommandString('');

        if ($task instanceof CallbackEvent || ! is_string($task->command) || ! str_starts_with($task->command, $prefix)) {
            return null;
        }

        return static::split(substr($task->command, strlen($prefix)));
    }

    /**
     * Words of a command line, quoted as ProcessUtils::escapeArgument() does:
     * 'single' ('\'' for a quote in it) on Linux and macOS, "double" (\" for a
     * quote in it) on Windows.
     *
     * @return list<string>
     */
    public static function split(string $line): array
    {
        preg_match_all('/(?:\'[^\']*\'|"(?:\\\\.|[^"\\\\])*"|\\\\.|[^\s\'"\\\\])+/s', $line, $words);

        return array_map(fn (string $word): string => preg_replace_callback(
            '/\'([^\']*)\'|"((?:\\\\.|[^"\\\\])*)"|\\\\(.)/s',
            fn (array $match): string => $match[3] ?? (isset($match[2]) ? str_replace('\\"', '"', $match[2]) : $match[1]),
            $word,
        ), $words[0]);
    }

    /**
     * Runs the task as `schedule:run` would, with the same events, so CronLog
     * records it either way.
     *
     * @return array{task: string, status: string, duration: float, output: string}|null
     */
    protected function run(Event $task): ?array
    {
        if (! $task->filtersPass($this->app)) {
            $this->events->dispatch(new ScheduledTaskSkipped($task));

            return null;
        }

        $task->exitCode = null;
        $arguments = static::artisanArguments($task);
        $output = '';
        $error = null;
        $start = microtime(true);

        $this->events->dispatch(new ScheduledTaskStarting($task));

        try {
            $arguments === null ? $this->runItself($task, $output) : $this->runHere($task, $arguments, $output);

            $this->events->dispatch(new ScheduledTaskFinished($task, round(microtime(true) - $start, 2)));

            if ($task->exitCode !== null && $task->exitCode !== 0) {
                throw new RuntimeException("Scheduled command [{$task->command}] failed with exit code [{$task->exitCode}].");
            }
        } catch (Throwable $error) {
            $this->events->dispatch(new ScheduledTaskFailed($task, $error));
            report($error);
            $output = trim($output."\n\n".$error->getMessage());
        }

        // No exit code: still running from an earlier call (withoutOverlapping),
        // or started in the background.
        if ($error === null && $task->exitCode === null) {
            return static::result($task, 'running', 0.0, '');
        }

        CronLog::output($task, $output);

        return static::result($task, $error === null ? 'ok' : 'failed', round(microtime(true) - $start, 2), $output);
    }

    /**
     * An Artisan command, through the console kernel in this process. Its
     * before/after callbacks and withoutOverlapping() work as they do under
     * `schedule:run`; runInBackground() doesn't: here it runs now.
     *
     * @param  list<string>  $arguments
     */
    protected function runHere(Event $task, array $arguments, string &$output): void
    {
        $kernel = $this->app->make(ConsoleKernel::class);

        // A module that registers its commands only when runningInConsole()
        // leaves them out of a web request: the cron URL, "Run now".
        if (! array_key_exists($arguments[0], $kernel->all())) {
            throw new RuntimeException("The command \"{$arguments[0]}\" isn't registered here. A module has to register a scheduled command outside its runningInConsole() check, or it can't run from the cron URL or the Cron page.");
        }

        if ($task->shouldSkipDueToOverlapping()) {
            return;
        }

        $buffer = new BufferedOutput;
        $exitCode = 1;

        try {
            $task->callBeforeCallbacks($this->app);
            $exitCode = $kernel->handle(new ArgvInput(['artisan', ...$arguments, '--no-interaction']), $buffer);
        } finally {
            $output = $buffer->fetch();
            $task->finish($this->app, $exitCode);
        }
    }

    /** A closure runs in this process anyway; a shell command needs proc_open(). */
    protected function runItself(Event $task, string &$output): void
    {
        ob_start();

        try {
            $task->run($this->app);
        } finally {
            $output = (string) ob_get_clean();
        }
    }

    /**
     * @return array{task: string, status: string, duration: float, output: string}
     */
    protected static function result(Event $task, string $status, float $duration, string $output): array
    {
        return ['task' => static::name($task), 'status' => $status, 'duration' => $duration, 'output' => $output];
    }

    /**
     * What cron.php and the cron URL print.
     *
     * @param  list<array{task: string, status: string, duration: float, output: string}>  $results
     */
    public static function report(array $results): string
    {
        if ($results === []) {
            return 'No tasks were due.';
        }

        return implode("\n", array_map(fn (array $result): string => sprintf(
            '%s: %s%s%s',
            $result['task'],
            match ($result['status']) {
                'ok' => 'done',
                'running' => 'still running from before',
                default => 'FAILED',
            },
            $result['status'] === 'running' ? '' : sprintf(' (%.2f s)', $result['duration']),
            $result['status'] === 'failed' && $result['output'] !== '' ? "\n    ".str_replace("\n", "\n    ", $result['output']) : '',
        ), $results));
    }
}
