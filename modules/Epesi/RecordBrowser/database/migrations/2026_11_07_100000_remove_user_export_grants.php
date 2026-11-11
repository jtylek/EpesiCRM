<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Epesi has no per-user permissions, so Export is granted to roles only. A user
 * who had Export through a grant of their own loses it; give it to their role.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('epesi_recordbrowser_export_grants')->where('subject_type', 'user')->delete();
    }

    public function down(): void
    {
        // The removed grants are not restored.
    }
};
