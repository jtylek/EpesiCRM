<?php

namespace Epesi\Modules\Watchdog;

use App\Models\User;
use Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord;
use Epesi\Modules\RecordBrowser\Filament\RelationManagers\HistoryRelationManager;
use Epesi\Modules\Watchdog\Models\CategorySubscription;
use Epesi\Modules\Watchdog\Models\Subscription;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Activitylog\ActivitylogServiceProvider;

/**
 * The port of Utils_WatchdogCommon. Events are `activity_log` rows, so there
 * is nothing to log here — only who watches what, and what they have seen.
 *
 *     Watchdog::subscribe($user, $task);
 *     Watchdog::unseenCount($user, $task);   // changes by others since last look
 *     Watchdog::markSeen($user, $task);
 */
class Watchdog
{
    /**
     * Record types that can be watched (morph aliases). Another module makes
     * its own watchable with Watchdog::enableFor() — the port of
     * Utils_RecordBrowserCommon::enable_watchdog().
     *
     * @var array<int, string>
     */
    public static array $recordTypes = ['company', 'contact', 'task', 'meeting', 'phone_call'];

    protected static bool $notificationsMuted = false;

    public static function enableFor(string $morphAlias): void
    {
        if (! in_array($morphAlias, static::$recordTypes, true)) {
            static::$recordTypes[] = $morphAlias;
        }
    }

    public static function watches(Model $record): bool
    {
        return in_array($record->getMorphClass(), static::$recordTypes, true);
    }

    /**
     * Start watching $record with everything so far seen — or, given
     * $firstUnseen (an activity id), with that change and any after it
     * still to see. A no-op for a record the user already watches.
     */
    public static function subscribe(User $user, Model $record, ?int $firstUnseen = null): Subscription
    {
        return Subscription::query()->firstOrCreate(
            static::key($user, $record),
            ['last_seen_activity_id' => static::latestActivityId($record, before: $firstUnseen)],
        );
    }

    public static function unsubscribe(User $user, Model $record): void
    {
        Subscription::query()->where(static::key($user, $record))->delete();
    }

    public static function isSubscribed(User $user, Model $record): bool
    {
        return Subscription::query()->where(static::key($user, $record))->exists();
    }

    public static function toggle(User $user, Model $record): bool
    {
        if (static::isSubscribed($user, $record)) {
            static::unsubscribe($user, $record);

            return false;
        }

        static::subscribe($user, $record);

        return true;
    }

    public static function subscribeToCategory(User $user, string $category): void
    {
        CategorySubscription::query()->firstOrCreate(['user_id' => $user->id, 'category' => $category]);
    }

    /**
     * @param  array<int, string>  $categories
     */
    public static function syncCategories(User $user, array $categories): void
    {
        CategorySubscription::query()
            ->where('user_id', $user->id)
            ->whereNotIn('category', $categories)
            ->delete();

        foreach ($categories as $category) {
            static::subscribeToCategory($user, $category);
        }
    }

    /**
     * @return array<int, string>
     */
    public static function categoriesOf(User $user): array
    {
        return CategorySubscription::query()->where('user_id', $user->id)->pluck('category')->all();
    }

    /**
     * Mark everything that has happened to $record so far as seen by $user —
     * what opening the record does, as Utils_WatchdogCommon::notified() did —
     * and the bell's notifications of it as read. The subscription is left
     * alone for a record the user does not watch.
     */
    public static function markSeen(User $user, Model $record): void
    {
        static::see($user, $record->getMorphClass(), $record->getKey(), static::latestActivityId($record));
    }

    /**
     * Bell notifications the user read or dismissed: the changes they told of
     * count as seen, so the Watched page agrees with the bell. A record is
     * seen up to its newest change among them, not every change since.
     *
     * @param  iterable<int, DatabaseNotification>  $notifications
     */
    public static function markNotifiedSeen(User $user, iterable $notifications): void
    {
        collect($notifications)
            ->map(fn (DatabaseNotification $notification): ?array => $notification->data['watchdog'] ?? null)
            ->filter()
            ->groupBy(fn (array $change): string => $change['subject_type'].':'.$change['subject_id'])
            ->each(fn ($changes) => static::see(
                $user,
                $changes->first()['subject_type'],
                $changes->first()['subject_id'],
                (int) $changes->max('activity_id'),
            ));
    }

    /**
     * What NotifySubscribers stores with each bell notification, for
     * markNotifiedSeen() to read back.
     *
     * @return array<string, mixed>
     */
    public static function notificationData(Model $record, Model $activity): array
    {
        return [
            'subject_type' => $record->getMorphClass(),
            'subject_id' => $record->getKey(),
            'activity_id' => $activity->getKey(),
        ];
    }

    /**
     * $user has seen the record's changes up to and including $upTo: never
     * moved back past a later change already seen.
     */
    protected static function see(User $user, string $type, int|string $id, ?int $upTo): void
    {
        if ($upTo === null) {
            return;
        }

        Subscription::query()
            ->where(['user_id' => $user->id, 'subscribable_type' => $type, 'subscribable_id' => $id])
            ->where(fn (Builder $q): Builder => $q
                ->whereNull('last_seen_activity_id')
                ->orWhere('last_seen_activity_id', '<', $upTo))
            ->update(['last_seen_activity_id' => $upTo]);

        $user->unreadNotifications()
            ->where('data->watchdog->subject_type', $type)
            ->where('data->watchdog->subject_id', $id)
            ->where('data->watchdog->activity_id', '<=', $upTo)
            ->update(['read_at' => now()]);
    }

