<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

$columns = DB::MetaColumnNames('utils_filestorage_files');

if (!in_array('type', $columns)) {
    PatchUtil::db_add_column('utils_filestorage_files', 'type', 'C(256)');
}

if (!in_array('size', $columns)) {
    PatchUtil::db_add_column('utils_filestorage_files', 'size', 'I8');
}

$cp = Patch::checkpoint('process');
$id = $cp->get('id', 0);
$sql = 'SELECT f.id, f.hash, s.filename FROM utils_filestorage_files f LEFT JOIN utils_filestorage s ON f.id=s.file_id WHERE f.id>%d LIMIT 50';
$update_sql = 'UPDATE utils_filestorage_files SET size=%d,type=%s WHERE id=%d';
while (($files = DB::GetAll($sql, array($id)))) {
    foreach ($files as $file) {
        $filepath = Utils_FileStorageCommon::get_storage_file_path($file['hash']);
        if (file_exists($filepath)) {
            $size = filesize($filepath);
            $type = Utils_FileStorageCommon::get_mime_type($filepath, $file['filename']);
            DB::Execute($update_sql, array($size, $type, $file['id']));
        }
        $id = $file['id'];
    }
    $cp->set('id', $id);
    Patch::require_time(10);
}
