<?php

namespace Epesi\Modules\PriorityList\Filament\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use BackedEnum;
use Epesi\Modules\PriorityList\PriorityList;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Administration → which record types can go on a priority list. Every
 * RecordsetResource in the main panel is a candidate with no code of its own
 * (PriorityList::candidateTypes()) — this is where an administrator turns
 * one on or off, the way Modules turns a module on or off.
 */
class PriorityListManagement extends Page implements HasTable
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use InteractsWithTable;
    use TranslatesPageLabels;

    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $navigationLabel = 'Priority list types';

    protected static ?string $title = 'Priority list types';

    protected static ?string $slug = 'priority-list-types';

    protected string $view = 'epesi-priority-list::management';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => collect(PriorityList::candidateTypes())->map(fn (string $alias): array => [
                '__key' => $alias,
                'label' => PriorityList::typeLabelFor($alias),
                'enabled' => PriorityList::isEnabled($alias),
            ]))
            ->columns([
                TextColumn::make('label')->label(__('Type')),
                IconColumn::make('enabled')->label(__('Enabled'))->boolean(),
            ])
            ->recordActions([
                $this->toggleAction(),
            ])
            ->toolbarActions([])
            ->paginated(false);
    }

    protected function toggleAction(): Action
    {
        return Action::make('toggle')
            ->label(fn (array $record): string => $record['enabled'] ? __('Disable') : __('Enable'))
            ->icon(fn (array $record): Heroicon => $record['enabled'] ? Heroicon::OutlinedPause : Heroicon::OutlinedPlay)
            ->color(fn (array $record): string => $record['enabled'] ? 'warning' : 'success')
            ->requiresConfirmation()
            ->modalDescription(fn (array $record): string => $record['enabled']
                ? __('Nobody can add a new one, and any already on a priority list stop showing there, until it is turned on again.')
                : __('Anyone can add one to their priority list with the flag on its page.'))
            ->action(function (array $record): void {
                PriorityList::setEnabled($record['__key'], ! $record['enabled']);

                Notification::make()
                    ->success()
                    ->title($record['label'].' is now '.($record['enabled'] ? 'disabled' : 'enabled'))
                    ->send();
            });
    }
}