    public static function unseenCount(User $user, Model $record): int
    {
        $subscription = Subscription::query()->where(static::key($user, $record))->first();

        return $subscription ? static::unseenActivities($subscription)->count() : 0;
    }

    /**
     * Changes to the subscription's record made by someone other than its
     * user since they last looked. Subscription::withUnseenChanges() is the
     * same rule for a whole list; keep the two in step.
     */
    public static function unseenActivities(Subscription $subscription): Builder
    {
        $activity = ActivitylogServiceProvider::determineActivityModel();

        return $activity::query()
            ->where('subject_type', $subscription->subscribable_type)
            ->where('subject_id', $subscription->subscribable_id)
            ->when($subscription->last_seen_activity_id, fn (Builder $q, int $id): Builder => $q->where('id', '>', $id))
            ->where(fn (Builder $q): Builder => $q
                ->whereNull('causer_id')
                ->orWhere('causer_type', '!=', (new User)->getMorphClass())
                ->orWhere('causer_id', '!=', $subscription->user_id));
    }

    /**
     * Everyone who should hear about a change to $record: its subscribers and
     * whoever watches its whole type.
     *
     * @return Collection<int, User>
     */
    public static function subscribersOf(Model $record): Collection
    {
        $direct = Subscription::query()
            ->where('subscribable_type', $record->getMorphClass())
            ->where('subscribable_id', $record->getKey())
            ->pluck('user_id');

        $category = CategorySubscription::query()
            ->where('category', $record->getMorphClass())
            ->pluck('user_id');

        return User::query()->whereKey($direct->merge($category)->unique()->all())->get();
    }

    /**
     * Whether $user may see $record — asked before telling them about it, so
     * watching a whole record type never leaks someone's private record. It
     * runs the model's own query as that user, which is the only way to put
     * its visibility scope (HasOwnershipVisibility reads the logged-in user)
     * to the question, then asks the policy.
     */
    public static function canSee(User $user, Model $record): bool
    {
        $guard = Auth::guard();
        $previous = $guard->user();
        $guard->setUser($user);

        try {
            $query = $record::query()->whereKey($record->getKey());

            if (in_array(SoftDeletes::class, class_uses_recursive($record), true)) {
                $query->withTrashed();
            }

            return $query->exists() && Gate::forUser($user)->allows('view', $record);
        } finally {
            if ($previous) {
                $guard->setUser($previous);
            } elseif (method_exists($guard, 'forgetUser')) {
                $guard->forgetUser();
            }
        }
    }

    /**
     * Run $callback without sending change notifications — for bulk work
     * such as a legacy import. Subscriptions still record what was missed.
     */
    public static function withoutNotifications(callable $callback): mixed
    {
        $previous = static::$notificationsMuted;
        static::$notificationsMuted = true;

        try {
            return $callback();
        } finally {
            static::$notificationsMuted = $previous;
        }
    }

    public static function notificationsMuted(): bool
    {
        return static::$notificationsMuted;
    }

    /**
     * "Task: Call the bank" — what a record is called in a notification or
     * the Watched list, via its resource's record title where it has one.
     */
    public static function label(Model $record): string
    {
        $resource = static::resourceFor($record);
        $type = $resource ? Str::ucfirst($resource::getModelLabel()) : Str::headline($record->getMorphClass());

        return $type.': '.static::title($record);
    }

    /**
     * "Call the bank" — label() without the record type, for where the type
     * is shown already.
     */
    public static function title(Model $record): string
    {
        $resource = static::resourceFor($record);
        $title = $resource ? $resource::getRecordTitle($record) : null;

        return filled($title) ? strip_tags((string) $title) : '#'.$record->getKey();
    }

    /**
     * The record's View page with its History addon open: everywhere this
     * links from (the bell, the Watched list) says the record changed, and
     * History is where the changes are.
     */
    public static function url(Model $record): ?string
    {
        $resource = static::resourceFor($record);

        if (! $resource || ! $resource::hasPage('view')) {
            return null;
        }

        return $resource::getUrl('view', [
            'record' => $record,
            'tab' => ViewRecord::addonTab(HistoryRelationManager::class),
        ], panel: 'main');
    }

    /**
     * A changed column as people know it: the recordset field's own label
     * where the record has one, else the column name made readable.
     */
    public static function fieldLabel(Model $record, string $column): string
    {
        $resource = static::resourceFor($record);
        $field = $resource && method_exists($resource, 'fields')
            ? collect($resource::fields())->first(fn ($field): bool => ($field->name ?? null) === $column)
            : null;

        return __($field?->getLabel() ?? Str::headline($column));
    }

    /**
     * @return class-string<\Filament\Resources\Resource>|null
     */
    protected static function resourceFor(Model $record): ?string
    {
        $panel = Filament::getPanel('main', isStrict: false);

        return $panel?->getModelResource($record::class);
    }

    public static function modelFor(string $category): ?string
    {
        return Relation::getMorphedModel($category);
    }

    protected static function latestActivityId(Model $record, ?int $before = null): ?int
    {
        $activity = ActivitylogServiceProvider::determineActivityModel();

        $id = $activity::query()
            ->where('subject_type', $record->getMorphClass())
            ->where('subject_id', $record->getKey())
            ->when($before, fn (Builder $q, int $id): Builder => $q->where('id', '<', $id))
            ->max('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function key(User $user, Model $record): array
    {
        return [
            'user_id' => $user->id,
            'subscribable_type' => $record->getMorphClass(),
            'subscribable_id' => $record->getKey(),
        ];
    }
}
