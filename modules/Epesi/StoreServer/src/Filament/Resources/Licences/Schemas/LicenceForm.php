<?php

namespace Epesi\Modules\StoreServer\Filament\Resources\Licences\Schemas;

use Epesi\Modules\StoreServer\Models\Licence;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LicenceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('key')
                            ->label('Licence key')
                            ->default(fn (): string => Licence::generateKey())
                            ->helperText(__('Generated automatically; the customer pastes this into their Store settings.'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('purchaser_name')->maxLength(255),
                        TextInput::make('purchaser_email')->email()->maxLength(255),
                        DateTimePicker::make('expires_at')
                            ->label('Expires')
                            ->seconds(false)
                            ->helperText(__('Leave empty for a perpetual licence.')),
                        DateTimePicker::make('revoked_at')
                            ->label('Revoked')
                            ->seconds(false)
                            ->helperText(__('Set to block downloads immediately.')),
                    ]),
            ]);
    }
}
