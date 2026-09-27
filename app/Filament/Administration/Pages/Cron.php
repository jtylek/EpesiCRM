<?php

namespace App\Filament\Administration\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Services\Cron\CronLog;
use App\Services\Cron\CronRunner;
use App\Services\Cron\CronToken;
use BackedEnum;
use DateTimeZone;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\HtmlString;

use function Illuminate\Support\php_binary;

/**
 * Epesi's Administration → Cron (Base/Cron): whether cron runs, how to set it
 * up (cron.php from the server's cron, or the cron URL for a host that can
 * only call an address), and every scheduled task with its last run. A task,
 * or everything that is due, can be run from here too.
 */
class Cron extends Page implements HasTable
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use InteractsWithTable;
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Cron';

    protected static ?string $title = 'Cron';

    protected static ?string $slug = 'cron';

    protected static ?int $navigationSort = 85;

    /** In the menu, a red mark while cron isn't running. */
    public static function getNavigationBadge(): ?string
    {
        return static::isRunning() ? null : '!';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Cron is not running');
    }

    /** Called on a schedule lately: see CronLog::runsOnSchedule(). */
    public static function isRunning(): bool
    {
        return CronLog::runsOnSchedule();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->status(),
            EmbeddedTable::make(),
            Section::make(__('Setting up cron'))
                ->description(__('Cron has to run every minute. Set it up one of these two ways.'))
                ->collapsible()
                ->collapsed(static::isRunning())
                ->schema([
                    TextEntry::make('cronLine')
                        ->label('Recommended: the server runs cron.php')
                        ->state($this->cronLine())
                        ->fontFamily(FontFamily::Mono)
                        ->copyable()
                        ->helperText(__('A line for the crontab, or the command for the Cron Jobs page of the host\'s control panel, every minute. The path to PHP differs between hosts; the control panel or the host\'s help names it.')),
                    Callout::make(__('The "php" above is a guess'))
                        ->description(__('This page can only run from a web request, which can\'t see which command-line PHP the server has. If the host\'s own Cron Jobs page names a PHP version or path, use that instead of copying "php" as it stands.'))
                        ->warning()
                        ->visible(fn (): bool => $this->phpBinaryIsGuessed()),
                    TextEntry::make('cronUrl')
                        ->label('If the host can only call a web address: the cron URL')
                        ->state(CronToken::url())
                        ->fontFamily(FontFamily::Mono)
                        ->copyable()
                        ->helperText(__('Give it to the host\'s cron, or to an outside service that calls an address on a schedule, every minute, or as often as allowed.')),
                    Callout::make(__('The cron URL contains a secret'))
                        ->description(__('Anyone who has it can start cron. Keep it private, and replace it if it may have leaked.'))
                        ->warning()
                        ->actions([$this->newTokenAction()]),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->tasks())
            ->columns([
                TextColumn::make('name')
                    ->label('Task')
                    ->description(fn (array $record): ?string => $record['description'])
                    ->wrap(),
                TextColumn::make('schedule')
                    ->label('Schedule')
                    ->description(fn (array $record): string => __('Next: :time', ['time' => $record['next']->diffForHumans()])),
                TextColumn::make('last_started_at')
                    ->label('Last run')
                    ->dateTime()
                    ->description(fn (array $record): ?string => static::ranWhenAndHow($record))
                    ->placeholder(__('Never')),
                TextColumn::make('last_duration')
                    ->label('Duration')
                    ->formatStateUsing(fn (float|int|string $state): string => sprintf('%.2f s', $state))
                    ->placeholder('-'),
                TextColumn::make('last_status')
                    ->label('Result')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::statuses()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'ok' => 'success',
                        'failed' => 'danger',
                        default => 'warning',
                    })
                    ->placeholder('-'),
            ])
            ->recordAction('output')
            ->recordActions([
                $this->outputAction(),
                $this->runTaskAction(),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('Nothing is scheduled'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runDue')
                ->label('Run cron jobs manually')
                ->icon(Heroicon::OutlinedPlay)
                ->action(function (CronRunner $runner): void {
                    @set_time_limit(0);
                    $this->notifyResults($runner->runDue('browser'));
                    $this->afterRun();
                }),
        ];
    }

    protected function outputAction(): Action
    {
        return Action::make('output')
            ->label('Output')
            ->icon(Heroicon::OutlinedDocumentText)
            ->iconButton()
            ->tooltip(__('Output of the last run'))
            ->visible(fn (array $record): bool => filled($record['last_output']))
            ->modalHeading(fn (array $record): string => $record['name'])
            ->modalDescription(fn (array $record): ?string => static::ranWhenAndHow($record))
            ->modalContent(fn (array $record): HtmlString => new HtmlString(
                '<pre style="white-space:pre-wrap;word-break:break-word;font-size:.8125rem;max-height:60vh;overflow:auto">'.e($record['last_output']).'</pre>'
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'));
    }

    protected function runTaskAction(): Action
    {
        return Action::make('run')
            ->label('Run now')
            ->icon(Heroicon::OutlinedPlay)
            ->iconButton()
            ->tooltip(__('Run now'))
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedPlay)
            ->modalHeading(fn (array $record): string => __('Run :task now?', ['task' => $record['name']]))
            ->modalDescription(__('It runs now, whatever its schedule, and this window waits until it has finished.'))
            ->modalSubmitActionLabel(__('Run now'))
            ->action(function (array $record, CronRunner $runner): void {
                $task = $runner->find($record['__key']);
                @set_time_limit(0);
                $result = $task ? $runner->runNow($task) : null;

                if ($result === null) {
                    Notification::make()->title(__('The task doesn\'t apply now'))->warning()->send();
                } else {
                    $this->notifyResults([$result]);
                }

                $this->afterRun();
            });
    }

    /** Replacing the cron URL, as Epesi's "New Token". */
    protected function newTokenAction(): Action
    {
        return Action::make('newToken')
            ->label('New cron URL')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('Replace the cron URL?'))
            ->modalDescription(__('The current cron URL stops working at once. If the host\'s cron or an outside service calls it, give it the new one.'))
            ->modalSubmitActionLabel(__('Replace'))
            ->action(function (): void {
                CronToken::regenerate();
                Notification::make()->title(__('The cron URL was replaced'))->success()->send();
            });
    }

    /**
     * Green only while cron is called on a schedule (isRunning()), and
     * "Cron has stopped" only when it was. Tasks run some other way — from
     * this page, from a terminal, or a few calls of the cron URL — say how
     * they last ran, so a run by hand isn't taken for cron: yellow for
     * config('cron.late_after') minutes, red after.
     *
     * Worked out when the callout is drawn, not when the page's schema is
     * built: Filament builds that once per request, before an action such as
     * "Run cron jobs manually" has run, so text worked out then was drawn
     * again unchanged after the tasks ran.
     */
    protected function status(): Callout
    {
        return Callout::make(fn (): string => $this->statusState()['heading'])
            ->description(fn (): string => $this->statusState()['description'])
            ->status(fn (): string => $this->statusState()['status']);
    }

    /** @var array{heading: string, description: string, status: string}|null */
    protected ?array $statusState = null;

    /**
     * @return array{heading: string, description: string, status: string}
     */
    protected function statusState(): array
    {
        return $this->statusState ??= $this->workOutStatus();
    }

    /** Forgets the status, so it is worked out again from what the tasks have just logged. */
    protected function afterRun(): void
    {
        $this->statusState = null;
        $this->flushCachedTableRecords();
    }

    /**
     * @return array{heading: string, description: string, status: string}
     */
    protected function workOutStatus(): array
    {
        $last = CronLog::lastCall();
        $latest = CronLog::lastCall(page: true);
        $lately = Date::now()->subMinutes((int) config('cron.late_after'));
        $cronCalledLately = $last !== null && Date::parse($last->started_at)->greaterThan($lately);
        $callsThisHour = CronLog::callsSince(Date::now()->subHour());

        if (static::isRunning()) {
            return [
                'heading' => __('Cron is running'),
                'description' => __('Last run :time, :via. Runs in the last hour: :count.', [
                    'time' => Date::parse($last->started_at)->diffForHumans(),
                    'via' => static::via($last->via),
                    'count' => $callsThisHour,
                ]),
                'status' => 'success',
            ];
        }

        if (! $cronCalledLately && CronLog::ranOnSchedule()) {
            return [
                'heading' => __('Cron has stopped: it last ran :time', ['time' => Date::parse($last->started_at)->diffForHumans()]),
                'description' => __('It last ran :via. Until it runs again, reminders aren\'t sent and mail isn\'t fetched. Check the cron job on the server, or the service that calls the cron URL.', ['via' => static::via($last->via)]),
                'status' => 'danger',
            ];
        }

        if ($latest === null) {
            return [
                'heading' => __('Cron has never run'),
                'description' => __('Until it does, reminders aren\'t sent, mail isn\'t fetched and old records aren\'t cleaned up. Set it up as described below.'),
                'status' => 'danger',
            ];
        }

        return [
            'heading' => __('Cron isn\'t running on a schedule'),
            'description' => __('The tasks last ran :time, :via.', [
                'time' => Date::parse($latest->started_at)->diffForHumans(),
                'via' => static::via($latest->via),
            ]).' '.($cronCalledLately
                ? __('Calls of cron in the last hour: :count, not yet regular. If it has just been set up, this turns green once it has run for :minutes minutes.', [
                    'count' => $callsThisHour,
                    'minutes' => (int) config('cron.regular_minutes'),
                ])
                : __('Nothing calls cron on a schedule, so reminders aren\'t sent and mail isn\'t fetched unless the tasks are run by hand. Set it up as described below.')),
            'status' => Date::parse($latest->started_at)->greaterThan($lately) ? 'warning' : 'danger',
        ];
    }

    /** How a call was started, for the status above: "by cron.php", "manually, from this page". */
    protected static function via(string $via): string
    {
        return match ($via) {
            'cli' => __('by cron.php'),
            'url' => __('through the cron URL'),
            'terminal' => __('from a terminal'),
            'browser' => __('manually, from this page'),
            default => __('by :command', ['command' => $via]),
        };
    }

    /**
     * "9 seconds ago · by cron", "2 minutes ago · manually": when a task last
     * ran and how, under its date.
     *
     * @param  array<string, mixed>  $record
     */
    protected static function ranWhenAndHow(array $record): ?string
    {
        if ($record['last_started_at'] === null) {
            return null;
        }

        $how = match ($record['last_via']) {
            'cli', 'schedule:run' => __('by cron'),
            'url' => __('through the cron URL'),
            'terminal' => __('from a terminal'),
            'browser' => __('manually'),
            default => null,
        };

        return $record['last_started_at']->diffForHumans().($how === null ? '' : ' · '.$how);
    }

    /**
     * Every scheduled task with its last run. One that doesn't apply here,
     * such as the demo reset outside demo mode, isn't listed.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function tasks(): array
    {
        $rows = CronLog::tasks();

        return collect(app(CronRunner::class)->tasks())
            ->filter(fn (Event $task): bool => $task->filtersPass(app()))
            ->mapWithKeys(function (Event $task) use ($rows): array {
                $key = CronLog::key($task);
                $row = $rows->get($key);

                return [$key => [
                    '__key' => $key,
                    'name' => static::translated(CronRunner::name($task)),
                    'description' => static::translated(CronRunner::description($task)),
                    'schedule' => static::schedule($task),
                    'next' => $task->nextRunDate(),
                    'last_started_at' => $row?->last_started_at ? Date::parse($row->last_started_at) : null,
                    'last_duration' => $row?->last_duration,
                    'last_status' => $row?->last_status,
                    'last_via' => $row?->last_via ?? null,
                    'last_output' => $row?->last_output,
                ]];
            })
            ->all();
    }

    /**
     * @param  list<array{task: string, status: string, duration: float, output: string}>  $results
     */
    protected function notifyResults(array $results): void
    {
        if ($results === []) {
            Notification::make()->title(__('No tasks were due'))->info()->send();

            return;
        }

        $failed = collect($results)->where('status', 'failed')->isNotEmpty();

        Notification::make()
            ->title($failed ? __('A task failed') : __('Done'))
            ->body(collect($results)
                ->map(fn (array $result): string => e(static::translated($result['task'])).': '.e(static::statuses()[$result['status']] ?? $result['status']))
                ->implode('<br>'))
            ->status($failed ? 'danger' : 'success')
            ->send();
    }

    /** "Every 5 minutes", "Every day at 03:00 Europe/Warsaw", or the cron expression itself. */
    protected static function schedule(Event $task): string
    {
        if ($task->expression === '* * * * *') {
            return __('Every minute');
        }

        if (preg_match('#^\*/(\d+) \* \* \* \*$#', $task->expression, $match)) {
            return __('Every :count minutes', ['count' => $match[1]]);
        }

        if (preg_match('#^(\d+) (\d+) \* \* \*$#', $task->expression, $match)) {
            $timezone = $task->timezone instanceof DateTimeZone ? $task->timezone->getName() : ($task->timezone ?? config('app.timezone'));

            return __('Every day at :time', ['time' => sprintf('%02d:%02d', $match[2], $match[1]).' '.$timezone]);
        }

        return $task->expression;
    }

    /**
     * @return array<string, string>
     */
    protected static function statuses(): array
    {
        return [
            'ok' => __('Done'),
            'failed' => __('Failed'),
            'running' => __('Running'),
        ];
    }

    /**
     * A task's description in the user's language where it has one. Modules
     * add their own; an untranslated one shows as it is, not as a gap.
     */
    protected static function translated(?string $text): ?string
    {
        return $text !== null && Lang::has($text) ? __($text) : $text;
    }

    /** The crontab line for this installation's paths. */
    protected function cronLine(): string
    {
        $script = base_path('cron.php');
        $quote = fn (string $path): string => str_contains($path, ' ') ? '"'.$path.'"' : $path;

        return '* * * * * '.$quote(php_binary()).' '.$quote($script).' > /dev/null 2>&1';
    }

    /**
     * php_binary() asks Symfony's PhpExecutableFinder for the running
     * process's own executable, which only means something from the command
     * line. This page only ever renders from a web request (it's a Filament
     * panel page), so the finder can never offer a real command-line PHP —
     * on a web SAPI, PHP_BINARY is that SAPI's own binary (LiteSpeed's
     * lsphp, PHP-FPM's own process, …), not one the finder will return — and
     * php_binary() falls back to the literal string "php". That may or may
     * not be the right PHP on this host.
     */
    protected function phpBinaryIsGuessed(): bool
    {
        return php_binary() === 'php';
    }
}
