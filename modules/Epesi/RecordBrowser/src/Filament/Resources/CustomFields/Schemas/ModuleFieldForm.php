<?php

namespace Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Schemas;

use Epesi\Modules\RecordBrowser\Recordset\FieldOverrides;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Epesi\Modules\RecordBrowser\Recordset\LinkableRecordsets;
use Epesi\Modules\RecordBrowser\Recordset\LinkedTo;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class ModuleFieldForm
{
    public const LABELS = [
        'label' => 'Label', 'help' => 'Help text', 'section' => 'Group', 'position' => 'Position',
        'show_in_form' => 'On the add/edit form', 'show_in_view' => 'On the record view',
        'show_in_table' => 'As a list column', 'filterable' => 'As a list filter', 'required' => 'Required',
        'recordsets' => 'Can link to',
    ];

    public static function fill(array $record): array
    {
        $registry = app(FieldOverrides::class);
        [$model, $field, $position] = $registry->definition($record['model_type'], $record['name']);
        $overrides = array_intersect_key(
            $registry->properties($record['model_type'], $record['name']),
            array_flip($registry->editable($model, $field)),
        );
        $properties = array_replace($field->administratorDefaults() + ['position' => $position], $overrides);

        // A Linked to field's recordsets as the form edits them: an "Any
        // recordset" switch, else a list to tick.
        $anyRecordset = ($properties['recordsets'] ?? null) === LinkedTo::ANY;
        if (array_key_exists('recordsets', $properties)) {
            $properties['recordsets'] = $anyRecordset ? [] : (array) $properties['recordsets'];
        }

        return [
            'properties' => $properties,
            'any_recordset' => $anyRecordset,
            'inherit' => array_map(fn (string $key): bool => ! array_key_exists($key, $overrides), array_combine(array_keys(self::LABELS), array_keys(self::LABELS))),
        ];
    }

    public static function components(array $record, bool $readOnly = false): array
    {
        $registry = app(FieldOverrides::class);
        [$model, $field, $position] = $registry->definition($record['model_type'], $record['name']);
        $editable = $registry->editable($model, $field);
        $defaults = $field->administratorDefaults() + ['position' => $position];
        $groups = [];
        foreach (self::LABELS as $key => $label) {
            if ($key === 'recordsets' && $field->type !== FieldType::Related) {
                continue;
            }
            $locked = ! in_array($key, $editable, true);
            if ($key === 'recordsets') {
                $groups[] = self::recordsetsGroup($defaults[$key], $readOnly, $locked);

                continue;
            }
            $input = match ($key) {
                'label', 'section' => TextInput::make("properties.{$key}")->maxLength(255)->required($key === 'label'),
                'help' => Textarea::make("properties.{$key}")->maxLength(4000),
                'position' => TextInput::make("properties.{$key}")->numeric()->minValue(0)->maxValue(100000)->required(),
                default => Toggle::make("properties.{$key}"),
            };
            $input->label(__($label))
                ->disabled(fn (Get $get): bool => $readOnly || $locked || $get("inherit.{$key}"));
            if ($locked) {
                $input->helperText(__('Protected by the module or database.'));
            }
            $groups[] = Group::make([
                $input,
                Checkbox::make("inherit.{$key}")
                    ->label(__('Use module default'))
                    ->live()
                    ->visible(! $readOnly && ! $locked)
                    ->afterStateUpdated(function (bool $state, Set $set) use ($key, $defaults): void {
                        if ($state) {
                            $set("properties.{$key}", $defaults[$key]);
                        }
                    }),
            ]);
        }

        return [Group::make($groups)->columns(2)];
    }

    /**
     * Which recordsets a Linked to field offers: any recordset, or those
     * ticked, each with its icon. Full width, below the switches.
     *
     * @param  list<string>|string  $default  the module's own choice (LinkedTo::ANY or aliases)
     */
    protected static function recordsetsGroup(array|string $default, bool $readOnly, bool $locked): Group
    {
        $disabled = fn (Get $get): bool => $readOnly || $locked || (bool) $get('inherit.recordsets');

        return Group::make([
            Toggle::make('any_recordset')
                ->label(__('Can link to any recordset'))
                ->helperText(__('Including recordsets installed later.'))
                ->live()
                ->disabled($disabled),
            CheckboxList::make('properties.recordsets')
                ->label(__('Can link to'))
                ->options(fn (): array => collect(LinkableRecordsets::options())
                    ->map(fn (string $label, string $alias): string => LinkableRecordsets::iconLabel($alias, $label))
                    ->all())
                ->allowHtml()
                ->columns(3)
                ->required(fn (Get $get): bool => ! $get('any_recordset') && ! $get('inherit.recordsets'))
                ->visible(fn (Get $get): bool => ! $get('any_recordset'))
                ->disabled($disabled)
                ->helperText($locked ? __('Protected by the module or database.') : null),
            Checkbox::make('inherit.recordsets')
                ->label(__('Use module default'))
                ->live()
                ->visible(! $readOnly && ! $locked)
                ->afterStateUpdated(function (bool $state, Set $set) use ($default): void {
                    if ($state) {
                        $set('any_recordset', $default === LinkedTo::ANY);
                        $set('properties.recordsets', $default === LinkedTo::ANY ? [] : (array) $default);
                    }
                }),
        ])->columnSpanFull();
    }

    public static function save(array $record, array $data): void
    {
        $properties = [];
        foreach ($data['inherit'] ?? [] as $key => $inherit) {
            if (! $inherit) {
                $properties[$key] = $data['properties'][$key] ?? null;
            }
        }
        if (array_key_exists('recordsets', $properties) && ($data['any_recordset'] ?? false)) {
            $properties['recordsets'] = LinkedTo::ANY;
        }
        app(FieldOverrides::class)->save($record['model_type'], $record['name'], $properties);
    }
}
