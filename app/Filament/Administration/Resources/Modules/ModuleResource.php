<?php

namespace App\Filament\Administration\Resources\Modules;

use App\Filament\Administration\Resources\Modules\Pages\ListModules;
use App\Filament\Administration\Resources\Modules\Pages\ViewModule;
use App\Filament\Administration\Resources\Modules\Schemas\ModuleInfolist;
use App\Filament\Administration\Resources\Modules\Tables\ModulesTable;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Models\Module;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Port of Epesi's Administration → Module Administration & Store: what's
 * installed, what's on, and installing a new module from a zip. Rows are never
 * created or edited through a form — App\Services\Modules\ModuleInstaller owns
 * every write — so this resource has List and View pages only.
 *
 * Lives in the super_admin-only "administration" panel; access is gated there
 * by User::canAccessPanel(), same as Login Audit and Users.
 */
class ModuleResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Module::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?string $recordTitleAttribute = 'name';

    public static function infolist(Schema $schema): Schema
    {
        return ModuleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ModulesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListModules::route('/'),
            'view' => ViewModule::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
