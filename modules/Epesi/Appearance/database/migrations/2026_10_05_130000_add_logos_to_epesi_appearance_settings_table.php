<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_appearance_settings', function (Blueprint $table): void {
            // Paths on the "local" disk (branding/…); empty means the built-in epesi logo.
            $table->string('login_logo')->nullable()->after('portal_title');
            $table->string('portal_logo')->nullable()->after('login_logo');
        });
    }

    public function down(): void
    {
        Schema::table('epesi_appearance_settings', function (Blueprint $table): void {
            $table->dropColumn(['login_logo', 'portal_logo']);
        });
    }
};
