<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Port of the `utils_attachment` recordset. Epesi's "Attached to" multiselect
 * (one note linked to several records, which is how Follow-up leaves the same
 * trail on both ends) becomes a polymorphic pivot instead of a delimited
 * `__RECORDSETS__` string. Files were first stored as a JSON list of paths on
 * the private disk with their names alongside; since 2026_09_27_010000 they
 * are StoredFile ids in the shared file storage (App\Services\FileStorage).
 *
 * Not ported: `crypted` (per-note password encryption) — see the module
 * README section in the port notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epesi_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->longText('note')->nullable();
            $table->json('files')->nullable();
            $table->json('file_names')->nullable();
            $table->unsignedTinyInteger('permission')->default(0);
            $table->boolean('sticky')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('epesi_attachment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attachment_id')->constrained('epesi_attachments')->cascadeOnDelete();
            $table->morphs('attachable');
            $table->timestamps();

            $table->unique(['attachment_id', 'attachable_type', 'attachable_id'], 'epesi_attachment_links_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_attachment_links');
        Schema::dropIfExists('epesi_attachments');
    }
};
