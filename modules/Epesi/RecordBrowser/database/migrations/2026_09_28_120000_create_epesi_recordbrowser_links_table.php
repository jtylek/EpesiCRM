<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The links of every "link to any record" field (Field::related()) — Epesi's
 * `__RECORDSETS__` select, which stored "task/7" tokens in a delimited string
 * on the record itself. One table for every field of every recordset, keyed by
 * the field's name, so a field an administrator adds needs no DDL.
 *
 * Both ends are morph aliases, which survive a model moving namespace. No
 * foreign keys: the targets are polymorphic, and a link whose target has gone
 * simply stops showing (see HasRecordLinks).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epesi_recordbrowser_links', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 64);
            $table->unsignedBigInteger('source_id');
            $table->string('field', 64);
            $table->string('target_type', 64);
            $table->unsignedBigInteger('target_id');
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'field', 'target_type', 'target_id'], 'epesi_recordbrowser_links_unique');
            $table->index(['target_type', 'target_id'], 'epesi_recordbrowser_links_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_recordbrowser_links');
    }
};
