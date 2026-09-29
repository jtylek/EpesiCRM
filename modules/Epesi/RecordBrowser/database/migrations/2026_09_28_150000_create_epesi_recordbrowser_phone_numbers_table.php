<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The items of every phone-number collection (PhoneNumber, a
 * CollectionItem): the columns every collection table starts with, then the
 * number as typed, its digits alone (indexed, so a number is found however it
 * was spaced) and the messengers that reach it, as JSON keys of the
 * Phone_Messengers list.
 *
 * As on the addresses table: no foreign key on the owner, nullable
 * timestamps, and the owner index named by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epesi_recordbrowser_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 64);
            $table->unsignedBigInteger('owner_id');
            $table->string('field', 64);
            $table->string('kind', 64)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->string('value', 64)->nullable();
            $table->string('digits', 64)->nullable()->index();
            $table->json('messengers')->nullable();

            $table->index(['owner_type', 'owner_id', 'field', 'position'], 'epesi_recordbrowser_phone_numbers_owner_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_recordbrowser_phone_numbers');
    }
};
