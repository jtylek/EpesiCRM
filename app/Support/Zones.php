<?php

namespace App\Support;

/**
 * Per-country state/province lists driving the Country -> Zone chained
 * select. Like Countries, this is deliberately not Epesi's full
 * Data_Countries dataset (every country's subdivisions) — only the
 * countries common enough in this data to warrant a fixed list get one.
 * Any other country falls back to a free-text Zone field instead of a
 * bogus or empty select.
 */
class Zones
{
    /**
     * @return array<string, array<string, string>>
     */
    protected static function all(): array
    {
        return [
            'US' => [
                'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
                'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
                'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii',
                'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
                'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
                'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
                'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska',
                'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico',
                'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
                'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
                'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas',
                'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington',
                'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
            ],
            'CA' => [
                'AB' => 'Alberta', 'BC' => 'British Columbia', 'MB' => 'Manitoba',
                'NB' => 'New Brunswick', 'NL' => 'Newfoundland and Labrador',
                'NS' => 'Nova Scotia', 'NT' => 'Northwest Territories', 'NU' => 'Nunavut',
                'ON' => 'Ontario', 'PE' => 'Prince Edward Island', 'QC' => 'Quebec',
                'SK' => 'Saskatchewan', 'YT' => 'Yukon',
            ],
            'AU' => [
                'ACT' => 'Australian Capital Territory', 'NSW' => 'New South Wales',
                'NT' => 'Northern Territory', 'QLD' => 'Queensland', 'SA' => 'South Australia',
                'TAS' => 'Tasmania', 'VIC' => 'Victoria', 'WA' => 'Western Australia',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function forCountry(?string $country): array
    {
        return static::all()[$country] ?? [];
    }

    public static function hasZones(?string $country): bool
    {
        return isset(static::all()[$country]);
    }
}
