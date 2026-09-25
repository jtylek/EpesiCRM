<?php

namespace Epesi\Modules\Watchdog\Filament\Concerns;

use App\Models\User;
use Epesi\Modules\Watchdog\Listeners\NotifySubscribers;
use Epesi\Modules\Watchdog\Models\Subscription;
use Epesi\Modules\Watchdog\Watchdog;
use Illuminate\Database\Eloquent\Model;

/**
 * Who changed what on a watched record — the latest change someone else made
 * since its user last looked, as the bell told it. The Watched page's columns.
 */
trait DescribesLatestChange
{
    /**
     * Each row's latest unseen change, looked up once per request.
     *
     * @var array<int, Model|null>
     */
    protected array $latestChanges = [];

    protected function latestChange(Subscription $subscription): ?Model
    {
        if (! array_key_exists($subscription->id, $this->latestChanges)) {
            $this->latestChanges[$subscription->id] = Watchdog::unseenActivities($subscription)->latest('id')->first();
        }

        return $this->latestChanges[$subscription->id];
    }

    /** "Ann" — who made the latest change. */
    protected function latestChangeBy(Subscription $subscription): ?string
    {
        $change = $this->latestChange($subscription);

        if ($change === null) {
            return null;
        }

        return $this->causerOf($change)?->displayName() ?? __('System');
    }

    /** "Title" — what the latest change changed. */
    protected function summarizeLatestChange(Subscription $subscription): ?string
    {
        $change = $this->latestChange($subscription);

        // A record since deleted, or no longer visible, has no fields to name.
        if ($change === null || $subscription->subscribable === null) {
            return null;
        }

        return app(NotifySubscribers::class)->summarize($change, $subscription->subscribable);
    }

    protected function causerOf(Model $change): ?User
    {
        return $change->causer_type === (new User)->getMorphClass()
            ? User::query()->find($change->causer_id)
            : null;
    }
}
