<?php

namespace Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Schemas;

use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldRegistry;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

class CustomFieldInfolist
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
                        TextEntry::make('model_type')
                            ->label('Recordset')
                            ->formatStateUsing(fn (string $state): string => CustomFieldRegistry::participatingModels()[$state] ?? $state),
                        TextEntry::make('type')
                            ->badge()
                            ->formatStateUsing(fn ($state): string => $state->label()),
                        TextEntry::make('label'),
                        TextEntry::make('name'),
                        TextEntry::make('section')->label('Group')->placeholder(__('-')),
                        TextEntry::make('position'),
                        TextEntry::make('help')->label('Help text')->placeholder(__('-'))->columnSpanFull(),
                    ]),

                Section::make(__('Storage'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        // The point of showing this: an administrator's field is
                        // a real column, so it is visible to SQL, exports and
                        // reporting exactly like a shipped one.
                        TextEntry::make('column')
                            ->label('Database column')
                            ->formatStateUsing(fn (CustomField $record, string $state): string => "{$record->modelTable()}.{$state}"),
                        IconEntry::make('column_exists')
                            ->label('Column present')
                            ->boolean()
                            ->state(fn (CustomField $record): bool => ($table = $record->modelTable()) !== null
                                && DatabaseSchema::hasColumn($table, (string) $record->column)),
                        IconEntry::make('required')->boolean(),
                        IconEntry::make('active')->boolean(),
                        IconEntry::make('show_in_form')->label('On the form')->boolean(),
                        IconEntry::make('show_in_view')->label('On the view')->boolean(),
                        IconEntry::make('show_in_table')->label('As a column')->boolean(),
                        IconEntry::make('filterable')->label('As a filter')->boolean(),
                    ]),
            ]);
    }
}
