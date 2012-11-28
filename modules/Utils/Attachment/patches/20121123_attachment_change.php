<?php

if (!array_key_exists(strtoupper('deleted'),DB::MetaColumnNames('utils_attachment_file'))) {
	PatchUtil::db_add_column('utils_attachment_file','deleted','I1 DEFAULT 0');

	$ret = DB::Execute('SELECT uaf.id as id, uaf.original as original, uaf.attach_id as attach_id, uaf.revision as revision, ual.local as local FROM utils_attachment_file uaf LEFT JOIN utils_attachment_link ual ON ual.id=uaf.attach_id ORDER BY revision DESC');
	$max_rev = array();

	$ids = array();
	while ($row = $ret->FetchRow()) {
		if (!isset($max_rev[$row['attach_id']])) $max_rev[$row['attach_id']] = $row['revision'];
		else $ids[] = $row['id'];
		if (!$row['original']) $ids[] = $row['id'];
		$old_filename = DATA_DIR.'/Utils_Attachment/'.$row['local'].'/'.$row['attach_id'].'_'.$row['revision'];
		$new_filename = DATA_DIR.'/Utils_Attachment/'.$row['local'].'/'.$row['id'];
		@rename($old_filename, $new_filename);
	}
	if (!empty($ids))
		DB::Execute('UPDATE utils_attachment_file SET deleted=1 WHERE id IN ('.implode(',',$ids).')');
}

?>
