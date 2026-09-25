<?php

namespace App\Filament\Administration\Resources\Modules\Tables;

use App\Filament\Administration\Resources\Modules\ModuleActions;
use App\Models\Module;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ModulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('module_id')
                    ->label('Id')
                    ->searchable(),
                TextColumn::make('version'),
                TextColumn::make('panels')
                    ->badge()
                    ->placeholder(__('-')),
                IconColumn::make('enabled')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('files')
                    ->label('Files')
                    ->state(fn (Module $record): string => $record->directoryExists() ? __('present') : __('missing'))
                    ->badge()
                    ->color(fn (Module $record): string => $record->directoryExists() ? 'success' : 'danger'),
                TextColumn::make('installed_at')
                    ->label('Installed')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('enabled'),
            ])
            ->recordActions([
                ModuleActions::toggle(),
                ModuleActions::uninstall(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading(__('No modules installed'))
            ->emptyStateDescription(__('Install one from a zip package to add screens without a code deploy.'))
            ->defaultSort('name');
    }
}
