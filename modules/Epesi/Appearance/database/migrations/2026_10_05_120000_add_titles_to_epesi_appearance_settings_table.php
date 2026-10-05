<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_appearance_settings', function (Blueprint $table): void {
            // Empty means the shipped defaults: "epesi" and "Customer Portal".
            $table->string('login_title')->nullable()->after('app_name');
            $table->string('portal_title')->nullable()->after('login_title');
        });
    }

    public function down(): void
    {
        Schema::table('epesi_appearance_settings', function (Blueprint $table): void {
            $table->dropColumn(['login_title', 'portal_title']);
        });
    }
};
