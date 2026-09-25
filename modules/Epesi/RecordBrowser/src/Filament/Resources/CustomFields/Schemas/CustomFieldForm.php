<?php

namespace Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Schemas;

use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldRegistry;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class CustomFieldForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(2)
                ->components([
                    Select::make('model_type')
                        ->label('Recordset')
                        ->options(CustomFieldRegistry::participatingModels())
                        ->required()
                        // The column lives on one table; moving a definition to
                        // another recordset would leave its data behind.
                        ->disabledOn('edit')
                        ->helperText(__('Which records this field is added to.')),

                    Select::make('type')
                        ->options(static::typeOptions())
                        ->required()
                        ->live()
                        ->disabledOn('edit')
                        ->helperText(__('Changing the type later is only possible where the stored values survive it.')),

                    TextInput::make('label')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                            // The name is a slug for code and imports, so it
                            // follows the label until someone sets it by hand.
                            if (blank($get('name')) && filled($state)) {
                                $set('name', Str::snake(Str::ascii($state)));
                            }
                        })
                        ->helperText(__('What people see on the form and the list.')),

                    TextInput::make('name')
                        ->required()
                        ->maxLength(64)
                        ->rule('regex:/^[a-z][a-z0-9_]*$/')
                        ->unique(
                            table: CustomField::class,
                            ignoreRecord: true,
                            modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('model_type', $get('model_type')),
                        )
                        ->helperText(__('Stable identifier for code and imports — lowercase, no spaces.')),

                    TextInput::make('length')
                        ->label('Maximum length')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(1024)
                        ->default(255)
                        ->statePath('params.length')
                        ->visible(fn (Get $get): bool => in_array($get('type'), [
                            FieldType::Text->value, FieldType::Email->value,
                            FieldType::Url->value, FieldType::Phone->value,
                        ], true)),

                    TextInput::make('decimals')
                        ->label('Decimal places')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(8)
                        ->default(2)
                        ->statePath('params.decimals')
                        ->visible(fn (Get $get): bool => $get('type') === FieldType::Decimal->value),

                    KeyValue::make('options')
                        ->label('Choices')
                        ->keyLabel('Stored value')
                        ->valueLabel('Shown as')
                        ->statePath('params.options')
                        ->columnSpanFull()
                        ->visible(fn (Get $get): bool => in_array($get('type'), [
                            FieldType::Select->value, FieldType::Multiselect->value,
                        ], true)),

                    Textarea::make('help')
                        ->label('Help text')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Section::make(__('Where it appears'))
                ->columnSpanFull()
                ->columns(2)
                ->components([
                    TextInput::make('section')
                        ->label('Group')
                        ->maxLength(255)
                        ->helperText(__('Fields sharing a group name get their own card. Blank puts it with the rest.')),

                    TextInput::make('position')
                        ->numeric()
                        ->default(0)
                        ->helperText(__('Lower numbers come first.')),

                    Toggle::make('show_in_form')->label('On the add/edit form')->default(true),
                    Toggle::make('show_in_view')->label('On the record view')->default(true),
                    Toggle::make('show_in_table')->label('As a list column')
                        ->helperText(__('Off still offers it in the column chooser.')),
                    Toggle::make('filterable')->label('As a list filter'),
                    Toggle::make('required')->label('Required')
                        ->helperText(__('Checked when the form is submitted — existing records keep their blanks.')),
                    Toggle::make('exportable')->label('Included in exports')->default(true),
                    Toggle::make('active')
                        ->default(true)
                        ->helperText(__('Turning this off hides the field everywhere and keeps its data.')),
                ]),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected static function typeOptions(): array
    {
        $options = [];

        foreach (FieldType::cases() as $type) {
            if ($type->isAdministratorDefinable()) {
                $options[$type->value] = $type->label();
            }
        }

        return $options;
    }
}
