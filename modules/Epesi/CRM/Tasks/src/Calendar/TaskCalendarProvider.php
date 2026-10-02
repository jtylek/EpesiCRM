<?php

namespace Epesi\Modules\CRM\Tasks\Calendar;

use App\Enums\RecordStatus;
use App\Models\User;
use App\Support\Calendar\CalendarColor;
use App\Support\Calendar\CalendarEvent;
use App\Support\Calendar\CalendarEventProvider;
use Carbon\Carbon;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\TaskResource;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TaskCalendarProvider implements CalendarEventProvider
{
    public static function calendarKey(): string
    {
        return 'task';
    }

    public static function calendarEvents(Carbon $start, Carbon $end, User $user, bool $mine = false): Collection
    {
        return Task::query()
            ->whereNotNull('deadline')
            ->whereBetween('deadline', [$start, $end])
            ->with(['customers', 'customerCompanies'])
            ->when($mine, fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                ->whereHas('employees', fn (Builder $contacts): Builder => $contacts->where('user_id', $user->id))
                ->orWhereHas('customers', fn (Builder $contacts): Builder => $contacts->where('user_id', $user->id))))
            ->get()
            ->map(fn (Task $task): CalendarEvent => new CalendarEvent(
                id: self::calendarKey().'-'.$task->id,
                title: $task->title,
                start: $task->deadline,
                end: null,
                allDay: $task->timeless,
                url: TaskResource::getUrl('view', ['record' => $task]),
                color: CalendarColor::css($task->status->getColor()),
                durationEditable: false,
                finished: in_array($task->status, RecordStatus::finished(), true),
                description: $task->description,
                customers: $task->customers->pluck('full_name')
                    ->merge($task->customerCompanies->pluck('company_name'))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
                deadline: true,
            ))
            ->values();
    }

    public static function calendarLabel(): string
    {
        return 'Task';
    }

    public static function calendarCreateUrl(Carbon $date, bool $allDay): string
    {
        return TaskResource::getUrl('create', [
            // A timed deadline is an instant: the clicked wall-clock slot, as UTC.
            'deadline' => ($allDay ? $date : RegionalSetting::fromUser($date))->toIso8601String(),
            'timeless' => $allDay ? 1 : 0,
        ]);
    }

    public static function calendarReschedule(string $recordId, Carbon $start, ?Carbon $end, bool $allDay, User $user): bool
    {
        $task = Task::query()->find($recordId);

        if (! $task || ! $user->can('update', $task)) {
            return false;
        }

        $task->deadline = $start;
        $task->timeless = $allDay;
        $task->save();

        return true;
    }
}
