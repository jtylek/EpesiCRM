<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Small per-screen choices (the dashboard's tab, the mailbox's folder…) beside the lists' state. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_ui_states', function (Blueprint $table) {
            $table->json('screens')->nullable()->after('tables');
        });
    }

    public function down(): void
    {
        Schema::table('user_ui_states', function (Blueprint $table) {
            $table->dropColumn('screens');
        });
    }
};
