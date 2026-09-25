<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Port of `apps_shoutbox_messages`. A deleted message keeps its row (Epesi's
 * `deleted` flag) so the conversation around it still reads, and admins can
 * still see what was said.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epesi_shoutbox_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message');
            $table->boolean('deleted')->default(false);
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_shoutbox_messages');
    }
};
