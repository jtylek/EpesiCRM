<?php

namespace Epesi\Modules\RecordBrowser\Models;

use App\Support\AddressFields;
use App\Support\Countries;
use App\Support\Zones;
use Epesi\Modules\RecordBrowser\Recordset\Field;

/**
 * A postal address, as a collection item: a contact's business and home
 * addresses, a company's offices, a billing or shipping address — as many as
 * a record needs, each with its kind (`Address_Kinds`: Business, Home,
 * Billing, Shipping, Other). Any recordset takes them with
 * `Field::collection('addresses', Address::class)`.
 *
 * City and Country are required, Address 1 isn't: many an address carried
 * over from legacy Epesi is a city and a postal code alone.
 *
 * @property ?string $address_1
 * @property ?string $address_2
 * @property ?string $city
 * @property ?string $postal_code
 * @property ?string $country
 * @property ?string $zone
 */
class Address extends CollectionItem
{
    protected $table = 'epesi_recordbrowser_addresses';

    public static function fields(): array
    {
        return [
            Field::text('address_1')->maxLength(64),
            Field::text('address_2')->maxLength(64),
            // The list's column: a record's first address's city.
            Field::text('city')->required()->maxLength(64)->inTable()->filterable(),
            Field::text('postal_code')->maxLength(64),
            // Codes, not names: searching them would find "PL" in "Plac…".
            AddressFields::country()->required()->filterable()->searchable(false),
            AddressFields::zone()->searchable(false),
        ];
    }

    public static function kinds(): string
    {
        return 'Address_Kinds';
    }

    public static function addActionLabel(): string
    {
        return __('Add address');
    }

    /**
     * Whether $values hold an address: a street, a city or a postal code.
     * Legacy Epesi filled Country and Zone in on every new record from the
     * user's regional settings, so those two alone say nothing — moving them
     * would give most records an "address" that is just a country.
     *
     * @param  array<string, mixed>  $values
     */
    public static function isAddress(array $values): bool
    {
        foreach (['address_1', 'address_2', 'city', 'postal_code'] as $column) {
            if (trim((string) ($values[$column] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /** "Main St 1, 00-001 Warsaw, Poland", the zone's and country's names for their codes. */
    public function summary(): string
    {
        $zone = Zones::forCountry($this->country)[$this->zone] ?? $this->zone;
        $country = Countries::options()[$this->country] ?? $this->country;

        return implode(', ', array_filter(
            [$this->address_1, $this->address_2, trim($this->postal_code.' '.$this->city), $zone, $country],
            fn (?string $part): bool => trim((string) $part) !== '',
        ));
    }
}
