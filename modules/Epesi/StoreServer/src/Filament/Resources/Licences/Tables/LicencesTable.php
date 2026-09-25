<?php

namespace Epesi\Modules\StoreServer\Filament\Resources\Licences\Tables;

use Epesi\Modules\RecordBrowser\Filament\LinkedRecords;
use Epesi\Modules\StoreServer\Models\Licence;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LicencesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label('Licence key')
                    ->searchable()
                    ->copyable(),
                LinkedRecords::style(
                    TextColumn::make('product.name'),
                    fn (Licence $record): ?string => $record->product ? LinkedRecords::url($record->product) : null,
                )
                    ->label('Product')
                    ->sortable(),
                TextColumn::make('purchaser_email')
                    ->label('Purchaser')
                    ->placeholder(__('-'))
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (Licence $record): string => $record->isValid() ? 'valid' : 'invalid')
                    ->color(fn (Licence $record): string => $record->isValid() ? 'success' : 'danger'),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->placeholder(__('never'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }
}
