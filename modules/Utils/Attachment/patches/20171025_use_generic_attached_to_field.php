<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

Utils_RecordBrowserCommon::delete_record_field('utils_attachment', 'Attached to');

$field = array('name' => _M('Attached to'),
		'type' => 'multiselect',
		'position' => 'Crypted',
		'param' => '__RECORDSETS__::;',
		'required' => false,
		'extra' => false,
		'visible'=>false,
);

Utils_RecordBrowserCommon::new_record_field('utils_attachment', $field);

$cp = Patch::checkpoint('add_attached_to_values');
if (!$cp->is_done()) {
	$last_id = $cp->get('last_id', 0);
	
	$attachment_ids = DB::GetCol('SELECT id FROM utils_attachment_data_1 WHERE id > %d ORDER BY id ASC', [$last_id]);
	
	foreach ($attachment_ids as $attachment_id) {
		$attached_to = DB::GetCol('SELECT local FROM utils_attachment_local WHERE attachment=%d', [$attachment_id]);
		
		if ($attached_to) {	
			$attached_to = Utils_RecordBrowserCommon::encode_multi($attached_to);
			
			DB::Execute('UPDATE utils_attachment_data_1 SET f_attached_to=%s WHERE id=%d', [$attached_to, $attachment_id]);
		}
		
		$cp->set('last_id', $attachment_id);
	}
	$cp->done();
}

DB::DropTable('utils_filestorage_local');

