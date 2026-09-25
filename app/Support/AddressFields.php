<?php

namespace App\Support;

use Epesi\Modules\RecordBrowser\Recordset\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

/**
 * The address block, as recordset fields — Contacts declares it twice (billing
 * and home) and Companies once.
 *
 * Only Zone needs anything the Field DSL can't say on its own: it is a Select
 * when the chosen country has a known state/province list and a free-text input
 * when it doesn't, which is two components for one field. They are wrapped in a
 * Group so the pair still occupies a single cell of the section's two-column
 * grid, the way the mutually-exclusive pair did before.
 */
class AddressFields
{
    /**
     * @param  string  $prefix  '' for the main address, 'home_' for Contact's second one
     * @return array<int, Field>
     */
    public static function block(
        string $prefix = '',
        string $section = 'Address',
        bool $collapsible = false,
        bool $collapsed = false,
    ): array {
        $apply = fn (Field $field): Field => $field->section($section, $collapsible, $collapsed);

        return [
            $apply(Field::text($prefix.'address_1')->maxLength(64)),
            $apply(Field::text($prefix.'address_2')->maxLength(64)),
            // City earns a column on the list for the main address only; a
            // second one for the home address would just crowd it.
            $apply(Field::text($prefix.'city')->maxLength(64)->inTable($prefix === '')),
            $apply(Field::text($prefix.'postal_code')->maxLength(64)),
            $apply(static::country($prefix)),
            $apply(static::zone($prefix)),
        ];
    }

    /**
     * Declared as text rather than select so the list and the view render a
     * plain value; only the form needs the picker.
     */
    public static function country(string $prefix = ''): Field
    {
        $name = $prefix.'country';
        $zone = $prefix.'zone';
        $label = Str::headline($name);

        return Field::text($name)->formUsing(fn (): Select => Select::make($name)
            ->label($label)
            ->options(Countries::options())
            ->searchable()
            ->native(false)
            ->live()
            ->afterStateUpdated(fn (Set $set) => $set($zone, null)));
    }

    public static function zone(string $prefix = ''): Field
    {
        $name = $prefix.'zone';
        $country = $prefix.'country';
        $label = trim(($prefix === 'home_' ? 'Home ' : '').'Zone / State');

        return Field::text($name)
            ->label($label)
            ->formUsing(fn (): Group => Group::make([
                Select::make($name)
                    ->label($label)
                    ->options(fn (Get $get): array => Zones::forCountry($get($country)))
                    ->searchable()
                    ->native(false)
                    ->visible(fn (Get $get): bool => Zones::hasZones($get($country))),
                TextInput::make($name)
                    ->label($label)
                    ->maxLength(64)
                    ->visible(fn (Get $get): bool => ! Zones::hasZones($get($country))),
            ])->columns(1));
    }
}
