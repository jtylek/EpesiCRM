<?php

namespace Epesi\Modules\Currencies\Filament\Resources\ExchangeRates;

use App\Filament\Concerns\TranslatesResourceLabels;
use BackedEnum;
use Epesi\Modules\Currencies\Filament\Resources\ExchangeRates\Pages\ListExchangeRates;
use Epesi\Modules\Currencies\Models\CurrencyRate;
use Epesi\Modules\Currencies\Models\CurrencySetting;
use Epesi\Modules\Currencies\Services\CurrencyRepository;
use Epesi\Modules\Currencies\Services\RateFormatter;
use Epesi\Modules\Currencies\Services\RateProviders;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Administration → Exchange Rates: the daily rates the provider published,
 * plus custom rates an administrator typed for pairs no provider covers. The
 * fetched rows are a cache and read-only; custom ones can be edited.
 */
class ExchangeRateResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = CurrencyRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected static ?string $navigationLabel = 'Exchange Rates';

    protected static ?string $modelLabel = 'exchange rate';

    /** The custom-rate form: 1 base = rate quote on a date. */
    public static function form(Schema $schema): Schema
    {
        $options = fn (): array => app(CurrencyRepository::class)->options();

        return $schema->components([
            Select::make('base')
                ->label('From')
                ->required()
                ->options($options),

            Select::make('quote')
                ->label('To')
                ->required()
                ->options($options)
                ->different('base'),

            DatePicker::make('rate_date')
                ->label('Date')
                ->required()
                ->default(today()),

            TextInput::make('rate')
                ->required()
                ->numeric()
                ->gt(0)
                ->helperText(fn (Get $get): string => __('How many :to one :from buys. Used for this pair when the provider has no rate for it.', [
                    'from' => $get('base') ?: __('units of the first currency'),
                    'to' => $get('quote') ?: __('units of the second currency'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('rate_date', 'desc')
            ->description(fn (): string => static::settingsDescription())
            ->columns([
                TextColumn::make('rate_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('provider')
                    ->badge()
                    ->color(fn (string $state): string => $state === CurrencyRate::CUSTOM ? 'warning' : 'gray')
                    ->formatStateUsing(fn (string $state): string => $state === CurrencyRate::CUSTOM ? __('Custom') : strtoupper($state)),

                TextColumn::make('pair')
                    ->label('Rate')
                    ->state(fn (CurrencyRate $record): string => '1 '.$record->base.' = '.RateFormatter::format($record->rate).' '.$record->quote),

                TextColumn::make('base')
                    ->label('From')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('quote')
                    ->label('To')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('fetched_at')
                    ->label('Fetched')
                    ->dateTime()
                    ->placeholder(__('—'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('provider')
                    ->options(CurrencyRate::providers()),

                SelectFilter::make('currency')
                    ->options(fn (): array => app(CurrencyRepository::class)->options(activeOnly: false))
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->where(fn (Builder $query) => $query->where('base', $data['value'])->orWhere('quote', $data['value']))),

                Filter::make('rate_date')
                    ->schema([
                        DatePicker::make('from')->label('Date from'),
                        DatePicker::make('until')->label('Date to'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('rate_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('rate_date', '<=', $date))),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip(__('Edit'))
                    ->visible(fn (CurrencyRate $record): bool => $record->provider === CurrencyRate::CUSTOM),

                DeleteAction::make()
                    ->iconButton()
                    ->tooltip(__('Delete'))
                    ->visible(fn (CurrencyRate $record): bool => $record->provider === CurrencyRate::CUSTOM),
            ]);
    }

    /** Which provider fills the table, and what the last fetch did. */
    public static function settingsDescription(): string
    {
        $setting = CurrencySetting::current();
        $provider = CurrencyRate::providers()[app(RateProviders::class)->currentId()];

        $text = $setting->auto_fetch
            ? __('Rates from :provider, fetched every day.', ['provider' => $provider])
            : __('Rates from :provider, fetched only with Fetch now.', ['provider' => $provider]);

        if ($setting->last_fetch_at) {
            $text .= ' '.__('Last fetch :when: :result', ['when' => $setting->last_fetch_at->diffForHumans(), 'result' => $setting->last_fetch_result]);
        }

        return $text;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExchangeRates::route('/'),
        ];
    }
}
