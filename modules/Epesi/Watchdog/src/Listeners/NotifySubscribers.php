<?php

namespace Epesi\Modules\Watchdog\Listeners;

use App\Models\User;
use App\Support\Locale\Locales;
use Epesi\Modules\Watchdog\Watchdog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Spatie\Activitylog\Contracts\Activity;

/**
 * Runs for every new `activity_log` row — the port of
 * Utils_WatchdogCommon::new_event(). The author of a new record starts
 * watching it (as RecordBrowser's add_record() subscribed the creator), and
 * everyone else watching the record or its type gets a notification in the
 * panel's bell, Epesi's tray notification.
 */
class NotifySubscribers
{
    public function __invoke(Activity&Model $activity): void
    {
        $record = $this->subjectOf($activity);

        if ($record === null || ! Watchdog::watches($record)) {
            return;
        }

        $causer = $activity->causer_type === (new User)->getMorphClass()
            ? User::query()->find($activity->causer_id)
            : null;

        if ($activity->event === 'created' && $causer) {
            Watchdog::subscribe($causer, $record);
        }

        if (Watchdog::notificationsMuted()) {
            return;
        }

        $recipients = Watchdog::subscribersOf($record)
            ->reject(fn (User $user): bool => $causer !== null && $user->is($causer))
            ->filter(fn (User $user): bool => Watchdog::canSee($user, $record));

        if ($recipients->isEmpty()) {
            return;
        }

        // Someone told about the record through watching its type starts
        // watching the record itself, this change unseen — as new_event()
        // subscribed type watchers to each new record. The Watched page lists
        // subscriptions: without one the change would reach the bell and
        // nowhere else.
        foreach ($recipients as $user) {
            Watchdog::subscribe($user, $record, firstUnseen: $activity->getKey());
        }

        $url = Watchdog::url($record);

        // One notification per language: it is stored as rendered text, so
        // each recipient gets it in their own, not the causer's.
        foreach ($recipients->groupBy(fn (User $user): string => $user->preferredLocale()) as $locale => $group) {
            $notification = Locales::using($locale, fn () => Notification::make()
                ->title(Watchdog::label($record))
                ->body($this->describe($activity, $record, $causer))
                ->icon('heroicon-o-eye')
                ->actions($url ? [Action::make('open')->label(__('Open'))->url($url)->markAsRead()] : [])
                ->toDatabase());

            // Which change this is: reading it in the bell marks it seen.
            $notification->data['watchdog'] = Watchdog::notificationData($record, $activity);

            // sendNow(), not Filament's sendToDatabase(): Filament's database
            // notification is ShouldQueue, and with the default database queue
            // it would wait in `jobs` for a worker most installs don't run.
            // Epesi wrote its watchdog events in the same request too.
            NotificationFacade::sendNow($group, $notification);
        }
    }

    protected function subjectOf(Model $activity): ?Model
    {
        $class = Relation::getMorphedModel((string) $activity->subject_type);

        if ($class === null) {
            return null;
        }

        // Without global scopes: the listener has to see every record,
        // including trashed ones and those the ownership scope would hide
        // from whoever happens to be logged in.
        return $class::query()->withoutGlobalScopes()->find($activity->subject_id);
    }

    /** "Ann updated: Title." — the bell notification's body. */
    public function describe(Model $activity, Model $record, ?User $causer): string
    {
        $who = $causer?->displayName() ?? __('System');
        $fields = $this->changedFields($activity, $record);

        return match ($activity->event) {
            'created' => __(':who created.', ['who' => $who]),
            'deleted' => __(':who deleted.', ['who' => $who]),
            'restored' => __(':who restored.', ['who' => $who]),
            default => $fields === []
                ? __(':who updated.', ['who' => $who])
                : __(':who updated: :fields.', ['who' => $who, 'fields' => implode(', ', $fields)]),
        };
    }

    /** "Title, Status" — describe() without who: the Watched page's Changes column. */
    public function summarize(Model $activity, Model $record): string
    {
        $fields = $this->changedFields($activity, $record);

        return match ($activity->event) {
            'created' => __('Created'),
            'deleted' => __('Deleted'),
            'restored' => __('Restored'),
            default => $fields === [] ? __('Updated') : implode(', ', $fields),
        };
    }

    /**
     * @return array<int, string>
     */
    protected function changedFields(Model $activity, Model $record): array
    {
        return collect(array_keys((array) ($activity->properties['attributes'] ?? [])))
            ->map(fn (string $f): string => Watchdog::fieldLabel($record, $f))
            ->all();
    }
}
