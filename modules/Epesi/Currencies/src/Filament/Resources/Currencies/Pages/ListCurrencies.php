<?php

namespace Epesi\Modules\Currencies\Filament\Resources\Currencies\Pages;

use App\Filament\Concerns\HasResourceIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use Epesi\Modules\Currencies\Filament\Resources\Currencies\CurrencyResource;
use Epesi\Modules\Currencies\Models\Currency;
use Epesi\Modules\Currencies\Services\CurrencyRepository;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListCurrencies extends ListRecords
{
    use HasResourceIconBreadcrumb;
    use HidesPageHeading;

    protected static string $resource = CurrencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New currency')
                ->mutateDataUsing(fn (array $data): array => [
                    ...$data,
                    'position' => (int) Currency::query()->max('position') + 1,
                ]),

            $this->setHomeAction(),
        ];
    }

    /**
     * The home currency changes from a date on, so documents booked before it
     * keep the one they were booked in. Without a date it applies to every
     * date: for a new install, or to correct a mistake.
     */
    protected function setHomeAction(): Action
    {
        return Action::make('setHome')
            ->label('Set home currency')
            ->icon(Heroicon::OutlinedHome)
            ->color('gray')
            ->modalDescription(__('The currency the books are kept in. Amounts in other currencies are converted into it at the rate of their own date.'))
            ->schema([
                Select::make('code')
                    ->label('Currency')
                    ->required()
                    ->options(fn (): array => app(CurrencyRepository::class)->options())
                    ->default(fn (): string => app(CurrencyRepository::class)->home()),

                DatePicker::make('effective_from')
                    ->label('From')
                    ->helperText(__('Leave empty to make it the home currency for all dates, past ones included. With a date, earlier records keep the home currency they had.')),
            ])
            ->action(function (array $data): void {
                app(CurrencyRepository::class)->setHome($data['code'], $data['effective_from'] ?: null);

                Notification::make()->success()->title(__('Home currency set'))->send();
            });
    }
}
