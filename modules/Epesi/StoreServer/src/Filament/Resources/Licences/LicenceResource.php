<?php

namespace Epesi\Modules\StoreServer\Filament\Resources\Licences;

use App\Filament\Concerns\TranslatesResourceLabels;
use BackedEnum;
use Epesi\Modules\StoreServer\Filament\Resources\Licences\Pages\CreateLicence;
use Epesi\Modules\StoreServer\Filament\Resources\Licences\Pages\EditLicence;
use Epesi\Modules\StoreServer\Filament\Resources\Licences\Pages\ListLicences;
use Epesi\Modules\StoreServer\Filament\Resources\Licences\Pages\ViewLicence;
use Epesi\Modules\StoreServer\Filament\Resources\Licences\Schemas\LicenceForm;
use Epesi\Modules\StoreServer\Filament\Resources\Licences\Schemas\LicenceInfolist;
use Epesi\Modules\StoreServer\Filament\Resources\Licences\Tables\LicencesTable;
use Epesi\Modules\StoreServer\Models\Licence;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LicenceResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Licence::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Store';

    protected static ?string $recordTitleAttribute = 'key';

    public static function form(Schema $schema): Schema
    {
        return LicenceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LicenceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LicencesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLicences::route('/'),
            'create' => CreateLicence::route('/create'),
            'view' => ViewLicence::route('/{record}'),
            'edit' => EditLicence::route('/{record}/edit'),
        ];
    }
}
