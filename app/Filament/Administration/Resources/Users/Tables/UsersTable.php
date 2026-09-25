<?php

namespace App\Filament\Administration\Resources\Users\Tables;

use App\Filament\Administration\Resources\Users\UserActions;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->placeholder(__('-')),
                IconColumn::make('active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('active')
                    ->label('Status')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive')
                    ->placeholder(__('All'))
                    ->default(true),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActions([
                ViewAction::make()
                    ->iconButton()
                    ->tooltip(__('View')),
                EditAction::make()
                    ->iconButton()
                    ->tooltip(__('Edit')),
                UserActions::logInAs()
                    ->iconButton()
                    ->tooltip(__('Log in as user')),
                UserActions::toggleActive()
                    ->iconButton()
                    ->tooltip(fn ($record): string => $record->active ? __('Deactivate') : __('Reactivate')),
            ]);
    }
}
