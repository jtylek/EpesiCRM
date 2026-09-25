<?php

namespace Epesi\Modules\StoreServer\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        TextInput::make('module_id')
                            ->label('Module id')
                            ->helperText(__('Must match the "id" in the package\'s module.json, e.g. epesi/notes.'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('category')
                            ->maxLength(255),
                        TextInput::make('icon')
                            ->helperText(__('Heroicon name, e.g. heroicon-o-pencil-square.'))
                            ->maxLength(255),
                        TextInput::make('price_amount')
                            ->label('Price')
                            ->helperText(__('In minor units (4900 = $49.00). Leave empty for a free module.'))
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('price_currency')
                            ->label('Currency')
                            ->default('USD')
                            ->maxLength(3),
                        Toggle::make('is_published')
                            ->label('Published')
                            ->helperText(__('Unpublished products are hidden from the catalog API.')),
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
