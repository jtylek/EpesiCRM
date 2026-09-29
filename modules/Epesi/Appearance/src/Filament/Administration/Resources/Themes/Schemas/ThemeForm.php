<?php

namespace Epesi\Modules\Appearance\Filament\Administration\Resources\Themes\Schemas;

use Epesi\Modules\Appearance\Models\Theme;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ThemeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(64)
                            ->columnSpanFull(),
                        // A new theme starts as a copy of the current default
                        // theme's colour and density — only on Create, where
                        // ->default() supplies the form's initial state; an
                        // Edit page fills from the record being edited
                        // instead, so this never fights an existing theme's
                        // own saved values.
                        ColorPicker::make('accent_color')
                            ->label(__('Accent colour'))
                            ->default(fn (): ?string => Theme::default()?->accent_color)
                            ->helperText(__('Leave blank to keep this panel\'s own colour.')),
                        Select::make('density')
                            ->label(__('Density'))
                            ->options(Theme::densities())
                            ->default(fn (): string => Theme::default()?->density ?? Theme::DENSITY_COMPACT)
                            ->native(false)
                            ->required(),
                        Select::make('font_size')
                            ->label(__('Font size'))
                            ->options(Theme::fontSizes())
                            ->default(fn (): string => Theme::default()?->font_size ?? Theme::FONT_SIZE_DEFAULT)
                            ->native(false)
                            ->required(),
                        Toggle::make('is_default')
                            ->label(__('Default theme'))
                            ->helperText(__('What a user gets until they choose one of their own.'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
