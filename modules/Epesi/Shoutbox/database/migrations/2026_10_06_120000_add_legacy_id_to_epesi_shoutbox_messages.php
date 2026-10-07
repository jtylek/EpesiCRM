<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `import:legacy shoutbox` upserts on the legacy `apps_shoutbox_messages.id`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_shoutbox_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('epesi_shoutbox_messages', function (Blueprint $table) {
            $table->dropUnique(['legacy_id']);
            $table->dropColumn('legacy_id');
        });
    }
};
