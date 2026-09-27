<?php

namespace Epesi\Modules\PriorityList;

use Carbon\CarbonInterface;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\PriorityList\Models\Entry;
use Epesi\Modules\RecordBrowser\Extensions\RecordExtensions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class PriorityListServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Relation::morphMap(['priority_list_entry' => Entry::class]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'epesi-priority-list');

        // The activities: the things one actually does next.
        PriorityList::enableFor(
            'task',
            due: fn (Task $task): ?CarbonInterface => $task->deadline,
            allDay: fn (Task $task): bool => $task->timeless,
        );
        PriorityList::enableFor(
            'meeting',
            due: fn (Meeting $meeting): ?CarbonInterface => $meeting->starts_at ?? $meeting->date,
            allDay: fn (Meeting $meeting): bool => $meeting->starts_at === null,
        );
        PriorityList::enableFor(
            'phone_call',
            due: fn (PhoneCall $call): ?CarbonInterface => $call->called_at,
        );

        RecordExtensions::headerActions('priority-list', fn (Model $record): array => static::headerActions($record));

        // After every provider has booted, so a module enabling its own
        // record type from its boot() is covered regardless of order. Every
        // candidate, not just the enabled ones: switching a type on later
        // must never reveal a record that finished while it was off.
        $this->app->booted(function (): void {
            foreach (PriorityList::candidateTypes() as $alias) {
                PriorityList::wire($alias);
            }
        });
    }

    /**
     * The flag on the View page: on the list, or off it. Not a star: that is
     * Favorite, right beside it.
     *
     * @return array<int, Action>
     */
    protected static function headerActions(Model $record): array
    {
        $user = Auth::user();

        if (! $user?->hasAnyRole(PriorityList::ROLES) || ! PriorityList::covers($record)) {
            return [];
        }

        return [
            Action::make('priorityListToggle')
                ->label(fn (): string => PriorityList::has($user, $record) ? __('On priority list') : __('Add to priority list'))
                ->icon(fn (): Heroicon => PriorityList::has($user, $record) ? Heroicon::Flag : Heroicon::OutlinedFlag)
                ->color(fn (): string => PriorityList::has($user, $record) ? 'warning' : 'gray')
                ->tooltip(fn (): string => PriorityList::has($user, $record)
                    ? __('Number :position on your priority list. Click to take it off.', ['position' => PriorityList::positionOf($user, $record)])
                    : __('Put it on your priority list: the :limit things you work on next.', ['limit' => PriorityList::LIMIT]))
                ->action(function () use ($user, $record): void {
                    if (PriorityList::has($user, $record)) {
                        PriorityList::remove($user, $record);
                        Notification::make()->title(__('Taken off your priority list'))->success()->send();
                    } elseif (PriorityList::add($user, $record)) {
                        Notification::make()->title(__('Added to your priority list'))->success()->send();
                    } else {
                        PriorityList::notifyFull();
                    }
                }),
        ];
    }
}
