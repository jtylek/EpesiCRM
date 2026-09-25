<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026_09_07_100000_map_morph_types_to_aliases again, for rows written with
 * a class name since: import:legacy replayed history under the model's class
 * (`Epesi\Modules\CRM\Meetings\Models\Meeting`, causer `App\Models\User`)
 * until it was changed to use the morph alias, so every imported record's
 * History tab stayed empty. The importer now clears both spellings on a
 * re-run; this repairs a database without one. Only the activity log: no
 * role or permission row was written that way.
 *
 * Aliases come from Relation::morphMap() at run time, so an enabled module's
 * own are included.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }

        foreach (['subject_type', 'causer_type'] as $column) {
            foreach (Relation::morphMap() as $alias => $class) {
                DB::table('activity_log')->where($column, $class)->update([$column => $alias]);
            }
        }
    }

    /** Nothing to undo: an alias is what every row should say. */
    public function down(): void {}
};
