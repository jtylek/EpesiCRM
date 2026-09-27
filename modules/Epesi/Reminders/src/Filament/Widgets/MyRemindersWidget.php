<?php

namespace Epesi\Modules\Reminders\Filament\Widgets;

use App\Filament\Dashboard\Applet;
use App\Filament\Dashboard\AppletTooltip;
use App\Filament\Dashboard\IsApplet;
use Epesi\Modules\Reminders\Models\Reminder;
use Epesi\Modules\Reminders\Reminders;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * "My reminders" — the Messenger applet ("Messenger alarms"): the signed-in
 * user's reminders that are still on, due ones first (as a checkbox to turn
 * off, Epesi's turn_off()), then upcoming ones. Records the user can no
 * longer see drop out, through each record type's own ownership scope.
 */
class MyRemindersWidget extends TableWidget implements Applet
{
    use IsApplet;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

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
            ->heading(__('My reminders'))
            ->query(fn (): Builder => static::query())
            ->defaultSort('remind_at')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->poll('60s')
            ->headerActions([$this->configureAppletAction()])
            ->columns([
                TextColumn::make('remind_at')
                    ->label('When')
                    ->dateTime('Y-m-d H:i')
                    ->description(fn (Reminder $record): string => $record->remind_at->diffForHumans())
                    ->color(fn (Reminder $record): ?string => $record->remind_at->isPast() ? 'danger' : null)
                    ->icon(fn (Reminder $record): ?Heroicon => $record->remind_at->isPast() ? Heroicon::OutlinedBellAlert : null)
                    ->tooltip(fn (Reminder $record): ?HtmlString => $this->details($record)),
                TextColumn::make('record')
                    ->label('Reminder')
                    ->state(fn (Reminder $record): string => $record->remindable ? Reminders::label($record->remindable) : '-')
                    ->description(fn (Reminder $record): ?string => $record->message)
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

    /** The description of the record it is about, on hover, as on the other applets. */
    private function details(Reminder $record): ?HtmlString
    {
        return AppletTooltip::text($record->remindable?->getAttribute('description'));
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
            ->with('remindable');
    }

    /** Utils_MessengerCommon::turn_off(): for this user only. */
    public static function dismiss(Reminder $reminder): void
    {
        $reminder->recipientRows()
            ->where('user_id', Auth::id())
            ->update(['dismissed_at' => now()]);
    }
}
