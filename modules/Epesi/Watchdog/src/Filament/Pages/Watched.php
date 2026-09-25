<?php

namespace Epesi\Modules\Watchdog\Filament\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use BackedEnum;
use Epesi\Modules\Watchdog\Filament\Concerns\DescribesLatestChange;
use Epesi\Modules\Watchdog\Models\Subscription;
use Epesi\Modules\Watchdog\Watchdog;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

/**
 * The records you watch and what changed on them since you last looked — the
 * port of Utils_Watchdog's applet and its "Mark all as read". Opening a record
 * from here (or from anywhere) marks it read; see WatchdogServiceProvider. So
 * does reading its notifications in the bell; see this module's
 * DatabaseNotifications.
 */
class Watched extends Page implements HasTable
{
    use DescribesLatestChange;
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use InteractsWithTable;
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEye;

    protected static ?string $navigationLabel = 'Watched';

    protected static ?string $title = 'Watched records';

    protected static ?int $navigationSort = 90;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasAnyRole(['super_admin', 'manager', 'employee']) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        $count = Subscription::query()->where('user_id', $user->id)->withUnseenChanges()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Subscription::query()
                ->where('user_id', Auth::id())
                ->with('subscribable'))
            ->heading(__('Watched records'))
            ->defaultSort('updated_at', 'desc')
            ->recordUrl(fn (Subscription $record): ?string => $record->subscribable ? Watchdog::url($record->subscribable) : null)
            ->columns([
                TextColumn::make('subscribable_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => Str::headline($state))
                    ->badge()
                    ->sortable(),
                // Just the title: Type says what kind of record it is.
                TextColumn::make('record')
                    ->label('Record')
                    ->state(fn (Subscription $record): string => $record->subscribable
                        ? Watchdog::title($record->subscribable)
                        : 'Deleted record #'.$record->subscribable_id),
                // Who changed what, the latest change only — the bell's line
                // for it, in two columns.
                TextColumn::make('changed_by')
                    ->label('Changed by')
                    ->state(fn (Subscription $record): ?string => $this->latestChangeBy($record))
                    ->placeholder(__('-')),
                TextColumn::make('changes')
                    ->label('Changes')
                    ->state(fn (Subscription $record): ?string => $this->summarizeLatestChange($record))
                    ->wrap()
                    ->placeholder(__('-')),
                TextColumn::make('unseen')
                    ->label('')
                    ->state(fn (Subscription $record): int => Watchdog::unseenActivities($record)->count())
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('last_change')
                    ->label('Last change')
                    ->state(fn (Subscription $record): mixed => $this->latestChange($record)?->created_at)
                    ->since()
                    ->placeholder(__('-')),
            ])
            ->filters([
                // What needs a look first; a record with nothing new is one
                // filter change away.
                TernaryFilter::make('unseen')
                    ->label('Has new changes')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query): Builder => $query->withUnseenChanges(),
                        false: fn (Builder $query): Builder => $query->whereNot(fn (Builder $q): Builder => $q->withUnseenChanges()),
                    ),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActions([
                Action::make('markRead')
                    ->label('Mark as read')
                    ->icon(Heroicon::OutlinedCheck)
                    ->iconButton()
                    ->tooltip(__('Mark as read'))
                    ->visible(fn (Subscription $record): bool => $record->subscribable !== null)
                    ->action(fn (Subscription $record) => $this->markSeen([$record])),
                Action::make('unsubscribe')
                    ->label('Stop watching')
                    ->icon(Heroicon::OutlinedEyeSlash)
                    ->iconButton()
                    ->tooltip(__('Stop watching'))
                    ->color('danger')
                    ->action(fn (Subscription $record) => $record->delete()),
            ])
            ->toolbarActions([
                BulkAction::make('markRead')
                    ->label('Mark as read')
                    ->icon(Heroicon::OutlinedCheck)
                    ->action(fn (Collection $records) => $this->markSeen($records)),
                BulkAction::make('unsubscribe')
                    ->label('Stop watching')
                    ->icon(Heroicon::OutlinedEyeSlash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->delete()),
            ]);
    }

    /**
     * Mark as read here marks the bell's notifications of those changes read
     * too (Watchdog::markSeen()); the bell and the count in the menu follow
     * straight away rather than at their next poll.
     *
     * @param  iterable<int, Subscription>  $subscriptions
     */
    protected function markSeen(iterable $subscriptions): void
    {
        foreach ($subscriptions as $subscription) {
            if ($subscription->subscribable !== null) {
                Watchdog::markSeen($subscription->user, $subscription->subscribable);
            }
        }

        $this->dispatch('databaseNotificationsSent');
        $this->dispatch('refresh-sidebar');
    }

    /** The bell marked changes seen: the list re-renders without them. */
    #[On('watchdog-seen')]
    public function refreshAfterBell(): void {}

    protected function getHeaderActions(): array
    {
        return [
            Action::make('categories')
                ->label('Watch record types')
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->modalDescription(__('Be notified about changes to every record of the chosen types, not just the ones you watch.'))
                ->fillForm(fn (): array => ['categories' => Watchdog::categoriesOf(Auth::user())])
                ->schema([
                    CheckboxList::make('categories')
                        ->label('Record types')
                        ->options(collect(Watchdog::$recordTypes)
                            ->mapWithKeys(fn (string $type): array => [$type => Str::headline($type)])
                            ->all()),
                ])
                ->action(fn (array $data) => Watchdog::syncCategories(Auth::user(), $data['categories'] ?? [])),
        ];
    }
}
