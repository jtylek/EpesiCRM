<?php

namespace Epesi\Modules\StoreServer\Filament\Resources\Licences\Schemas;

use Epesi\Modules\RecordBrowser\Filament\LinkedRecords;
use Epesi\Modules\StoreServer\Models\Licence;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LicenceInfolist
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
                        TextEntry::make('key')->label('Licence key')->copyable(),
                        LinkedRecords::style(
                            TextEntry::make('product.name'),
                            fn (Licence $record): ?string => $record->product ? LinkedRecords::url($record->product) : null,
                        )->label('Product'),
                        TextEntry::make('status')
                            ->badge()
                            ->state(fn (Licence $record): string => $record->isValid() ? 'valid' : 'invalid')
                            ->color(fn (Licence $record): string => $record->isValid() ? 'success' : 'danger'),
                        TextEntry::make('purchaser_name')->placeholder(__('-')),
                        TextEntry::make('purchaser_email')->placeholder(__('-')),
                        TextEntry::make('expires_at')->label('Expires')->dateTime()->placeholder(__('never')),
                        TextEntry::make('revoked_at')->label('Revoked')->dateTime()->placeholder(__('-')),
                    ]),
            ]);
    }
}
