<?php

namespace Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Schemas;

use Epesi\Modules\RecordBrowser\Recordset\FieldOverrides;
use Filament\Forms\Components\Checkbox;
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
    ];

    public static function fill(array $record): array
    {
        $registry = app(FieldOverrides::class);
        [$model, $field, $position] = $registry->definition($record['model_type'], $record['name']);
        $overrides = array_intersect_key(
            $registry->properties($record['model_type'], $record['name']),
            array_flip($registry->editable($model, $field)),
        );

        return [
            'properties' => array_replace($field->administratorDefaults() + ['position' => $position], $overrides),
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
            $input = match ($key) {
                'label', 'section' => TextInput::make("properties.{$key}")->maxLength(255)->required($key === 'label'),
                'help' => Textarea::make("properties.{$key}")->maxLength(4000),
                'position' => TextInput::make("properties.{$key}")->numeric()->minValue(0)->maxValue(100000)->required(),
                default => Toggle::make("properties.{$key}"),
            };
            $locked = ! in_array($key, $editable, true);
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

    public static function save(array $record, array $data): void
    {
        $properties = [];
        foreach ($data['inherit'] ?? [] as $key => $inherit) {
            if (! $inherit) {
                $properties[$key] = $data['properties'][$key] ?? null;
            }
        }
        app(FieldOverrides::class)->save($record['model_type'], $record['name'], $properties);
    }
}
