<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy Epesi filled Zone in on every new record from the user's regional
 * settings, so imported addresses in India or Germany carry a Polish `MP`,
 * and a few a stray `PA`. They read as a real zone on a printed or copied
 * address (AI-shared/recordbrowser/clipboard.md).
 *
 * Only those two codes are cleared, and only where the address's own country
 * has no such zone: `PA` stays on a Pennsylvania address and `MP` on a
 * Małopolskie one. "Not in the country's list" alone would be too wide, since
 * Germany, Italy and France gained their lists after Legacy, and a typed zone
 * there is still a good one.
 */
return new class extends Migration
{
    private const TABLE = 'epesi_recordbrowser_addresses';

    private const ZONES = ['MP', 'PA'];

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasTable('common_data')) {
            return;
        }

        foreach (self::ZONES as $zone) {
            $countries = DB::table('common_data')
                ->where('path', 'like', "Countries/%/{$zone}")
                ->pluck('path')
                ->map(fn (string $path): string => explode('/', $path)[1])
                ->all();

            DB::table(self::TABLE)
                ->where('zone', $zone)
                ->where(fn ($query) => $query->whereNull('country')->orWhereNotIn('country', $countries))
                ->update(['zone' => null]);
        }
    }

    /** What was cleared can't be told from a zone typed on purpose, so there is nothing to put back. */
    public function down(): void {}
};
