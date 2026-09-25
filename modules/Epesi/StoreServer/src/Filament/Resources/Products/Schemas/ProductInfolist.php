<?php

namespace Epesi\Modules\StoreServer\Filament\Resources\Products\Schemas;

use Epesi\Modules\StoreServer\Models\Product;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->inlineLabel()
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        TextEntry::make('name'),
                        TextEntry::make('module_id')->label('Module id'),
                        TextEntry::make('category')->placeholder(__('-')),
                        TextEntry::make('price')
                            ->state(fn (Product $record): string => $record->isFree()
                                ? 'Free'
                                : number_format($record->price_amount / 100, 2).' '.$record->price_currency),
                        IconEntry::make('is_published')->label('Published')->boolean(),
                        TextEntry::make('latest_version')
                            ->label('Latest release')
                            ->state(fn (Product $record): string => $record->latestRelease()?->version ?? 'none')
                            ->badge(),
                        TextEntry::make('licences_count')
                            ->label('Licences')
                            ->state(fn (Product $record): int => $record->licences()->count()),
                        TextEntry::make('description')->placeholder(__('-'))->columnSpanFull(),
                    ]),
            ]);
    }
}
