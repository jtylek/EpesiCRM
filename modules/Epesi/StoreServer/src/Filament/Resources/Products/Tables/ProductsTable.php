<?php

namespace Epesi\Modules\StoreServer\Filament\Resources\Products\Tables;

use Epesi\Modules\StoreServer\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('module_id')
                    ->label('Module id')
                    ->searchable(),
                TextColumn::make('category')
                    ->placeholder(__('-'))
                    ->searchable(),
                TextColumn::make('price')
                    ->state(fn (Product $record): string => $record->isFree()
                        ? 'Free'
                        : number_format($record->price_amount / 100, 2).' '.$record->price_currency),
                TextColumn::make('latest_version')
                    ->label('Latest')
                    ->state(fn (Product $record): string => $record->latestRelease()?->version ?? '-')
                    ->badge(),
                TextColumn::make('releases_count')
                    ->label('Releases')
                    ->counts('releases'),
                IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_published')->label('Published'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('name');
    }
}
