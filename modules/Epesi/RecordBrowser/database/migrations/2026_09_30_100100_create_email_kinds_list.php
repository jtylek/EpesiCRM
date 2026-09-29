<?php

use Epesi\Modules\CommonData\CommonDataRepository;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The kinds an e-mail address can be (`Email_Kinds`), in order.
 *
 * As Address_Kinds/Phone_Kinds: only a list that is missing, and readonly, so
 * the defaults stay and an administrator can add more. The query builder,
 * not the CommonData facade: on a fresh install this runs before CommonData's
 * classes are loaded.
 */
return new class extends Migration
{
    /** Keys and labels, in their order. The labels are translated when shown. */
    private const ITEMS = [
        'work' => 'Work',
        'private' => 'Private',
        'other' => 'Other',
    ];

    public function up(): void
    {
        $table = DB::table('common_data');

        if ($table->clone()->where('path', 'Email_Kinds')->exists()) {
            return;
        }

        $now = now();
        $parentId = $table->clone()->insertGetId([
            'parent_id' => null,
            'key' => 'Email_Kinds',
            'value' => null,
            'path' => 'Email_Kinds',
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
                'path' => 'Email_Kinds/'.$key,
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

    /** Nothing to undo: administrators may have added entries, and items use them. */
    public function down(): void {}
};
