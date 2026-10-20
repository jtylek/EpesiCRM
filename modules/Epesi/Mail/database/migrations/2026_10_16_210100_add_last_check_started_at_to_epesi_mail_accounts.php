<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_mail_accounts', function (Blueprint $table): void {
            $table->timestamp('last_check_started_at')->nullable()->after('last_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('epesi_mail_accounts', function (Blueprint $table): void {
            $table->dropColumn('last_check_started_at');
        });
    }
};
