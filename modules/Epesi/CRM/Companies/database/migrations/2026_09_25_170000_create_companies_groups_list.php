<?php

use Epesi\Modules\CommonData\CommonDataRepository;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The list behind a company's Group field (`Companies_Groups`), with Epesi's
 * default entries: CRM_ContactsInstall::install() created it with
 * new_array(…, true, true). Without it the Group picker offers nothing and a
 * company shows its raw keys ("customer").
 *
 * Only when the list is missing: one brought over by import:legacy, or built
 * by an administrator, is left as it is. Readonly like Epesi's, so the
 * defaults can't be deleted; an administrator can still add groups to it.
 *
 * The query builder, not the CommonData facade: on a fresh install this runs
 * before CommonData's classes are loaded (ModuleLoader loads every module once
 * all of them are registered).
 */
return new class extends Migration
{
    private const LIST = 'Companies_Groups';

    /** Epesi's keys, labels and order. The labels are translated when shown. */
    private const ITEMS = [
        'customer' => 'Customer',
        'vendor' => 'Vendor',
        'other' => 'Other',
        'manager' => 'Manager',
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

        // Reads are cached, and an install that showed a company before this
        // ran has the list cached as empty.
        if (class_exists(CommonDataRepository::class)) {
            CommonDataRepository::invalidate();
        }
    }

    /** Nothing to undo: administrators may have added groups, and companies use them. */
    public function down(): void {}
};
