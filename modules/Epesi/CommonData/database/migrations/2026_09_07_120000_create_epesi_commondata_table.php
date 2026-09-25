<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared reference data — the port of Epesi's `utils_commondata_tree`.
 *
 * An adjacency list, like Epesi's, plus a materialised `path` ("Countries/US/PA").
 * The path is what makes this table behave: Epesi looks a name up by walking it
 * one SELECT per segment, and cannot put a unique index on (parent_id, key)
 * without its `parent_id = -1` root sentinel, because MySQL treats NULLs in a
 * unique index as distinct and would let two root-level `Countries` arrays
 * coexist. A unique `path` fixes both, and makes subtree queries a LIKE away.
 *
 * The cost is that renaming or moving a node rewrites its descendants' paths;
 * CommonDataNode does that in one statement on save.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('common_data', function (Blueprint $table) {
            $table->id();

            // Root entries have no parent. Epesi stores -1 for these.
            $table->foreignId('parent_id')->nullable()->constrained('common_data')->cascadeOnDelete();

            // Epesi's `akey`, renamed: Laravel quotes every identifier it
            // generates, so the fact that KEY is reserved in MySQL doesn't
            // reach us. Never contains "/" — that is the path separator, and
            // CommonDataNode rejects it.
            $table->string('key', 64);

            // Nullable because a node that only groups its children (Countries,
            // CRM) has no value of its own.
            $table->text('value')->nullable();

            $table->string('path', 512);

            // Set by a module or seeder rather than typed in by an
            // administrator: the GUI won't edit or delete it. Epesi's readonly
            // flag, and the same intent — `CRM/Priority` exists because code
            // reads it back by key.
            $table->boolean('readonly')->default(false);

            // Position among siblings, 1-based like Epesi's.
            $table->integer('position')->default(0);

            $table->timestamps();

            $table->unique('path');
            $table->index(['parent_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('common_data');
    }
};
