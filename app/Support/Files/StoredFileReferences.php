<?php

namespace App\Support\Files;

use Epesi\Modules\RecordBrowser\Files\StoredFileIds;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The StoredFile ids something still refers to; everything else is an orphan. */
class StoredFileReferences
{
    /**
     * Every StoredFile id a note, an e-mail, a record's file field or a
     * record's rich text (stored_file_links) holds.
     * Read straight from the tables, past the models' scopes: a trashed record
     * still holds its files.
     *
     * @return array<int, int>
     */
    public static function ids(): array
    {
        $ids = [];

        if (Schema::hasTable('epesi_attachments')) {
            foreach (DB::table('epesi_attachments')->whereNotNull('files')->pluck('files') as $raw) {
                array_push($ids, ...StoredFileIds::decode($raw));
            }
        }

        // Images pasted into an editor (InlineImages), claimed or not: their
        // own clean-up is filestorage:prune-inline-images.
        if (Schema::hasTable('stored_file_links')) {
            array_push($ids, ...DB::table('stored_file_links')->pluck('stored_file_id')->all());
        }

        if (Schema::hasTable('epesi_mail_attachments')) {
            array_push($ids, ...DB::table('epesi_mail_attachments')->whereNotNull('stored_file_id')->pluck('stored_file_id')->all());
        }

        foreach (Relation::morphMap() as $class) {
            if (! is_subclass_of($class, Model::class) || ! method_exists($class, 'fileColumns')) {
                continue;
            }

            $model = new $class;

            if (! Schema::hasTable($model->getTable())) {
                continue;
            }

            foreach ($model->fileColumns() as $column) {
                if (! Schema::hasColumn($model->getTable(), $column)) {
                    continue;
                }

                foreach (DB::table($model->getTable())->whereNotNull($column)->pluck($column) as $raw) {
                    array_push($ids, ...StoredFileIds::decode($raw));
                }
            }
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
