<?php

namespace Epesi\Modules\Reminders;

use Carbon\CarbonInterface;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\RecordBrowser\Extensions\RecordExtensions;
use Epesi\Modules\Reminders\Console\SendRemindersCommand;
use Epesi\Modules\Reminders\Filament\RelationManagers\RemindersRelationManager;
use Epesi\Modules\Reminders\Models\Reminder;
use Epesi\Modules\Reminders\Models\ReminderRecipient;
use Epesi\Modules\Reminders\Policies\ReminderPolicy;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Puts a "Reminders" tab on tasks, meetings and phone calls — the three
 * modules that opened Utils/Messenger as an addon (messanger_addon()) — and
 * delivers due reminders from the scheduler, as Messenger's cron2() did.
 */
class RemindersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Relation::morphMap([
            'reminder' => Reminder::class,
            'reminder_recipient' => ReminderRecipient::class,
        ]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Gate::policy(Reminder::class, ReminderPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SendRemindersCommand::class]);
        }

        // Messenger's cron() asked for cron2() every minute; here that is
        // Laravel's scheduler, which needs `schedule:run` in cron.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('reminders:send')
                ->everyMinute()
                ->withoutOverlapping();
        });

        // What each type's reminders are "before": the times those modules
        // passed to Utils/Messenger as its default date.
        Reminders::startTimeFor('task', ['deadline', 'timeless'], fn (Task $task): ?CarbonInterface => $task->deadline
            // A timeless task is due on its day, as strtotime() read Epesi's
            // date-only deadline: midnight.
            ? ($task->timeless ? $task->deadline->copy()->startOfDay() : $task->deadline->copy())
            : null);
        Reminders::startTimeFor('meeting', ['date', 'time'], fn (Meeting $meeting): ?CarbonInterface => $meeting->starts_at);
        Reminders::startTimeFor('phone_call', ['called_at'], fn (PhoneCall $call): ?CarbonInterface => $call->called_at?->copy());

        // After every provider has booted, so a module registering its own
        // record type from its boot() is wired up regardless of order.
        $this->app->booted(function (): void {
            foreach (Reminders::recordTypes() as $alias) {
                Reminders::wire($alias);
            }

            RecordExtensions::addon(RemindersRelationManager::class, Reminders::recordTypes());
        });
    }
}
