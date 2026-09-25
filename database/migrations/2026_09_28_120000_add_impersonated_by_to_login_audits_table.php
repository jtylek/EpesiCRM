<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who opened a session with "Log in as user" (App\Support\Impersonation):
 * the row's user_id is the account used, this is the super_admin behind it.
 * Epesi's base_login_audit couldn't tell the two apart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_audits', function (Blueprint $table) {
            $table->foreignId('impersonated_by')->nullable()->after('login')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('login_audits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('impersonated_by');
        });
    }
};
