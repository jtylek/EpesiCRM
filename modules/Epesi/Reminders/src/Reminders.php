<?php

namespace Epesi\Modules\Reminders;

use App\Models\User;
use App\Support\Locale\Locales;
use Carbon\CarbonInterface;
use Closure;
use Epesi\Modules\Reminders\Models\Reminder;
use Epesi\Modules\Reminders\Models\ReminderRecipient;
use Epesi\Modules\Reminders\Notifications\ReminderMail;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

/**
 * The module's API: which record types take reminders and when each one
 * "starts", and delivery — the port of Utils_MessengerCommon.
 *
 * In Epesi each module handed Messenger its own start time when it opened
 * the addon (CRM_Tasks::messanger_addon() passed the deadline,
 * CRM_Meeting the date plus time) and its own get_alarm() callback for the
 * popup text. Here a module registers its record type once with
 * startTimeFor(); the text is built from the record's Filament resource.
 */
class Reminders
{
    /**
     * Morph alias => the attributes the start time is read from, and how.
     *
     * @var array<string, array{attributes: array<int, string>, resolver: Closure(Model): ?CarbonInterface}>
     */
    protected static array $startTimes = [];

    /** Roles that may be reminded of anything — Epesi's "Messenger Alerts" ACL. */
    public const ROLES = ['super_admin', 'manager', 'employee'];

    /**
     * Let reminders be set on a record type, "N minutes before" the time
     * $resolver returns. Whenever one of $attributes changes, reminders set
     * relative to it move with it (MeetingCommon::submit_meeting()'s 'edit'
     * case, which shifted every alarm by the same difference).
     *
     * @param  array<int, string>  $attributes
     * @param  Closure(Model): ?CarbonInterface  $resolver
     */
    public static function startTimeFor(string $alias, array $attributes, Closure $resolver): void
    {
        static::$startTimes[$alias] = ['attributes' => $attributes, 'resolver' => $resolver];
    }

    /**
     * @return array<int, string>
     */
    public static function recordTypes(): array
    {
        return array_keys(static::$startTimes);
    }

    public static function startOf(Model $record): ?CarbonInterface
    {
        $entry = static::$startTimes[$record->getMorphClass()] ?? null;

        return $entry ? ($entry['resolver'])($record) : null;
    }

    /**
     * Give a record type its `reminders` relation and keep relative
     * reminders on it in step with its start time. Called for every
     * registered type once the application has booted.
     */
    public static function wire(string $alias): void
    {
        $class = Relation::getMorphedModel($alias);

        if ($class === null || ! is_subclass_of($class, Model::class)) {
            return;
        }

        $class::resolveRelationUsing('reminders', fn (Model $record): MorphMany => $record
            ->morphMany(Reminder::class, 'remindable'));

        $class::saved(function (Model $record) use ($alias): void {
            $changed = collect(static::$startTimes[$alias]['attributes'] ?? [])
                ->contains(fn (string $attribute): bool => $record->wasChanged($attribute));

            if ($changed) {
                static::followStartTime($record);
            }
        });

        // Utils_MessengerCommon::delete_by_id() on the record's 'delete'.
        // A soft-deleted record keeps its reminders (they are simply not
        // delivered) so restoring it restores them too.
        $event = in_array(SoftDeletes::class, class_uses_recursive($class), true) ? 'forceDeleted' : 'deleted';
        $class::$event(function (Model $record): void {
            Reminder::query()->whereMorphedTo('remindable', $record)->delete();
        });
    }

    /** Move every "N before" reminder on $record to its current start time. */
    public static function followStartTime(Model $record): void
    {
        $start = static::startOf($record);

        if ($start === null) {
            // No time to be "before" any more (a task's deadline cleared):
            // leave the reminders where they were rather than guess.
            return;
        }

        Reminder::query()
            ->whereMorphedTo('remindable', $record)
            ->whereNotNull('before_minutes')
            ->get()
            ->each(fn (Reminder $reminder) => $reminder->update([
                'remind_at' => $start->copy()->subMinutes($reminder->before_minutes),
            ]));
    }

    /**
     * Everyone a reminder on $record may go to: staff who can open it.
     * Epesi offered the record's own employees and asked each user whether
     * others may set alerts for them ("allow_other"); here any colleague who
     * can see the record can be reminded of it.
     *
     * @return Collection<int, User>
     */
    public static function eligibleRecipients(Model $record): Collection
    {
        return User::query()
            ->role(self::ROLES)
            ->with('contact')
            ->get()
            ->filter(fn (User $user): bool => static::canSee($user, $record))
            ->sortBy(fn (User $user): string => Str::lower($user->displayName()))
            ->values();
    }

    /**
     * Whether $user may open $record — its type's ownership scope (private
     * records) and view policy, evaluated as that user. Trashed records are
     * nobody's to be reminded of.
     */
    public static function canSee(User $user, Model $record): bool
    {
        if (! $user->hasAnyRole(self::ROLES)) {
            return false;
        }

        $guard = Auth::guard();
        $previous = $guard->user();
        $guard->setUser($user);

        try {
            return $record::query()->whereKey($record->getKey())->exists()
                && Gate::forUser($user)->allows('view', $record);
        } finally {
            if ($previous) {
                $guard->setUser($previous);
            } elseif (method_exists($guard, 'forgetUser')) {
                $guard->forgetUser();
            }
        }
    }

