<?php

namespace Epesi\Modules\Appearance\Filament\Administration\Resources\Themes\Tables;

use Epesi\Modules\Appearance\Models\Theme;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;

class ThemesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                ColorColumn::make('accent_color')
                    ->label(__('Accent colour')),
                TextColumn::make('density')
                    ->formatStateUsing(fn (string $state): string => Theme::densities()[$state] ?? $state)
                    ->badge(),
                TextColumn::make('font_size')
                    ->label(__('Font size'))
                    ->formatStateUsing(fn (string $state): string => Theme::fontSizes()[$state] ?? $state)
                    ->badge(),
                IconColumn::make('is_default')
                    ->label(__('Default'))
                    ->boolean(),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActions([
                ViewAction::make()
                    ->iconButton()
                    ->tooltip(__('View')),
                EditAction::make()
                    ->iconButton()
                    ->tooltip(__('Edit')),
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip(__('Delete')),
            ]);
    }
}
