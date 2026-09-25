<?php

use App\Models\StoredFile;
use App\Services\FileStorage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Archived attachments move into the file storage (App\Services\FileStorage):
 * the same PDF on ten messages is then on disk once. `path` on the private
 * `local` disk becomes `stored_file_id`. An attachment already missing from
 * disk could not be downloaded before either and is left without a file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_mail_attachments', function (Blueprint $table) {
            $table->foreignId('stored_file_id')->nullable()->after('inline')->constrained('stored_files')->restrictOnDelete();
        });

        $local = Storage::disk('local');
        $moved = [];

        DB::transaction(function () use ($local, &$moved): void {
            DB::table('epesi_mail_attachments')->whereNull('stored_file_id')->eachById(function (object $row) use ($local, &$moved): void {
                if (blank($row->path) || ! $local->exists($row->path)) {
                    return;
                }

                $file = app(FileStorage::class)->putFile($local->path($row->path), $row->name);

                DB::table('epesi_mail_attachments')->where('id', $row->id)->update(['stored_file_id' => $file->getKey()]);
                $moved[] = $row->path;
            });
        });

        Schema::table('epesi_mail_attachments', function (Blueprint $table) {
            $table->dropColumn('path');
        });

        $local->delete($moved);

        // mail/<message id>/ held nothing else, and nothing writes there now.
        if ($local->exists('mail') && $local->allFiles('mail') === []) {
            $local->deleteDirectory('mail');
        }
    }

    public function down(): void
    {
        Schema::table('epesi_mail_attachments', function (Blueprint $table) {
            $table->string('path')->default('')->after('inline');
        });

        $local = Storage::disk('local');

        DB::table('epesi_mail_attachments')->whereNotNull('stored_file_id')->eachById(function (object $row) use ($local): void {
            $file = StoredFile::query()->find($row->stored_file_id);
            $path = '';

            if ($file?->isOnDisk()) {
                $path = 'mail/'.$row->mail_id.'/'.Str::random(8).'-'.$row->name;
                $local->put($path, (string) $file->read());
            }

            DB::table('epesi_mail_attachments')->where('id', $row->id)->update(['path' => $path, 'stored_file_id' => null]);
            $file?->delete();
        });

        Schema::table('epesi_mail_attachments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stored_file_id');
        });
    }
};
