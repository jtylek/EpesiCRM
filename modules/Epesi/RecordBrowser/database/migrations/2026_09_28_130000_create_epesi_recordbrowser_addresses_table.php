<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The items of every address collection (Address, a CollectionItem): a
 * contact's, a company's, any recordset's that declares
 * Field::collection(…, Address::class). The columns every collection table
 * starts with, then the address's own.
 *
 * The owner is a morph alias and an id, with no foreign key, as on the link
 * table. The timestamps are nullable, so MySQL adds no
 * ON UPDATE CURRENT_TIMESTAMP. The owner index is named by hand: Laravel's
 * name for it runs past MySQL's 64 characters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epesi_recordbrowser_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 64);
            $table->unsignedBigInteger('owner_id');
            $table->string('field', 64);
            $table->string('kind', 64)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->string('address_1', 64)->nullable();
            $table->string('address_2', 64)->nullable();
            $table->string('city', 64)->nullable();
            $table->string('postal_code', 64)->nullable();
            $table->string('country', 64)->nullable();
            $table->string('zone', 64)->nullable();

            $table->index(['owner_type', 'owner_id', 'field', 'position'], 'epesi_recordbrowser_addresses_owner_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_recordbrowser_addresses');
    }
};
