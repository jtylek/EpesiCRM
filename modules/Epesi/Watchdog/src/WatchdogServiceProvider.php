<?php

namespace Epesi\Modules\Watchdog;

use Epesi\Modules\RecordBrowser\Extensions\RecordExtensions;
use Epesi\Modules\Watchdog\Filament\Livewire\DatabaseNotifications;
use Epesi\Modules\Watchdog\Listeners\NotifySubscribers;
use Epesi\Modules\Watchdog\Models\CategorySubscription;
use Epesi\Modules\Watchdog\Models\Subscription;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Spatie\Activitylog\ActivitylogServiceProvider;

class WatchdogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Relation::morphMap([
            'watchdog_subscription' => Subscription::class,
            'watchdog_category_subscription' => CategorySubscription::class,
        ]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $activity = ActivitylogServiceProvider::determineActivityModel();
        $activity::created(fn (Model $row) => app(NotifySubscribers::class)($row));

        RecordExtensions::headerActions('watchdog', fn (Model $record): array => static::headerActions($record));

        // Named by its class, as Filament names the panel's own components.
        Livewire::component(DatabaseNotifications::class, DatabaseNotifications::class);
    }

    /**
     * The eye on every watchable record's View page — Epesi's ActionBar
     * subscription toggle. Building the page's actions is also the moment
     * the record is being looked at, which is what marks its changes read
     * (Utils_WatchdogCommon::notified() on record view).
     *
     * @return array<int, Action>
     */
    protected static function headerActions(Model $record): array
    {
        $user = Auth::user();

        if (! $user || ! Watchdog::watches($record)) {
            return [];
        }

        Watchdog::markSeen($user, $record);

        return [
            Action::make('watchdogToggle')
                ->label(fn (): string => Watchdog::isSubscribed($user, $record) ? __('Watching') : __('Watch'))
                ->icon(fn (): Heroicon => Watchdog::isSubscribed($user, $record) ? Heroicon::Eye : Heroicon::OutlinedEye)
                ->color(fn (): string => Watchdog::isSubscribed($user, $record) ? 'primary' : 'gray')
                ->tooltip(fn (): string => Watchdog::isSubscribed($user, $record)
                    ? 'You are notified about changes. Click to stop watching.'
                    : 'Be notified when someone else changes this record.')
                ->action(function () use ($user, $record): void {
                    $watching = Watchdog::toggle($user, $record);

                    Notification::make()
                        ->title($watching ? __('Watching this record') : __('Stopped watching this record'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
