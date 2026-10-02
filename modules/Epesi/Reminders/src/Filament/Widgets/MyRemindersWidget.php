<?php

namespace Epesi\Modules\Reminders\Filament\Widgets;

use App\Filament\Dashboard\AppletTooltip;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\RecordBrowser\Filament\Widgets\RecordsetApplet;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Epesi\Modules\Reminders\Models\Reminder;
use Epesi\Modules\Reminders\Reminders;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * "My reminders" — the Messenger applet ("Messenger alarms"): the signed-in
 * user's reminders that are still on, due ones first (as a checkbox to turn
 * off, Epesi's turn_off()), then upcoming ones. Records the user can no
 * longer see drop out, through each record type's own ownership scope.
 */
class MyRemindersWidget extends RecordsetApplet
{
    protected static string|\BackedEnum|null $appletIcon = Heroicon::OutlinedBell;

    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole(Reminders::ROLES) ?? false;
    }

    public static function getAppletCaption(): string
    {
        return __('My reminders');
    }

    public static function getAppletDescription(): ?string
    {
        return __('Your reminders that are due or coming up');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => static::query())
            ->defaultSort('remind_at')
            ->defaultPaginationPageOption(5)
            ->poll('60s')
            ->columns([
                TextColumn::make('remind_at')
                    ->label('When')
                    ->dateTime()
                    ->description(fn (Reminder $record): string => $record->remind_at->diffForHumans())
                    ->color(fn (Reminder $record): ?string => $record->remind_at->isPast() ? 'danger' : null)
                    ->icon(fn (Reminder $record): ?Heroicon => $record->remind_at->isPast() ? Heroicon::OutlinedBellAlert : null)
                    ->tooltip(fn (Reminder $record): ?HtmlString => $this->details($record)),
                TextColumn::make('record')
                    ->label('Reminder')
                    ->state(fn (Reminder $record): string => $record->remindable ? Reminders::label($record->remindable) : '-')
                    ->icon(fn (Reminder $record) => $record->remindable ? Reminders::typeIcon($record->remindable) : null)
                    ->url(fn (Reminder $record): ?string => $record->remindable ? Reminders::url($record->remindable) : null)
                    ->tooltip(fn (Reminder $record): ?HtmlString => $this->details($record))
                    ->wrap(),
            ])
            ->recordActions([
                Action::make('dismiss')
                    ->label('Turn off')
                    ->icon(Heroicon::OutlinedCheck)
                    ->iconButton()
                    ->tooltip(__('Turn off'))
                    ->color('gray')
                    ->action(fn (Reminder $record) => static::dismiss($record)),
            ])
            ->emptyStateHeading(__('No reminders'))
            ->emptyStateDescription(__('Set one from the Reminders tab of a task, meeting or phone call.'))
            ->emptyStateIcon(Heroicon::OutlinedBellSlash);
    }

    /** The full details of the record the reminder is about. */
    private function details(Reminder $record): HtmlString
    {
        $remindable = $record->remindable;

        if (! $remindable) {
            return AppletTooltip::details(
                type: 'Reminder',
                icon: Heroicon::OutlinedBell,
                title: '-',
                description: null,
                dateLabel: 'Date and Time',
                date: RegionalSetting::display($record->remind_at),
            );
        }

        $label = Reminders::label($remindable);
        $date = Reminders::startOf($remindable);

        if ($remindable instanceof Task && $remindable->timeless && $date) {
            $date = $date->copy()->startOfDay();
        }

        return AppletTooltip::details(
            type: Str::before($label, ': '),
            icon: Reminders::typeIcon($remindable),
            title: Str::after($label, ': '),
            description: $remindable->getAttribute('description'),
            dateLabel: $remindable instanceof Task ? 'Deadline' : 'Date and Time',
            date: RegionalSetting::display($date, $remindable instanceof Task && $remindable->timeless),
            customers: AppletTooltip::customers($remindable),
        );
    }

    /**
     * @return Builder<Reminder>
     */
    public static function query(): Builder
    {
        $types = array_filter(array_map(
            fn (string $alias): ?string => Relation::getMorphedModel($alias),
            Reminders::recordTypes(),
        ));

        return Reminder::query()
            ->activeFor(Auth::user())
            // whereHasMorph() runs each type's global scopes as the signed-in
            // user: someone else's private record, or a trashed one, is out.
            ->whereHasMorph('remindable', $types)
            ->with(['remindable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                Task::class => ['customers', 'customerCompanies'],
                Meeting::class => ['customers', 'customerCompanies'],
                PhoneCall::class => ['customer'],
            ])]);
    }

    /** Utils_MessengerCommon::turn_off(): for this user only. */
    public static function dismiss(Reminder $reminder): void
    {
        $reminder->recipientRows()
            ->where('user_id', Auth::id())
            ->update(['dismissed_at' => now()]);
    }
}