    /** "Task: Call the bank" — what get_alarm() printed as the alert's title. */
    public static function label(Model $record): string
    {
        $resource = static::resourceFor($record);
        $type = $resource ? Str::ucfirst($resource::getModelLabel()) : Str::headline($record->getMorphClass());
        $title = $resource ? $resource::getRecordTitle($record) : null;

        return $type.': '.(filled($title) ? strip_tags((string) $title) : '#'.$record->getKey());
    }

    public static function url(Model $record): ?string
    {
        $resource = static::resourceFor($record);

        if (! $resource || ! $resource::hasPage('view')) {
            return null;
        }

        return $resource::getUrl('view', ['record' => $record], panel: 'main');
    }

    /**
     * Deliver every reminder that is due and not yet sent — cron2(), run each
     * minute by `reminders:send`. Returns how many notifications went out.
     */
    public static function deliverDue(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $sent = 0;

        ReminderRecipient::query()
            ->whereNull('sent_at')
            ->whereNull('dismissed_at')
            ->whereHas('reminder', fn ($query) => $query->where('remind_at', '<=', $now))
            ->with(['reminder', 'user.contact'])
            ->chunkById(200, function (EloquentCollection $rows) use ($now, &$sent): void {
                foreach ($rows as $row) {
                    $sent += (int) static::deliver($row, $now);
                }
            });

        return $sent;
    }

    protected static function deliver(ReminderRecipient $row, CarbonInterface $now): bool
    {
        // Claim the row before sending: two overlapping runs (or a slow one
        // and the next minute's) must not both deliver it.
        $claimed = ReminderRecipient::query()
            ->whereKey($row->getKey())
            ->whereNull('sent_at')
            ->update(['sent_at' => $now]);

        $reminder = $row->reminder;
        $user = $row->user;
        $record = $claimed ? static::remindableOf($reminder) : null;

        // A reminder whose record is gone, or which its recipient can no
        // longer see (made private, taken off it), is marked sent and
        // dropped, never shown: get_alarm()'s "Private record" is not
        // something to wake someone up for.
        if ($record === null || $user === null || ! static::canSee($user, $record)) {
            return false;
        }

        // In the recipient's language, not whoever's request (if any) this
        // runs in: the bell notification is stored as rendered text.
        Locales::using($user->preferredLocale(), fn () => static::notify($user, $reminder, $record));

        return true;
    }

    protected static function notify(User $user, Reminder $reminder, Model $record): void
    {
        $url = static::url($record);

        // notifyNow(), not sendToDatabase(): Filament queues its database
        // notifications, and a reminder must not wait for (or depend on) a
        // queue worker — the scheduler run is already off the request.
        $user->notifyNow(Notification::make()
            ->title(__('Reminder: :record', ['record' => static::label($record)]))
            ->body(static::describe($reminder, $record))
            ->icon('heroicon-o-bell-alert')
            ->iconColor('warning')
            ->actions($url ? [Action::make('open')->label(__('Open'))->url($url)->markAsRead()] : [])
            ->toDatabase());

        if ($reminder->send_email && filled($user->email)) {
            try {
                $user->notify(new ReminderMail(static::label($record), static::describe($reminder, $record), $url));
            } catch (Throwable $e) {
                // A mail server being down must not stop everyone else's
                // reminders; the bell notification has already gone out.
                report($e);
            }
        }
    }

    /**
     * The body of the alert: when the record starts, then the reminder's own
     * message — get_alarm()'s "Date: …" line and "Alarm comment: …".
     */
    public static function describe(Reminder $reminder, Model $record): string
    {
        $start = static::startOf($record);

        return collect([
            $start ? __('Starts :date (:relative)', ['date' => $start->format('Y-m-d H:i'), 'relative' => $start->diffForHumans()]) : null,
            filled($reminder->message) ? $reminder->message : null,
        ])->filter()->implode("\n");
    }

    protected static function remindableOf(Reminder $reminder): ?Model
    {
        $class = Relation::getMorphedModel((string) $reminder->remindable_type);

        if ($class === null) {
            return null;
        }

        // Without the ownership scope, which applies to whoever is logged in
        // (nobody, in the scheduler); canSee() checks the recipient instead.
        // SoftDeletes is re-applied so a trashed record is not delivered.
        $query = $class::query()->withoutGlobalScopes();

        if (in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
            $query->whereNull((new $class)->getQualifiedDeletedAtColumn());
        }

        return $query->find($reminder->remindable_id);
    }

    protected static function resourceFor(Model $record): ?string
    {
        $panel = Filament::getPanel('main', isStrict: false);

        return $panel?->getModelResource($record::class);
    }

    /** For tests: forget every registered record type. */
    public static function flush(): void
    {
        static::$startTimes = [];
    }
}
