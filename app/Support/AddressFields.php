<?php

namespace App\Support;

use Epesi\Modules\CommonData\Facades\CommonData;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * An address's Country and Zone, as Field objects: the Address collection
 * type (Epesi\Modules\RecordBrowser\Models\Address) declares them, and the
 * setup wizard's "Your company" step borrows their form inputs.
 *
 * Only Zone needs anything the Field DSL can't say on its own: it is a Select
 * when the chosen country has a known state/province list and a free-text input
 * when it doesn't, which is two components for one field. They are wrapped in a
 * Group so the pair still occupies a single cell of the two-column grid.
 *
 * Both read and write their sibling relative to their own container, so in a
 * repeater each address's Zone follows that address's Country.
 */
class AddressFields
{
    /**
     * Declared as text rather than select so the list and the view render a
     * plain value; only the form needs the picker.
     */
    public static function country(): Field
    {
        return Field::text('country')->formUsing(fn (TextInput $component, Field $field): Select => Select::make('country')
            ->label($field->getLabel())
            ->required($field->isRequired())
            ->options(fn (): array => CommonData::array('Countries'))
            ->searchable()
            ->native(false)
            ->live()
            ->afterStateUpdated(fn (Set $set) => $set('zone', null)));
    }

    public static function zone(): Field
    {
        $label = 'Zone / State';

        return Field::text('zone')
            ->label($label)
            ->formUsing(fn (): Group => Group::make([
                Select::make('zone')
                    ->label($label)
                    ->options(fn (Get $get): array => static::zonesFor($get('country')))
                    ->searchable()
                    ->native(false)
                    ->visible(fn (Get $get): bool => static::zonesFor($get('country')) !== []),
                TextInput::make('zone')
                    ->label($label)
                    ->maxLength(64)
                    ->visible(fn (Get $get): bool => static::zonesFor($get('country')) === []),
            ])->columns(1));
    }

    /**
     * A country's zone list, or empty for one with none — including an
     * unchosen country, which a bare 'Countries/'.$country would resolve to
     * the Countries list itself rather than an empty path.
     *
     * @return array<string, string>
     */
    protected static function zonesFor(?string $country): array
    {
        return filled($country) ? CommonData::array('Countries/'.$country) : [];
    }
}
