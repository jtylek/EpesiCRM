<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\CommonData\Facades\CommonData;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Legacy's default zone on addresses in other countries: cleared, without
 * touching a zone that belongs to its country or one typed on purpose.
 */
class ClearDefaultZonesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_clears_only_default_codes_the_country_does_not_have(): void
    {
        CommonData::seed('Countries/PL', ['MP' => 'Lesser Poland']);
        CommonData::seed('Countries/US', ['PA' => 'Pennsylvania']);

        $contact = Contact::create(['first_name' => 'Ann', 'last_name' => 'Buyer']);
        $contact->syncCollection('addresses', [
            ['kind' => 'business', 'city' => 'Kraków', 'country' => 'PL', 'zone' => 'MP'],
            ['kind' => 'business', 'city' => 'Philadelphia', 'country' => 'US', 'zone' => 'PA'],
            ['kind' => 'business', 'city' => 'Mumbai', 'country' => 'IN', 'zone' => 'MP'],
            ['kind' => 'business', 'city' => 'Shanghai', 'country' => 'CN', 'zone' => 'PA'],
            ['kind' => 'business', 'city' => 'Austin', 'country' => 'US', 'zone' => 'MP'],
            ['kind' => 'business', 'city' => 'Munich', 'country' => 'DE', 'zone' => 'Bayern'],
            ['kind' => 'business', 'city' => 'Pune', 'country' => 'IN', 'zone' => 'Maharashtra'],
        ]);

        $migration = require base_path('modules/Epesi/RecordBrowser/database/migrations/2026_11_01_120000_clear_default_zones_from_other_countries.php');
        $migration->up();
        // A second run, as on a database pulled over an already migrated one, changes nothing.
        $migration->up();

        $this->assertSame([
            'Kraków' => 'MP',
            'Philadelphia' => 'PA',
            'Mumbai' => null,
            'Shanghai' => null,
            'Austin' => null,
            'Munich' => 'Bayern',
            'Pune' => 'Maharashtra',
        ], DB::table('epesi_recordbrowser_addresses')->orderBy('position')->pluck('zone', 'city')->all());
    }
}
