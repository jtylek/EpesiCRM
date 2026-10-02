<?php

namespace Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields;

use App\Support\Demo;
use Epesi\Modules\RecordBrowser\Models\CollectionItem;
use Epesi\Modules\RecordBrowser\Recordset\FieldOverrides;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetFeatures;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

/**
 * Administration → Recordsets → Features: which addon modules attach
 * themselves to a recordset — whether e-mails can be linked to Projects,
 * whether Tickets get a Notes tab. The choice is stored per recordset
 * (RecordsetFeatures); a module only says what it starts as.
 */
class RecordsetFeatureAction
{
    /** @param  callable(): ?string  $currentRecordset  the recordset the list is showing */
    public static function features(callable $currentRecordset): Action
    {
        return Demo::guard(Action::make('features')
            ->label('Features')
            ->icon(Heroicon::OutlinedPuzzlePiece)
            ->color('gray')
            ->visible(fn (): bool => RecordsetFeatures::definitions() !== [])
            ->modalHeading(__('Recordset features'))
            ->modalDescription(__('Choose which addon modules attach to a recordset. A change applies from the next page load.'))
            ->modalWidth('lg')
            ->fillForm(fn (): array => static::state($currentRecordset() ?? array_key_first(static::recordsets())))
            ->schema([
                Select::make('recordset')
                    ->label('Recordset')
                    ->options(fn (): array => static::recordsets())
                    ->selectablePlaceholder(false)
                    ->live()
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('features', static::state($state)['features'])),
                CheckboxList::make('features')
                    ->label('Features')
                    ->options(fn (): array => array_map(fn (string $label): string => __($label), RecordsetFeatures::definitions()))
                    ->helperText(fn (): ?string => __('Links already made stay in place when a feature is turned off; they show again when it is turned back on.')),
            ])
            ->action(function (array $data): void {
                $alias = (string) $data['recordset'];
                abort_unless(array_key_exists($alias, static::recordsets()), 404);

                foreach (array_keys(RecordsetFeatures::definitions()) as $feature) {
                    RecordsetFeatures::set($feature, $alias, in_array($feature, $data['features'] ?? [], true));
                }

                Notification::make()->success()->title(__('Features saved'))->send();
            }));
    }

    /**
     * @return array<string, string> morph alias => label, recordsets of the main panel only
     */
    protected static function recordsets(): array
    {
        return collect(app(FieldOverrides::class)->recordsets())
            ->reject(fn (array $recordset): bool => is_subclass_of($recordset['model'], CollectionItem::class))
            ->map(fn (array $recordset): string => __($recordset['label']))
            ->all();
    }

    /**
     * @return array{recordset: ?string, features: array<int, string>}
     */
    protected static function state(?string $alias): array
    {
        return [
            'recordset' => $alias,
            'features' => $alias === null ? [] : array_values(Arr::where(
                array_keys(RecordsetFeatures::definitions()),
                fn (string $feature): bool => RecordsetFeatures::enabled($feature, $alias),
            )),
        ];
    }
}
