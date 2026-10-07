<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_attachments', function (Blueprint $table): void {
            $table->boolean('legacy_encrypted')->default(false);
            $table->string('legacy_password_hint')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('epesi_attachments', function (Blueprint $table): void {
            $table->dropColumn(['legacy_encrypted', 'legacy_password_hint']);
        });
    }
};
