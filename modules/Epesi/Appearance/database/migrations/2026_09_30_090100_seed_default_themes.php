<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A starting point, not a placeholder: Epesi Green (this app's brand colour,
 * replacing the Amber panel fallback) as the default, Blue Compact, and
 * Orange Comfortable — one example of each density so an admin sees both
 * without building one from scratch. Only seeded when the table is still
 * empty, so this never overwrites themes an install already authored —
 * including this migration running against a database where Administration
 * -> Themes already has rows of its own.
 */
return new class extends Migration
{
    private const TABLE = 'epesi_appearance_themes';

    public function up(): void
    {
        if (DB::table(self::TABLE)->exists()) {
            return;
        }

        $now = now();

        DB::table(self::TABLE)->insert([
            ['name' => 'Epesi Green', 'accent_color' => '#269c34', 'density' => 'compact', 'is_default' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Blue Compact', 'accent_color' => '#3767db', 'density' => 'compact', 'is_default' => false, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Orange Comfortable', 'accent_color' => '#e08122', 'density' => 'comfortable', 'is_default' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        DB::table(self::TABLE)->whereIn('name', ['Epesi Green', 'Blue Compact', 'Orange Comfortable'])->delete();
    }
};
