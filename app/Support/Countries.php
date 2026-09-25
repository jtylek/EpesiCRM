<?php

namespace App\Support;

/**
 * Epesi's Country field draws from Data_Countries, a full CommonData-backed
 * country/zone dataset with per-country states/provinces for the chained
 * Country -> Zone select. Reproducing that full dataset is out of scope for
 * this pass: this is a compact, self-contained list covering common cases,
 * with Zone left as a free-text field instead of a per-country states list.
 */
class Countries
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'US' => 'United States',
            'CA' => 'Canada',
            'MX' => 'Mexico',
            'GB' => 'United Kingdom',
            'IE' => 'Ireland',
            'FR' => 'France',
            'DE' => 'Germany',
            'ES' => 'Spain',
            'PT' => 'Portugal',
            'IT' => 'Italy',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'CH' => 'Switzerland',
            'AT' => 'Austria',
            'PL' => 'Poland',
            'CZ' => 'Czech Republic',
            'SK' => 'Slovakia',
            'HU' => 'Hungary',
            'RO' => 'Romania',
            'BG' => 'Bulgaria',
            'GR' => 'Greece',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'IS' => 'Iceland',
            'UA' => 'Ukraine',
            'RU' => 'Russia',
            'TR' => 'Turkey',
            'IL' => 'Israel',
            'AE' => 'United Arab Emirates',
            'SA' => 'Saudi Arabia',
            'EG' => 'Egypt',
            'ZA' => 'South Africa',
            'NG' => 'Nigeria',
            'KE' => 'Kenya',
            'IN' => 'India',
            'PK' => 'Pakistan',
            'CN' => 'China',
            'JP' => 'Japan',
            'KR' => 'South Korea',
            'SG' => 'Singapore',
            'MY' => 'Malaysia',
            'ID' => 'Indonesia',
            'PH' => 'Philippines',
            'VN' => 'Vietnam',
            'TH' => 'Thailand',
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'BR' => 'Brazil',
            'AR' => 'Argentina',
            'CL' => 'Chile',
            'CO' => 'Colombia',
            'PE' => 'Peru',
        ];
    }
}
