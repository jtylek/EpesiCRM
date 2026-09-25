<?php

namespace App\Filament\Administration\Resources\LoginAudits\Tables;

use App\Models\LoginAudit;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LoginAuditsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('login')
                    ->label('Login')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.displayName')
                    ->label('User Name')
                    ->state(fn (LoginAudit $record): string => $record->user?->displayName() ?? '—'),
                TextColumn::make('impersonator.name')
                    ->label('Logged in by')
                    ->state(fn (LoginAudit $record): ?string => $record->impersonator?->displayName())
                    ->toggleable(),
                TextColumn::make('started_at')
                    ->label('Start')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ended_at')
                    ->label('End')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('duration')
                    ->state(fn (LoginAudit $record): string => gmdate('H:i:s', $record->started_at->diffInSeconds($record->ended_at))),
                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('host_name')
                    ->label('Host Name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('device')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('ended_at', 'desc');
    }
}
