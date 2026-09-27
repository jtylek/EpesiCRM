<?php

namespace Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Tables;

use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldRegistry;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\CustomFieldActions;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CustomFieldsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->groups(['model_type'])
            ->defaultGroup('model_type')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('model_type')
                    ->label('Recordset')
                    ->formatStateUsing(fn (string $state): string => CustomFieldRegistry::participatingModels()[$state] ?? $state)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('label')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->label()),
                TextColumn::make('column')->label('Column')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('section')->label('Group')->placeholder(__('-'))->toggleable(),
                TextColumn::make('position')->sortable()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('required')->boolean(),
                IconColumn::make('active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('model_type')
                    ->label('Recordset')
                    ->options(CustomFieldRegistry::participatingModels()),
                TernaryFilter::make('active'),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip(__('View')),
                EditAction::make()->iconButton()->tooltip(__('Edit')),
                CustomFieldActions::dropColumn()->iconButton(),
            ]);
    }
}
