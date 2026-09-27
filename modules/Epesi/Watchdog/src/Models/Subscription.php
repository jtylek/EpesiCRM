<?php

namespace Epesi\Modules\Watchdog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Spatie\Activitylog\ActivitylogServiceProvider;

/**
 * One user watching one record — a `utils_watchdog_subscription` row, with
 * `last_seen_activity_id` in place of `last_seen_event`.
 */
class Subscription extends Model
{
    use MassPrunable;

    /**
     * How long a subscription lasts with nothing happening: no change to its
     * record, and nothing new seen by its user (seeing moves updated_at, as
     * starting to watch sets it).
     */
    public const EXPIRES_AFTER_DAYS = 90;

    protected $table = 'epesi_watchdog_subscriptions';

    protected $fillable = [
        'user_id',
        'subscribable_type',
        'subscribable_id',
        'last_seen_activity_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Only subscriptions whose record someone other than their user changed
     * since they last looked: Watchdog::unseenActivities(), as one query for a
     * whole list instead of one per subscription. Keep the two in step.
     */
    public function scopeWithUnseenChanges(Builder $query): Builder
    {
        $activity = ActivitylogServiceProvider::determineActivityModel();
        $log = (new $activity)->getTable();
        $subscriptions = $this->getTable();

        return $query->whereExists(fn (QueryBuilder $changes): QueryBuilder => $changes
            ->selectRaw('1')
            ->from($log)
            ->whereColumn("{$log}.subject_type", "{$subscriptions}.subscribable_type")
            ->whereColumn("{$log}.subject_id", "{$subscriptions}.subscribable_id")
            ->where(fn (QueryBuilder $q): QueryBuilder => $q
                ->whereNull("{$subscriptions}.last_seen_activity_id")
                ->orWhereColumn("{$log}.id", '>', "{$subscriptions}.last_seen_activity_id"))
            ->where(fn (QueryBuilder $q): QueryBuilder => $q
                ->whereNull("{$log}.causer_id")
                ->orWhere("{$log}.causer_type", '!=', (new User)->getMorphClass())
                ->orWhereColumn("{$log}.causer_id", '!=', "{$subscriptions}.user_id")));
    }

    /**
     * Subscriptions nothing has happened to in EXPIRES_AFTER_DAYS, for
     * `model:prune` to delete (scheduled by WatchdogServiceProvider). Without
     * it every record a user created, or heard of through watching its type,
     * would stay theirs to watch for good. Epesi kept its subscriptions and
     * dropped events older than three months instead; here the events are
     * the records' History, which stays.
     */
    public function prunable(): Builder
    {
        $cutoff = now()->subDays(self::EXPIRES_AFTER_DAYS);
        $activity = ActivitylogServiceProvider::determineActivityModel();
        $log = (new $activity)->getTable();
        $subscriptions = $this->getTable();

        return static::query()
            ->where("{$subscriptions}.updated_at", '<', $cutoff)
            ->whereNotExists(fn (QueryBuilder $changes): QueryBuilder => $changes
                ->selectRaw('1')
                ->from($log)
                ->whereColumn("{$log}.subject_type", "{$subscriptions}.subscribable_type")
                ->whereColumn("{$log}.subject_id", "{$subscriptions}.subscribable_id")
                ->where("{$log}.created_at", '>=', $cutoff));
    }
}
