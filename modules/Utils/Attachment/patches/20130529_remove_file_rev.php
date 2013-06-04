<?php

if (array_key_exists(strtoupper('revision'),DB::MetaColumnNames('utils_attachment_file'))) {
    @DB::Execute('ALTER TABLE utils_attachment_file DROP INDEX utils_attachment_file__revision__idx');
    @DB::Execute('alter table utils_attachment_file drop foreign key utils_attachment_file_ibfk_2');
    @DB::Execute('alter table utils_attachment_file drop index attach_id');
	PatchUtil::db_drop_column('utils_attachment_file','revision');
}

?>
