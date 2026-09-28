<?php

namespace Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Schemas;

use Epesi\Modules\CommonData\Models\CommonDataNode;
use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldRegistry;
use Epesi\Modules\RecordBrowser\Models\CollectionItem;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Epesi\Modules\RecordBrowser\Recordset\LinkableRecordsets;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
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
                        // An address takes fewer types than a recordset.
                        ->live()
                        // The column lives on one table; moving a definition to
                        // another recordset would leave its data behind.
                        ->disabledOn('edit')
                        ->helperText(__('Which records this field is added to.')),

                    Select::make('type')
                        ->options(fn (Get $get): array => static::typeOptions($get('model_type')))
                        ->required()
                        ->live()
                        // Several files, but one shared-list value: the two
                        // toggles share `params.multiple`, so neither can carry
                        // a default of its own without overwriting the other's.
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('params.multiple', $state === FieldType::File->value))
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

                    TextInput::make('prefix')
                        ->maxLength(16)
                        ->statePath('params.prefix')
                        ->helperText(__('Put in front of the number, e.g. "INV-".'))
                        ->visible(fn (Get $get): bool => $get('type') === FieldType::Autonumber->value),

                    TextInput::make('pad_length')
                        ->label('Digits')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(20)
                        ->default(4)
                        ->statePath('params.pad_length')
                        ->helperText(__('Padded up to this many digits.'))
                        ->visible(fn (Get $get): bool => $get('type') === FieldType::Autonumber->value),

                    TextInput::make('pad_mask')
                        ->label('Pad character')
                        ->required()
                        ->length(1)
                        ->default('0')
                        ->statePath('params.pad_mask')
                        ->helperText(__('What fills the number up to that many digits, e.g. "0" for "0042".'))
                        ->visible(fn (Get $get): bool => $get('type') === FieldType::Autonumber->value),

                    Toggle::make('multiple_files')
                        ->label('Several files')
                        ->statePath('params.multiple')
                        ->visible(fn (Get $get): bool => $get('type') === FieldType::File->value),

                    // What a link field points at. Locked once created: its
                    // stored keys belong to that recordset.
                    Select::make('recordset')
                        ->label('Links to')
                        ->options(fn (): array => LinkableRecordsets::options())
                        ->required()
                        ->disabledOn('edit')
                        ->statePath('params.recordset')
                        ->helperText(__('Which kind of record it links to.'))
                        ->visible(fn (Get $get): bool => in_array($get('type'), [
                            FieldType::Relation->value, FieldType::Relations->value,
                        ], true)),

                    // Arrays are the top-level lists and any entry with
                    // entries of its own (Administration → Common Data).
                    Select::make('array')
                        ->label('List')
                        ->options(fn (): array => CommonDataNode::query()
                            ->where(fn (Builder $query): Builder => $query->whereNull('parent_id')->orWhereHas('children'))
                            ->orderBy('path')
                            ->pluck('path', 'path')
                            ->all())
                        ->searchable()
                        ->required()
                        ->statePath('params.array')
                        ->helperText(__('Which shared list (Administration → Common Data) it offers.'))
                        ->visible(fn (Get $get): bool => $get('type') === FieldType::CommonData->value),

                    // One value is a string column, several a JSON one, so it
                    // can't change once the column exists.
                    Toggle::make('multiple_values')
                        ->label('Several values')
                        ->disabledOn('edit')
                        ->statePath('params.multiple')
                        ->visible(fn (Get $get): bool => $get('type') === FieldType::CommonData->value),

                    TextInput::make('max_size_mb')
                        ->label('Maximum file size (MB)')
                        ->integer()
                        ->minValue(1)
                        ->maxValue(1024)
                        ->default(50)
                        ->statePath('params.max_size_mb')
                        ->visible(fn (Get $get): bool => $get('type') === FieldType::File->value),

                    // Which collection type its items are. Locked once
                    // created: its items are rows of that type's table.
                    Select::make('collection')
                        ->label('Collection')
                        ->options(fn (): array => CollectionItem::options())
                        ->required()
                        ->disabledOn('edit')
                        ->statePath('params.collection')
                        ->helperText(__('What each record can have any number of, e.g. addresses.'))
                        ->visible(fn (Get $get): bool => $get('type') === FieldType::Collection->value),

                    // Legacy keeps this list in a recordset of its own per
                    // field (task_related); here it's the field's setting.
                    Select::make('recordsets')
                        ->label('Recordsets')
                        ->multiple()
                        ->options(fn (): array => LinkableRecordsets::options())
                        ->statePath('params.recordsets')
                        ->helperText(__('Which kinds of record it can link to. Empty offers them all.'))
                        ->visible(fn (Get $get): bool => $get('type') === FieldType::Related->value),

                    // Legacy's "Minutes Interval" choices, 60 being full hours.
                    Select::make('minutes_step')
                        ->label('Minutes interval')
                        ->options([1 => '1', 2 => '2', 5 => '5', 10 => '10', 15 => '15', 20 => '20', 30 => '30', 60 => __('Full hours')])
                        ->default(1)
                        ->selectablePlaceholder(false)
                        ->statePath('params.minutes_step')
                        ->helperText(__('Times can be picked this many minutes apart.'))
                        ->visible(fn (Get $get): bool => in_array($get('type'), [
                            FieldType::Time->value, FieldType::DateTime->value,
                        ], true)),

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
     * Every type an administrator may add, or, for a collection type's items
     * (an address), those an item can hold (FieldType::fitsCollectionItem()).
     *
     * @return array<string, string>
     */
    protected static function typeOptions(?string $recordset): array
    {
        $class = $recordset !== null ? Relation::getMorphedModel($recordset) : null;
        $isItem = is_string($class) && is_subclass_of($class, CollectionItem::class);
        $options = [];

        foreach (FieldType::cases() as $type) {
            if ($type->isAdministratorDefinable() && (! $isItem || $type->fitsCollectionItem())) {
                $options[$type->value] = $type->label();
            }
        }

        return $options;
    }
}
