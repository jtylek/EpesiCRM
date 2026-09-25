<?php

namespace App\Filament\Administration\Resources\Modules\Schemas;

use App\Models\Module;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ModuleInfolist
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
                        TextEntry::make('module_id')->label('Id'),
                        TextEntry::make('version'),
                        IconEntry::make('enabled')->boolean(),
                        TextEntry::make('panels')->badge()->placeholder(__('-')),
                        TextEntry::make('path')
                            ->label('Directory')
                            ->formatStateUsing(fn (string $state): string => "modules/{$state}"),
                        TextEntry::make('namespace'),
                        TextEntry::make('installed_at')->label('Installed')->dateTime(),
                        TextEntry::make('description')->placeholder(__('-'))->columnSpanFull(),
                        TextEntry::make('requires')
                            ->label('Requires')
                            ->state(fn (Module $record): array => $record->manifest['requires'] ?? [])
                            ->badge()
                            ->placeholder(__('-')),
                        TextEntry::make('epesi_core')
                            ->label('Core constraint')
                            ->state(fn (Module $record): string => $record->manifest['epesi_core'] ?? '*'),
                    ]),
            ]);
    }
}
