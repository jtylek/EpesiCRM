<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A contact's two addresses become items of its Addresses collection (the
 * Address collection type, epesi_recordbrowser_addresses): the main one of
 * kind Business, first, and the `home_` one of kind Home. Then the twelve
 * address columns go; Home Phone stays until phone numbers become a
 * collection too.
 *
 * Only an address with a street, a city or a postal code moves: legacy Epesi
 * filled Country and Zone in on every new contact, home address included,
 * from the user's regional settings, so those two alone aren't an address
 * anyone entered.
 *
 * History recorded against the old columns stays readable: a key with no
 * field of its own shows under its column's name ("Home City").
 */
return new class extends Migration
{
    private const FIELDS = ['address_1', 'address_2', 'city', 'postal_code', 'country', 'zone'];

    /** Column prefix => the kind its address moves in as, in order. */
    private const ADDRESSES = ['' => 'business', 'home_' => 'home'];

    public function up(): void
    {
        if (! Schema::hasColumn('contacts', 'address_1')) {
            return;
        }

        $now = now();

        DB::table('contacts')->orderBy('id')->chunkById(500, function ($contacts) use ($now): void {
            $items = [];

            foreach ($contacts as $contact) {
                $position = 0;

                foreach (self::ADDRESSES as $prefix => $kind) {
                    $values = [];

                    foreach (self::FIELDS as $field) {
                        $value = trim((string) $contact->{$prefix.$field});
                        $values[$field] = $value === '' ? null : $value;
                    }

                    if ($values['address_1'] === null && $values['address_2'] === null && $values['city'] === null && $values['postal_code'] === null) {
                        continue;
                    }

                    $items[] = [
                        'owner_type' => 'contact',
                        'owner_id' => $contact->id,
                        'field' => 'addresses',
                        'kind' => $kind,
                        'position' => ++$position,
                        'created_at' => $now,
                        'updated_at' => $now,
                        ...$values,
                    ];
                }
            }

            if ($items !== []) {
                DB::table('epesi_recordbrowser_addresses')->insert($items);
            }
        });

        Schema::table('contacts', fn (Blueprint $table) => $table->dropColumn($this->columns()));
    }

    /**
     * The columns back, each filled from the contact's first address of its
     * kind, and the contacts' addresses gone, so up() can run again. Any
     * other address a contact had by then is lost: the columns hold two.
     */
    public function down(): void
    {
        if (Schema::hasColumn('contacts', 'address_1')) {
            return;
        }

        Schema::table('contacts', function (Blueprint $table): void {
            foreach ($this->columns() as $column) {
                $table->string($column)->nullable();
            }
        });

        foreach (self::ADDRESSES as $prefix => $kind) {
            $items = DB::table('epesi_recordbrowser_addresses')
                ->where('owner_type', 'contact')
                ->where('field', 'addresses')
                ->where('kind', $kind)
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->unique('owner_id');

            foreach ($items as $item) {
                $values = [];

                foreach (self::FIELDS as $field) {
                    $values[$prefix.$field] = $item->{$field};
                }

                DB::table('contacts')->where('id', $item->owner_id)->update($values);
            }
        }

        DB::table('epesi_recordbrowser_addresses')->where('owner_type', 'contact')->where('field', 'addresses')->delete();
    }

    /**
     * @return list<string>
     */
    private function columns(): array
    {
        $columns = [];

        foreach (array_keys(self::ADDRESSES) as $prefix) {
            foreach (self::FIELDS as $field) {
                $columns[] = $prefix.$field;
            }
        }

        return $columns;
    }
};
