<?php

use Epesi\Modules\CommonData\CommonDataRepository;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds GitHub to `Online_Account_Kinds` (see
 * `2026_09_28_150200_create_phone_and_online_account_lists.php`), after an
 * install may already have the list without it. Only when the entry is
 * missing, and readonly like the rest of the list, so an administrator's own
 * "GitHub" entry (however unlikely) is left alone.
 *
 * The query builder, not the CommonData facade: on a fresh install this runs
 * before CommonData's classes are loaded.
 */
return new class extends Migration
{
    private const LIST = 'Online_Account_Kinds';

    private const KEY = 'github';

    private const LABEL = 'GitHub';

    public function up(): void
    {
        $table = DB::table('common_data');
        $parentId = $table->clone()->where('path', self::LIST)->value('id');

        // The list doesn't exist yet (a fresh install runs both migrations
        // in order, so create_phone_and_online_account_lists() already has it).
        if ($parentId === null) {
            return;
        }

        if ($table->clone()->where('parent_id', $parentId)->where('key', self::KEY)->exists()) {
            return;
        }

        $now = now();
        $position = (int) $table->clone()->where('parent_id', $parentId)->max('position') + 1;

        $table->clone()->insert([
            'parent_id' => $parentId,
            'key' => self::KEY,
            'value' => self::LABEL,
            'path' => self::LIST.'/'.self::KEY,
            'readonly' => true,
            'position' => $position,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (class_exists(CommonDataRepository::class)) {
            CommonDataRepository::invalidate();
        }
    }

    /** Nothing to undo: an administrator may already have a record using it. */
    public function down(): void {}
};
