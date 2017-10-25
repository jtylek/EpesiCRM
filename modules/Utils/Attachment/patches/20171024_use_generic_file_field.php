<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

$field = array('name' => _M('Files'),
		'type' => 'file',
		'position' => 'Note',
		'required' => false,
		'extra' => false,
		'visible'=>false,
		'QFfield_callback'=>array('Utils_AttachmentCommon','QFfield_files'),
);

Utils_RecordBrowserCommon::new_record_field('utils_attachment', $field);

$fields = Utils_RecordBrowserCommon::init('utils_attachment');
$field_key = $fields['Files']['pkey'];

$cp = Patch::checkpoint('add_file_values');
if (!$cp->is_done()) {
	$last_id = $cp->get('last_id', 0);
	
	$attachment_ids = DB::GetCol('SELECT id FROM utils_attachment_data_1 WHERE id > %d ORDER BY id ASC', [$last_id]);
	
	foreach ($attachment_ids as $attachment_id) {
		$files = DB::GetCol('SELECT id FROM utils_filestorage WHERE backref=%s', ['rb:utils_attachment/' . $attachment_id]);
		
		if ($files) {	
			DB::Execute('UPDATE utils_filestorage SET backref=%s WHERE id IN (' . implode(',', array_fill(0, count($files), '%d')) . ')', array_merge(['rb:utils_attachment/' . $attachment_id . '/' . $field_key], array_values($files)));
			
			$files = Utils_RecordBrowserCommon::encode_multi($files);
			
			DB::Execute('UPDATE utils_attachment_data_1 SET f_files=%s WHERE id=%d', [$files, $attachment_id]);
		}
		
		$cp->set('last_id', $attachment_id);
	}
	$cp->done();
}

DB::DropTable('utils_attachment_download');
DB::DropTable('utils_attachment_file');
DB::DropTable('utils_attachment_clipboard');
