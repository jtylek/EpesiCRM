<?php

namespace Epesi\Modules\StoreServer\Filament\Resources\Products;

use App\Filament\Concerns\TranslatesResourceLabels;
use BackedEnum;
use Epesi\Modules\StoreServer\Filament\Resources\Products\Pages\CreateProduct;
use Epesi\Modules\StoreServer\Filament\Resources\Products\Pages\EditProduct;
use Epesi\Modules\StoreServer\Filament\Resources\Products\Pages\ListProducts;
use Epesi\Modules\StoreServer\Filament\Resources\Products\Pages\ViewProduct;
use Epesi\Modules\StoreServer\Filament\Resources\Products\RelationManagers\ReleasesRelationManager;
use Epesi\Modules\StoreServer\Filament\Resources\Products\Schemas\ProductForm;
use Epesi\Modules\StoreServer\Filament\Resources\Products\Schemas\ProductInfolist;
use Epesi\Modules\StoreServer\Filament\Resources\Products\Tables\ProductsTable;
use Epesi\Modules\StoreServer\Models\Product;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProductResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Store';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ReleasesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'view' => ViewProduct::route('/{record}'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
