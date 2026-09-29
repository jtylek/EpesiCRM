<?php

use Epesi\Modules\CommonData\CommonDataRepository;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `Countries` (`AddressFields`/`RegionalSettings`' `CommonData::array('Countries')`
 * and `CommonData::array('Countries/'.$country)`), with two different
 * histories to reconcile:
 *
 * - A fresh install has no `Countries` list at all until this migration runs
 *   — nothing else seeds one, so the Country/Zone pickers would be empty.
 * - An install that has run `php artisan import:legacy commondata` already
 *   has a full 242-country `Countries` tree (legacy's own
 *   `Data_Countries`), with zones only for the four countries legacy ever
 *   had them for (US, CA, PL, RO) — using legacy's own bare local codes
 *   (`TX`, `MP`, not `US-TX`/`PL-14`), not ISO 3166-2. That tree wins: it is
 *   richer than any list this port would seed, and rewriting its codes to
 *   ISO is the coding decision left open in Common-data.md, not this
 *   migration's job.
 *
 * So: seed a compact baseline only when `Countries` doesn't exist yet, using
 * the same bare zone codes legacy uses for the four it covers (byte-for-byte,
 * so a `commondata` import run afterwards merges by path instead of
 * colliding with a differently-coded sibling). Either way, top up whichever
 * tree is present with zones for Germany, Italy and France, which legacy
 * never had — matching every other zone list's bare-code style rather than
 * standing out as the one ISO-prefixed set in an otherwise legacy-coded tree.
 */
