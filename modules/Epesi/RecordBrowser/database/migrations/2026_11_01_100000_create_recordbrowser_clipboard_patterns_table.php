<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per recordset, only once an administrator has typed an
        // override: a module's default pattern (Clipboard::enableFor()) is
        // code, not a row, so a recordset nobody has touched here still
        // copies what its module declared. Clearing the override later
        // deletes the row, which restores that default.
        Schema::create('epesi_recordbrowser_clipboard_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('model_type', 100)->unique();
            $table->text('pattern');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_recordbrowser_clipboard_patterns');
    }
};
