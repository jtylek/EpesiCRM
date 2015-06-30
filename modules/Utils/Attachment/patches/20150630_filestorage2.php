<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

ModuleManager::install('Utils/FileStorage');
@PatchUtil::db_add_column('utils_attachment_file','filestorage_id', 'I8 NOTNULL');

Patch::set_message('Processing files');
$files_checkpoint = Patch::checkpoint('files');
if(!$files_checkpoint->is_done()) {
    if($files_checkpoint->has('files')) {
        $files = $files_checkpoint->get('files');
    } else {
        $files = 0;
    }
    if($files_checkpoint->has('files_qty')) {
        $files_qty = $files_checkpoint->get('files_qty');
    } else {
        $files_qty = DB::GetOne('SELECT count(*) FROM utils_attachment_file');
        $files_checkpoint->set('files_qty',$files_qty);
    }
    
    while($ret = DB::SelectLimit('SELECT f.id,f.attach_id as aid,f.original FROM utils_attachment_file f ORDER BY f.id',1,$files++)) {
        $row = $ret->FetchRow();
        if(!$row) break;

        Patch::set_message('Processing file: '.$files.'/'.$files_qty);
        $files_checkpoint->require_time(2);

        $fsid = Utils_FileStorageCommon::write_file($row['original'],DATA_DIR.'/Utils_Attachment/'.$row['aid'].'/'.$row['id']);
        unlink(DATA_DIR.'/Utils_Attachment/'.$row['aid'].'/'.$row['id']);
        DB::Execute('UPDATE utils_attachment_file SET filestorage_id=%d WHERE id=%d',array($fsid,$row['id']));

        $files_checkpoint->set('files',$files);
    }
    
    $files_checkpoint->done();
}

DB::Execute('ALTER TABLE utils_attachment_file ADD FOREIGN KEY (filestorage_id) REFERENCES utils_filestorage_files(id)');
