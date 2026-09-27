<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per record on a user's priority list. Which record types can go on
 * a list is discovered from the main panel's own recordsets, then turned on
 * or off per install from Administration → Priority List
 * (epesi_priority_list_settings); which date is shown beside a record is
 * still registered in code, see PriorityList::enableFor().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epesi_priority_list_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->morphs('record');
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['user_id', 'record_type', 'record_id'], 'epesi_priority_list_entries_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_priority_list_entries');
    }
};
