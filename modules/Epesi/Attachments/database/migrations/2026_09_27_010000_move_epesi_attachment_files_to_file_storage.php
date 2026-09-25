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
 * A note's files move into the file storage (App\Services\FileStorage), which
 * keeps a content once however many notes and e-mails carry it: `files`
 * changes from paths on the private `local` disk to StoredFile ids, and
 * `file_names` goes, the name being the StoredFile's. A file already missing
 * from disk could not be downloaded before either, and is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        $local = Storage::disk('local');
        $moved = [];

        DB::transaction(function () use ($local, &$moved): void {
            DB::table('epesi_attachments')->whereNotNull('files')->eachById(function (object $note) use ($local, &$moved): void {
                $names = json_decode((string) $note->file_names, true) ?: [];
                $ids = [];

                foreach (json_decode((string) $note->files, true) ?: [] as $path) {
                    // Already an id: a run that stopped after this note.
                    if (is_int($path) || ctype_digit((string) $path)) {
                        $ids[] = (string) $path;

                        continue;
                    }

                    if (! is_string($path) || ! $local->exists($path)) {
                        continue;
                    }

                    $file = app(FileStorage::class)->putFile($local->path($path), $names[$path] ?? basename($path));
                    $file->forceFill(['created_by' => $note->created_by])->save();

                    $ids[] = (string) $file->getKey();
                    $moved[] = $path;
                }

                DB::table('epesi_attachments')->where('id', $note->id)->update(['files' => json_encode($ids)]);
            });
        });

        Schema::table('epesi_attachments', function (Blueprint $table) {
            $table->dropColumn('file_names');
        });

        $local->delete($moved);

        // Where the upload field used to put them; nothing writes there now.
        if ($local->exists('attachments') && $local->allFiles('attachments') === []) {
            $local->deleteDirectory('attachments');
        }
    }

    public function down(): void
    {
        Schema::table('epesi_attachments', function (Blueprint $table) {
            $table->json('file_names')->nullable()->after('files');
        });

        $local = Storage::disk('local');

        DB::table('epesi_attachments')->whereNotNull('files')->eachById(function (object $note) use ($local): void {
            $paths = [];
            $names = [];

            foreach (json_decode((string) $note->files, true) ?: [] as $id) {
                $file = StoredFile::query()->find($id);

                if ($file === null || ! $file->isOnDisk()) {
                    continue;
                }

                $path = 'attachments/'.Str::ulid().'.'.pathinfo($file->name, PATHINFO_EXTENSION);
                $local->put($path, (string) $file->read());
                $paths[] = $path;
                $names[$path] = $file->name;

                $file->delete();
            }

            DB::table('epesi_attachments')->where('id', $note->id)->update([
                'files' => json_encode($paths),
                'file_names' => json_encode($names),
            ]);
        });
    }
};
