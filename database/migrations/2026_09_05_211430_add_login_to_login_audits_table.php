<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A denormalized snapshot of the user's email at the time of tracking, so a
 * row stays identifiable after its `user_id` goes NULL (nullOnDelete, when
 * the account is later removed) — old Epesi's base_login_audit.user_login_id
 * never faced this problem since it was a plain int, never nulled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_audits', function (Blueprint $table) {
            $table->string('login')->nullable()->after('user_id');
        });

        // A correlated subquery rather than UPDATE ... JOIN, which SQLite
        // (the test database) does not support.
        DB::table('login_audits')
            ->whereNotNull('user_id')
            ->update(['login' => DB::raw('(select email from users where users.id = login_audits.user_id)')]);
    }

    public function down(): void
    {
        Schema::table('login_audits', function (Blueprint $table) {
            $table->dropColumn('login');
        });
    }
};
