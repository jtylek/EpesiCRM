<?php

namespace Epesi\Modules\Appearance\Filament\Administration\Resources\Themes\Schemas;

use Epesi\Modules\Appearance\Models\Theme;
use Filament\Infolists\Components\ColorEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ThemeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->columnSpanFull(),
                        ColorEntry::make('accent_color')
                            ->label(__('Accent colour'))
                            ->placeholder(__('This panel\'s own colour')),
                        TextEntry::make('density')
                            ->label(__('Density'))
                            ->formatStateUsing(fn (string $state): string => Theme::densities()[$state] ?? $state)
                            ->badge(),
                        TextEntry::make('font_size')
                            ->label(__('Font size'))
                            ->formatStateUsing(fn (string $state): string => Theme::fontSizes()[$state] ?? $state)
                            ->badge(),
                        IconEntry::make('is_default')
                            ->label(__('Default theme'))
                            ->boolean()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
