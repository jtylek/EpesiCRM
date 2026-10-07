<?php

namespace Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields;

use App\Filament\Concerns\TranslatesResourceLabels;
use App\Support\Demo;
use BackedEnum;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Pages\CreateCustomField;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Pages\EditCustomField;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Pages\ListCustomFields;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Pages\ViewCustomField;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Schemas\CustomFieldForm;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Schemas\CustomFieldInfolist;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Tables\FieldCatalogue;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Administration → Recordsets: add a field to an existing recordset and it appears
 * on the form, the view, the column chooser, the filters and the record's
 * history — the direct analogue of Epesi's Administration → Records Browser
 * screen (`Utils_RecordBrowser::administrator_panel()`).
 *
 * Lives in the administration panel, whose `super_admin` gate is the access
 * control — the same reasoning as Modules and the Store. Hand-written rather
 * than built on the engine because the form is type-dependent: which parameter
 * inputs appear follows the chosen type.
 */
class CustomFieldResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = CustomField::class;

    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $recordTitleAttribute = 'label';

    protected static ?string $navigationLabel = 'Recordsets';

    protected static ?string $slug = 'recordsets';

    protected static ?string $modelLabel = 'field';

    protected static ?string $pluralModelLabel = 'Recordsets';

    public static function canAccess(): bool
    {
        return auth()->user()?->active && auth()->user()?->hasRole('super_admin') && ! Demo::enabled();
    }

    public static function form(Schema $schema): Schema
    {
        return CustomFieldForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomFieldInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FieldCatalogue::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomFields::route('/'),
            'create' => CreateCustomField::route('/create'),
            'view' => ViewCustomField::route('/{record}'),
            'edit' => EditCustomField::route('/{record}/edit'),
        ];
    }
}
