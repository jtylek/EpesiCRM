<?php

namespace Epesi\Modules\CommonData\Filament\Resources\CommonData;

use App\Filament\Concerns\TranslatesResourceLabels;
use BackedEnum;
use Epesi\Modules\CommonData\Filament\Resources\CommonData\Pages\ListCommonData;
use Epesi\Modules\CommonData\Filament\Resources\CommonData\Schemas\CommonDataForm;
use Epesi\Modules\CommonData\Filament\Resources\CommonData\Tables\CommonDataTable;
use Epesi\Modules\CommonData\Models\CommonDataNode;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Administration → Common Data: the shared lookup lists every recordset select
 * reads from — the analogue of Epesi's Administration → Common Data
 * (`Utils_CommonData::browse()`).
 *
 * One page, not the usual List/View/Create/Edit set. The tree is browsed by
 * drilling into a node rather than opening it, so `?path=` selects the level
 * and entries are created and edited in modals — matching Epesi's screen, where
 * a row's View action moves you down a level and there is no record page at
 * all.
 */
class CommonDataResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = CommonDataNode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $recordTitleAttribute = 'key';

    protected static ?string $navigationLabel = 'Common Data';

    protected static ?string $modelLabel = 'entry';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return CommonDataForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommonDataTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommonData::route('/'),
        ];
    }
}
