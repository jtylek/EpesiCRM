<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_appearance_settings', function (Blueprint $table): void {
            // login_logo / portal_logo are the light-mode pictures; these are the dark-mode ones.
            $table->string('login_logo_dark')->nullable()->after('login_logo');
            $table->string('portal_logo_dark')->nullable()->after('portal_logo');
        });
    }

    public function down(): void
    {
        Schema::table('epesi_appearance_settings', function (Blueprint $table): void {
            $table->dropColumn(['login_logo_dark', 'portal_logo_dark']);
        });
    }
};
