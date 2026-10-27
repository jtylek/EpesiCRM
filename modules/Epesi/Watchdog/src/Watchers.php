<?php

namespace Epesi\Modules\Watchdog;

use App\Models\User;
use App\Support\RequestMemo;
use ArrayObject;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Epesi\Modules\Watchdog\Listeners\NotifySubscribers;
use Epesi\Modules\Watchdog\Models\Subscription;
use Filament\Facades\Filament;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Spatie\Activitylog\ActivitylogServiceProvider;

use function Filament\Support\generate_icon_html;

/**
 * The people a record names in a field marked with watchers — a task's
 * Employees, a ticket's Assigned to — and the eye in front of each of them:
 * the port of CRM_TasksCommon::subscribed_employees() and
 * CRM_ContactsCommon::display_contacts_with_notification().
 *
 * Saving a record subscribes them, as Epesi's `added`/`edited` hooks did.
 * The eye says whether each one has seen the record's latest change by
 * someone else: green, orange, or grey for someone who doesn't watch it (or
 * has no login to watch with).
 */
class Watchers
{
    public const SEEN = 'seen';

    public const PENDING = 'pending';

    /**
     * After a Create or Edit page saved $record and its relationships:
     * everyone in its watcher fields watches it. Whoever saved has seen it;
     * the others have its latest change still to see, as user_subscribe()
     * left the newest event unseen, and someone who wasn't watching yet hears
     * of it in the bell. Like Epesi, every save does this again, so someone
     * who stopped watching starts again when the record is next saved.
     */
    public static function subscribe(Model $record, ?User $actor): void
    {
        if (! Watchdog::watches($record)) {
            return;
        }

        $activity = static::latestActivity($record);
        $added = [];

        foreach (static::fieldsOf($record) as $field) {
            foreach (static::usersIn($record, $field) as $user) {
                if ($actor !== null && $user->is($actor)) {
                    Watchdog::subscribe($user, $record);

                    continue;
                }

                $subscription = Watchdog::subscribe($user, $record, firstUnseen: $activity?->getKey());

                if ($subscription->wasRecentlyCreated && ! isset($added[$user->id])) {
                    $added[$user->id] = [$user, $field];
                }
            }
        }

        if ($added === [] || $activity === null || Watchdog::notificationsMuted()) {
            return;
        }

        $who = $actor?->displayName() ?? __('System');

        collect($added)
            ->filter(fn (array $entry): bool => Watchdog::canSee($entry[0], $record))
            ->groupBy(fn (array $entry): string => $entry[1]->name)
            ->each(fn (Collection $group) => app(NotifySubscribers::class)->send(
                $group->map(fn (array $entry): User => $entry[0]),
                $record,
                $activity,
                fn (): string => __(':who added you to :field.', ['who' => $who, 'field' => __($group->first()[1]->getLabel())]),
            ));
    }

    /**
     * The eye in front of one person's badge.
     */
    public static function badge(Field $field, Model $record, Model $related): ?Htmlable
    {
        if (! Watchdog::watches($record)) {
            return null;
        }

        static::prepare($field, new EloquentCollection([$record]));

        $userId = $related->getAttribute('user_id');
        $state = $userId === null ? null : static::stateOf($record, (int) $userId);

        [$icon, $color, $tooltip] = match ($state) {
            self::SEEN => [Heroicon::Eye, 'var(--success-600)', __('This person is up to date with all changes made to this record.')],
            self::PENDING => [Heroicon::Eye, 'var(--warning-500)', __('This person has notifications pending about changes made to this record.')],
            default => [Heroicon::EyeSlash, 'var(--gray-400)', __('This person is not watching this record.')],
        };

        $html = generate_icon_html($icon, size: IconSize::Small)?->toHtml() ?? '';

        return new HtmlString(sprintf(
            '<span class="epesi-watcher-eye" style="color: %s; display: inline-flex; vertical-align: -0.125em; margin-inline-end: 0.25rem;" title="%s" aria-label="%s">%s</span>',
            $color,
            e($tooltip),
            e($tooltip),
            $html,
        ));
    }

