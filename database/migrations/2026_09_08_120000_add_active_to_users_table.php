<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Epesi never deletes a user login, only disables it — legacy_id-based
 * reimports, records' created_by, and login_audits.user_id all stay valid
 * either way, but deleting would orphan a super_admin's own audit trail and
 * remove the account before anyone decided it should be gone. This is the
 * same enable/disable shape as Module::enabled, not a SoftDeletes-style
 * deleted_at — the row is never excluded from normal queries, only login.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('active');
        });
    }
};
