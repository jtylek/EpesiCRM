<?php

namespace Epesi\Modules\Currencies\Filament\Resources\Currencies;

use App\Filament\Concerns\TranslatesResourceLabels;
use BackedEnum;
use Epesi\Modules\Currencies\Filament\Resources\Currencies\Pages\ListCurrencies;
use Epesi\Modules\Currencies\Models\Currency;
use Epesi\Modules\Currencies\Models\CurrencyHomePeriod;
use Epesi\Modules\Currencies\Services\CurrencyRepository;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Administration → Currencies: the currencies this install uses and which of
 * them is the home currency. One page; currencies are added and edited in
 * modals, as Common Data entries are.
 */
class CurrencyResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Currency::class;

    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?string $navigationLabel = 'Currencies';

    protected static ?string $modelLabel = 'currency';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('code')
                ->required()
                ->searchable()
                ->options(fn (?Currency $record): array => collect(Currency::iso())
                    ->except(Currency::query()->whereKeyNot($record?->getKey())->pluck('code')->all())
                    ->map(fn (array $iso, string $code): string => $code.' ('.__($iso[0]).')')
                    ->all())
                ->disabled(fn (?Currency $record): bool => $record !== null)
                ->live()
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    if ($iso = Currency::iso()[$state] ?? null) {
                        $set('name', $iso[0]);
                        $set('decimals', $iso[1]);
                    }
                }),

            TextInput::make('name')
                ->required()
                ->maxLength(255),

            TextInput::make('decimals')
                ->required()
                ->integer()
                ->minValue(0)
                ->maxValue(4)
                ->default(2)
                ->helperText(__('Decimal places of an amount in this currency (ISO 4217 minor units).')),

            Toggle::make('active')
                ->default(true)
                ->helperText(__('Only active currencies can be chosen on new records.')),
        ]);
    }

    public static function table(Table $table): Table
    {
        $currencies = app(CurrencyRepository::class);

        return $table
            ->defaultSort('position')
            ->reorderable('position')
            ->paginated(false)
            ->description(fn (): string => static::homeDescription())
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => __($state)),

                TextColumn::make('home')
                    ->label('Home')
                    ->state(fn (Currency $record): ?string => $record->code === $currencies->home() ? __('Home currency') : null)
                    ->badge()
                    ->color('primary'),

                TextColumn::make('decimals')
                    ->numeric()
                    ->alignEnd(),

                ToggleColumn::make('active')
                    ->disabled(fn (Currency $record): bool => $record->code === $currencies->home())
                    ->tooltip(fn (Currency $record): ?string => $record->code === $currencies->home() ? __('The home currency cannot be deactivated.') : null),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip(__('Edit')),

                // A currency that is or was the home currency is part of the
                // books' history; deactivate it instead.
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip(__('Delete'))
                    ->hidden(fn (Currency $record): bool => $currencies->isHomeInAnyPeriod($record->code)),
            ]);
    }

    /** "Home currency: PLN since 2020-01-01 (before: USD)." */
    public static function homeDescription(): string
    {
        $periods = CurrencyHomePeriod::query()->orderByDesc('effective_from')->get();
        $current = $periods->first(fn ($period): bool => $period->effective_from->lte(today())) ?? $periods->last();

        if ($current === null) {
            return __('No home currency is set.');
        }

        $text = $current->effective_from->year <= 1970
            ? __('Home currency: :code.', ['code' => $current->currency_code])
            : __('Home currency: :code since :date.', ['code' => $current->currency_code, 'date' => $current->effective_from->toDateString()]);

        $others = $periods->reject(fn ($period): bool => $period->is($current))
            ->map(fn ($period): string => $period->effective_from->year <= 1970
                ? $period->currency_code
                : $period->currency_code.' '.__('from :date', ['date' => $period->effective_from->toDateString()]));

        return $others->isEmpty() ? $text : $text.' '.__('Other periods: :periods.', ['periods' => $others->implode(', ')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCurrencies::route('/'),
        ];
    }
}