    /**
     * Load the read state of everyone in $field for every record in $records
     * at once: two queries for a list page, whatever its size.
     *
     * @param  Collection<int, Model>  $records
     */
    public static function prepare(Field $field, Collection $records): void
    {
        $records = $records->filter(fn (Model $record): bool => Watchdog::watches($record));

        if ($records->isEmpty()) {
            return;
        }

        $states = static::states();
        $type = $records->first()->getMorphClass();
        $missing = $records->reject(fn (Model $record): bool => isset($states["prepared:{$type}:{$record->getKey()}"]));

        if ($missing->isEmpty()) {
            return;
        }

        $relationship = (string) $field->getParam('relationship');
        $userIds = $missing
            ->flatMap(fn (Model $record) => $record->relationLoaded($relationship) ? $record->getRelation($relationship) : $record->{$relationship}()->withoutGlobalScopes()->get())
            ->map(fn (Model $related): mixed => $related->getAttribute('user_id'))
            ->filter()
            ->unique()
            ->values();

        $recordIds = $missing->map(fn (Model $record): mixed => $record->getKey())->all();

        if ($userIds->isNotEmpty()) {
            $query = fn () => Subscription::query()
                ->where('subscribable_type', $type)
                ->whereIn('subscribable_id', $recordIds)
                ->whereIn('user_id', $userIds->all());

            foreach ($query()->get(['user_id', 'subscribable_id']) as $subscription) {
                $states["{$type}:{$subscription->subscribable_id}:{$subscription->user_id}"] = self::SEEN;
            }

            foreach ($query()->withUnseenChanges()->get(['user_id', 'subscribable_id']) as $subscription) {
                $states["{$type}:{$subscription->subscribable_id}:{$subscription->user_id}"] = self::PENDING;
            }
        }

        foreach ($recordIds as $id) {
            $states["prepared:{$type}:{$id}"] = true;
        }
    }

    /**
     * The Record Info popup's rows: everyone watching the record who can see
     * it, with their eye — what Epesi's Watch icon tooltip listed.
     *
     * @return array<int, array{0: string, 1: Htmlable, 2: string}>
     */
    public static function recordInfoRows(Model $record): array
    {
        if (! Watchdog::watches($record)) {
            return [];
        }

        $subscriptions = Subscription::query()
            ->where('subscribable_type', $record->getMorphClass())
            ->where('subscribable_id', $record->getKey())
            ->with('user.contact')
            ->get();

        $pending = Subscription::query()
            ->whereKey($subscriptions->modelKeys())
            ->withUnseenChanges()
            ->pluck('id')
            ->all();

        return $subscriptions
            ->filter(fn (Subscription $subscription): bool => $subscription->user !== null && Watchdog::canSee($subscription->user, $record))
            ->sortBy(fn (Subscription $subscription): string => mb_strtolower($subscription->user->displayName()))
            ->values()
            ->map(function (Subscription $subscription, int $index) use ($pending): array {
                $seen = ! in_array($subscription->getKey(), $pending, true);
                $color = $seen ? 'var(--success-600)' : 'var(--warning-500)';
                $eye = generate_icon_html(Heroicon::Eye, size: IconSize::Small)?->toHtml() ?? '';

                return [
                    $index === 0 ? __('Watching') : '',
                    new HtmlString(sprintf(
                        '<span style="color: %s; display: inline-flex; vertical-align: -0.125em; margin-inline-end: 0.25rem;">%s</span>%s',
                        $color,
                        $eye,
                        e($subscription->user->displayName()),
                    )),
                    $seen ? __('Up to date') : __('Has changes not yet seen'),
                ];
            })
            ->all();
    }

    /** What remembered eyes no longer hold after someone saw or started watching something. */
    public static function forget(): void
    {
        app(RequestMemo::class)->forget('watchdog:eyes');
    }

    /**
     * The fields of $record's recordset whose people watch it.
     *
     * @return array<int, Field>
     */
    public static function fieldsOf(Model $record): array
    {
        $resource = Filament::getPanel('main', isStrict: false)?->getModelResource($record::class);

        if (! $resource || ! is_subclass_of($resource, RecordsetResource::class)) {
            return [];
        }

        return array_values(array_filter($resource::resolvedFields(), fn (Field $field): bool => $field->hasWatchers()));
    }

    /**
     * The users behind the people $field holds for $record; someone with no
     * login has no one to watch with.
     *
     * @return Collection<int, User>
     */
    public static function usersIn(Model $record, Field $field): Collection
    {
        $relationship = (string) $field->getParam('relationship');

        if (! $record->isRelation($relationship)) {
            return collect();
        }

        $ids = $record->{$relationship}()->withoutGlobalScopes()->get()
            ->map(fn (Model $related): mixed => $related->getAttribute('user_id'))
            ->filter()
            ->unique()
            ->all();

        return $ids === [] ? collect() : User::query()->whereKey($ids)->get();
    }

    protected static function stateOf(Model $record, int $userId): ?string
    {
        $states = static::states();
        $type = $record->getMorphClass();

        if (! isset($states["prepared:{$type}:{$record->getKey()}"])) {
            return null;
        }

        return $states["{$type}:{$record->getKey()}:{$userId}"] ?? null;
    }

    /** @return ArrayObject<string, mixed> */
    protected static function states(): ArrayObject
    {
        return app(RequestMemo::class)->remember('watchdog:eyes', fn (): ArrayObject => new ArrayObject);
    }

    protected static function latestActivity(Model $record): ?Model
    {
        $activity = ActivitylogServiceProvider::determineActivityModel();

        return $activity::query()
            ->where('subject_type', $record->getMorphClass())
            ->where('subject_id', $record->getKey())
            ->latest('id')
            ->first();
    }
}
