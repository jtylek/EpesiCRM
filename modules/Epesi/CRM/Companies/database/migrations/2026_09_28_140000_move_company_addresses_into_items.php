<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A company's address becomes an item of its Addresses collection (the
 * Address collection type, epesi_recordbrowser_addresses), of kind Business,
 * and the address columns go.
 *
 * Only an address with a street, a city or a postal code moves: legacy Epesi
 * filled Country and Zone in on every new company from the user's regional
 * settings, so those two alone aren't an address anyone entered.
 *
 * History recorded against the old columns stays readable: a key with no
 * field of its own shows under its column's name ("City").
 */
return new class extends Migration
{
    private const COLUMNS = ['address_1', 'address_2', 'city', 'postal_code', 'country', 'zone'];

    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'address_1')) {
            return;
        }

        $now = now();

        DB::table('companies')->orderBy('id')->chunkById(500, function ($companies) use ($now): void {
            $items = [];

            foreach ($companies as $company) {
                $values = [];

                foreach (self::COLUMNS as $column) {
                    $value = trim((string) $company->{$column});
                    $values[$column] = $value === '' ? null : $value;
                }

                if ($values['address_1'] === null && $values['address_2'] === null && $values['city'] === null && $values['postal_code'] === null) {
                    continue;
                }

                $items[] = [
                    'owner_type' => 'company',
                    'owner_id' => $company->id,
                    'field' => 'addresses',
                    'kind' => 'business',
                    'position' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                    ...$values,
                ];
            }

            if ($items !== []) {
                DB::table('epesi_recordbrowser_addresses')->insert($items);
            }
        });

        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn(self::COLUMNS));
    }

    /**
     * The columns back, filled from each company's first Business address,
     * and the companies' addresses gone, so up() can run again. Any other
     * address a company had by then is lost: one set of columns holds one.
     */
    public function down(): void
    {
        if (Schema::hasColumn('companies', 'address_1')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table): void {
            foreach (self::COLUMNS as $column) {
                $table->string($column)->nullable();
            }
        });

        $items = DB::table('epesi_recordbrowser_addresses')
            ->where('owner_type', 'company')
            ->where('field', 'addresses')
            ->where('kind', 'business')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->unique('owner_id');

        foreach ($items as $item) {
            DB::table('companies')->where('id', $item->owner_id)->update(array_map(fn (string $column) => $item->{$column}, array_combine(self::COLUMNS, self::COLUMNS)));
        }

        DB::table('epesi_recordbrowser_addresses')->where('owner_type', 'company')->where('field', 'addresses')->delete();
    }
};
