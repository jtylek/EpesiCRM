<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The items of every online-account collection (OnlineAccount, a
 * CollectionItem): the columns every collection table starts with, then the
 * handle. The kind is the service, which decides what the handle links to.
 *
 * As on the addresses table: no foreign key on the owner, nullable
 * timestamps, and the owner index named by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epesi_recordbrowser_online_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 64);
            $table->unsignedBigInteger('owner_id');
            $table->string('field', 64);
            $table->string('kind', 64)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->string('value')->nullable();

            $table->index(['owner_type', 'owner_id', 'field', 'position'], 'epesi_recordbrowser_online_accounts_owner_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_recordbrowser_online_accounts');
    }
};
