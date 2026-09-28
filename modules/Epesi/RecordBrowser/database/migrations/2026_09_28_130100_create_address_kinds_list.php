<?php

use Epesi\Modules\CommonData\CommonDataRepository;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The kinds an address can be (`Address_Kinds`, Address::kinds()): Business,
 * Home, Billing, Shipping and Other, in that order. A contact's main address
 * moves in as Business and its home one as Home.
 *
 * Only when the list is missing, and readonly, as Contacts_Groups is: the
 * defaults stay, an administrator can add more ("Warehouse").
 *
 * The query builder, not the CommonData facade: on a fresh install this runs
 * before CommonData's classes are loaded.
 */
return new class extends Migration
{
    private const LIST = 'Address_Kinds';

    /** Keys and labels, in their order. The labels are translated when shown. */
    private const ITEMS = [
        'business' => 'Business',
        'home' => 'Home',
        'billing' => 'Billing',
        'shipping' => 'Shipping',
        'other' => 'Other',
    ];

    public function up(): void
    {
        $table = DB::table('common_data');

        if ($table->clone()->where('path', self::LIST)->exists()) {
            return;
        }

        $now = now();
        $parentId = $table->clone()->insertGetId([
            'parent_id' => null,
            'key' => self::LIST,
            'value' => null,
            'path' => self::LIST,
            'readonly' => true,
            'position' => (int) $table->clone()->whereNull('parent_id')->max('position') + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $position = 0;

        foreach (self::ITEMS as $key => $value) {
            $table->clone()->insert([
                'parent_id' => $parentId,
                'key' => $key,
                'value' => $value,
                'path' => self::LIST.'/'.$key,
                'readonly' => true,
                'position' => ++$position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (class_exists(CommonDataRepository::class)) {
            CommonDataRepository::invalidate();
        }
    }

    /** Nothing to undo: administrators may have added kinds, and addresses use them. */
    public function down(): void {}
};