return new class extends Migration
{
    private const LIST = 'Countries';

    /** ISO 3166-1 alpha-2 code => English name, in their order. */
    private const COUNTRIES = [
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

    /**
     * Country code => [bare local code => English name], in their order.
     * US/CA match legacy's own `Data_Countries` byte-for-byte (including its
     * quirks: Canada's "LB"/"YK" rather than ISO "NL"/"YT" — see
     * Common-data.md). PL matches legacy's GUS voivodeship symbols. AU, DE,
     * IT and FR have no legacy list to match, so they use each country's own
     * official short codes.
     */
    private const ZONES = [
        'US' => [
            'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
            'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
            'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
            'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
            'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
            'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
            'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
            'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
            'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
            'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
            'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
            'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
            'WI' => 'Wisconsin', 'WY' => 'Wyoming', 'DC' => 'District of Columbia',
        ],
        'CA' => [
            'AB' => 'Alberta', 'BC' => 'British Columbia', 'LB' => 'Labrador',
            'MB' => 'Manitoba', 'NB' => 'New Brunswick', 'NS' => 'Nova Scotia',
            'NT' => 'Northwest Territories', 'NU' => 'Nunavut', 'ON' => 'Ontario',
            'PE' => 'Prince Edward Island', 'QC' => 'Quebec', 'SK' => 'Saskatchewan',
            'YK' => 'Yukon',
        ],
        'AU' => [
            'NSW' => 'New South Wales', 'QLD' => 'Queensland', 'SA' => 'South Australia',
            'TAS' => 'Tasmania', 'VIC' => 'Victoria', 'WA' => 'Western Australia',
            'ACT' => 'Australian Capital Territory', 'NT' => 'Northern Territory',
        ],
        'PL' => [
            'DS' => 'Lower Silesian', 'KP' => 'Kuyavian-Pomeranian', 'LB' => 'Lubusz',
            'LD' => 'Łódź', 'LU' => 'Lublin', 'MA' => 'Masovian',
            'MP' => 'Lesser Poland', 'OP' => 'Opole', 'PD' => 'Podlaskie',
            'PK' => 'Subcarpathian', 'PM' => 'Pomeranian', 'SL' => 'Silesian',
            'SW' => 'Świętokrzyskie', 'WM' => 'Warmian-Masurian', 'WP' => 'Greater Poland',
            'ZP' => 'West Pomeranian',
        ],
        'DE' => [
            'BW' => 'Baden-Württemberg', 'BY' => 'Bavaria', 'BE' => 'Berlin',
            'BB' => 'Brandenburg', 'HB' => 'Bremen', 'HH' => 'Hamburg',
            'HE' => 'Hesse', 'MV' => 'Mecklenburg-Western Pomerania', 'NI' => 'Lower Saxony',
            'NW' => 'North Rhine-Westphalia', 'RP' => 'Rhineland-Palatinate', 'SL' => 'Saarland',
            'SN' => 'Saxony', 'ST' => 'Saxony-Anhalt', 'SH' => 'Schleswig-Holstein',
            'TH' => 'Thuringia',
        ],
        'IT' => [
            '65' => 'Abruzzo', '77' => 'Basilicata', '78' => 'Calabria',
            '72' => 'Campania', '45' => 'Emilia-Romagna', '36' => 'Friuli Venezia Giulia',
            '62' => 'Lazio', '42' => 'Liguria', '25' => 'Lombardy',
            '57' => 'Marche', '67' => 'Molise', '21' => 'Piedmont',
            '75' => 'Apulia', '88' => 'Sardinia', '82' => 'Sicily',
            '52' => 'Tuscany', '32' => 'Trentino-South Tyrol', '55' => 'Umbria',
            '23' => 'Aosta Valley', '34' => 'Veneto',
        ],
        'FR' => [
            'ARA' => 'Auvergne-Rhône-Alpes', 'BFC' => 'Bourgogne-Franche-Comté', 'BRE' => 'Brittany',
            'CVL' => 'Centre-Val de Loire', '20R' => 'Corsica', 'GES' => 'Grand Est',
            'HDF' => 'Hauts-de-France', 'IDF' => 'Île-de-France', 'NOR' => 'Normandy',
            'NAQ' => 'Nouvelle-Aquitaine', 'OCC' => 'Occitanie', 'PDL' => 'Pays de la Loire',
            'PAC' => "Provence-Alpes-Côte d'Azur", '971' => 'Guadeloupe', '972' => 'Martinique',
            '973' => 'French Guiana', '974' => 'Réunion', '976' => 'Mayotte',
        ],
    ];

    public function up(): void
    {
        $table = DB::table('common_data');
        $rootId = $table->clone()->where('path', self::LIST)->value('id');

        if ($rootId === null) {
            $this->seedBaseline($table);

            return;
        }

        $this->topUpZones($table, $rootId);
    }

    protected function seedBaseline($table): void
    {
        $now = now();
        $rootId = $table->clone()->insertGetId([
            'parent_id' => null,
            'key' => self::LIST,
            'value' => null,
            'path' => self::LIST,
            'readonly' => true,
            'position' => (int) $table->clone()->whereNull('parent_id')->max('position') + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $countryPosition = 0;

        foreach (self::COUNTRIES as $code => $name) {
            $countryPath = self::LIST.'/'.$code;

            $countryId = $table->clone()->insertGetId([
                'parent_id' => $rootId,
                'key' => $code,
                'value' => $name,
                'path' => $countryPath,
                'readonly' => true,
                'position' => ++$countryPosition,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->insertZones($table, $countryId, $countryPath, self::ZONES[$code] ?? []);
        }

        if (class_exists(CommonDataRepository::class)) {
            CommonDataRepository::invalidate();
        }
    }

    /**
     * A `Countries` tree already exists (almost certainly from
     * `import:legacy commondata`). Only add zones to a country that is
     * present but has none yet — never touch one that already has zones
     * (US/CA/PL/RO, on a legacy-imported tree) or create a country that
     * isn't there at all: that tree's own codes and coverage win.
     */
    protected function topUpZones($table, int $rootId): void
    {
        $changed = false;

        foreach (self::ZONES as $code => $zones) {
            $country = $table->clone()->where('parent_id', $rootId)->where('key', $code)->first();

            if (! $country) {
                continue;
            }

            if ($table->clone()->where('parent_id', $country->id)->exists()) {
                continue;
            }

            $this->insertZones($table, $country->id, self::LIST.'/'.$code, $zones);
            $changed = true;
        }

        if ($changed && class_exists(CommonDataRepository::class)) {
            CommonDataRepository::invalidate();
        }
    }

    /**
     * @param  array<string, string>  $zones
     */
    protected function insertZones($table, int $parentId, string $parentPath, array $zones): void
    {
        $now = now();
        $position = 0;

        foreach ($zones as $zoneCode => $zoneName) {
            $table->clone()->insert([
                'parent_id' => $parentId,
                'key' => $zoneCode,
                'value' => $zoneName,
                'path' => $parentPath.'/'.$zoneCode,
                'readonly' => true,
                'position' => ++$position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** Nothing to undo: administrators may have added countries or zones, and addresses use them. */
    public function down(): void {}
};
