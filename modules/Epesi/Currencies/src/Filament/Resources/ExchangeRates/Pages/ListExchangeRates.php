<?php

namespace Epesi\Modules\Currencies\Filament\Resources\ExchangeRates\Pages;

use App\Filament\Concerns\HasResourceIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use Carbon\CarbonImmutable;
use Epesi\Modules\Currencies\Filament\Resources\ExchangeRates\ExchangeRateResource;
use Epesi\Modules\Currencies\Models\CurrencyRate;
use Epesi\Modules\Currencies\Models\CurrencySetting;
use Epesi\Modules\Currencies\Services\RateFetcher;
use Epesi\Modules\Currencies\Services\RateProviders;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class ListExchangeRates extends ListRecords
{
    use HasResourceIconBreadcrumb;
    use HidesPageHeading;

    protected static string $resource = ExchangeRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->fetchAction(),

            $this->convertAction(),

            ActionGroup::make([
                CreateAction::make()
                    ->label('Add custom rate')
                    ->icon(Heroicon::OutlinedPlus)
                    ->mutateDataUsing(fn (array $data): array => [...$data, 'provider' => CurrencyRate::CUSTOM]),

                $this->fetchRangeAction(),

                $this->settingsAction(),
            ])
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->color('gray')
                ->tooltip(__('More')),
        ];
    }

    protected function fetchAction(): Action
    {
        return Action::make('fetch')
            ->label('Fetch now')
            ->icon(Heroicon::OutlinedArrowPath)
            ->action(function (): void {
                $fetcher = app(RateFetcher::class);
                $report = $fetcher->fetch();

                Notification::make()
                    ->status($report['error'] ? 'danger' : 'success')
                    ->title($report['error'] ? __('Exchange rates not fetched') : __('Exchange rates fetched'))
                    ->body($fetcher->describe($report))
                    ->send();
            });
    }

    /**
     * "Fetch now" only fills forward from each currency's last cached day, so a date before
     * the cache starts (a cutover, an old document) is never reached. This fetches the window.
     */
    protected function fetchRangeAction(): Action
    {
        return Action::make('fetchRange')
            ->label('Fetch rates for a period')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->modalWidth(Width::Medium)
            ->schema([
                DatePicker::make('from')
                    ->label('From')
                    ->required()
                    ->maxDate(now()),

                DatePicker::make('to')
                    ->label('To')
                    ->helperText(__('Leave empty for today.'))
                    ->afterOrEqual('from')
                    ->maxDate(now()),
            ])
            ->action(function (array $data): void {
                $fetcher = app(RateFetcher::class);
                $report = $fetcher->fetch(null, CarbonImmutable::parse($data['from']), filled($data['to'] ?? null) ? CarbonImmutable::parse($data['to']) : null);

                Notification::make()
                    ->status($report['error'] ? 'danger' : 'success')
                    ->title($report['error'] ? __('Exchange rates not fetched') : __('Exchange rates fetched'))
                    ->body($fetcher->describe($report))
                    ->send();
            });
    }

    /** The Dashboard's Currency Converter applet, in a modal: the rate a document dated on a given day gets. */
    protected function convertAction(): Action
    {
        return Action::make('convert')
            ->label('Currency Converter')
            ->icon(Heroicon::OutlinedCalculator)
            ->color('gray')
            ->modalWidth(Width::Medium)
            ->modalContent(fn () => view('epesi-currencies::converter-modal'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'));
    }

    protected function settingsAction(): Action
    {
        return Action::make('settings')
            ->label('Settings')
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->fillForm(fn (): array => CurrencySetting::current()->only(['rate_provider', 'auto_fetch', 'backfill_from']))
            ->schema([
                Select::make('rate_provider')
                    ->label('Daily rates from')
                    ->required()
                    ->options(fn (): array => app(RateProviders::class)->options())
                    ->helperText(__('ECB: euro reference rates. NBP: the National Bank of Poland\'s table A; Polish VAT requires its rate from the business day before the document date. Automatic picks NBP when the home currency is PLN.')),

                Toggle::make('auto_fetch')
                    ->label('Fetch every day'),

                DatePicker::make('backfill_from')
                    ->label('Fetch history from')
                    ->required()
                    ->helperText(__('The first fetch, and the first after a currency is added, reaches back to this date.')),
            ])
            ->action(function (array $data): void {
                CurrencySetting::current()->update($data);

                Notification::make()->success()->title(__('Settings saved'))->send();
            });
    }
}
