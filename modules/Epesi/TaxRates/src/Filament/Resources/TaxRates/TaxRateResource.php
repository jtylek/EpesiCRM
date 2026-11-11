<?php

namespace Epesi\Modules\TaxRates\Filament\Resources\TaxRates;

use BackedEnum;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Epesi\Modules\TaxRates\Models\TaxRate;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Administration → Data → Tax Rates.
 *
 * Also registered in the main panel, without a menu entry: RecordBrowser
 * finds a relation's title and link through the current panel's resources, so
 * without it an item's or a document line's tax rate would show as
 * "Tax Rate #3" instead of its name.
 */
class TaxRateResource extends RecordsetResource
{
    protected static ?string $model = TaxRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected static ?string $navigationLabel = 'Tax Rates';

    protected static ?string $recordTitleAttribute = 'name';

    public static function shouldRegisterNavigation(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'administration';
    }

    public static function fields(): array
    {
        return [
            Field::text('name')->required()->maxLength(64)->inTable()->searchable(),
            Field::text('code')->maxLength(32)->inTable(),
            Field::decimal('rate', 4)->label('Rate (%)')->required()->inTable()
                ->formUsing(fn (TextInput $field): TextInput => $field->numeric()->minValue(0)->maxValue(100)->step(0.0001)),
            Field::select('tax_type', TaxRate::TYPES)->required()->default('standard')->inTable(),
            Field::text('country_code')->label('Country Code')->maxLength(2),
            Field::longText('description'),
            Field::boolean('active')->required()->default(true)->inTable()->filterable(),
        ];
    }
}
