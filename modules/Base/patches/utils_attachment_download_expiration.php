<?php
if (ModuleManager::is_installed('Utils_Attachment')==-1) return;

PatchUtil::db_add_column('utils_attachment_download','expires_on','T');

$ret = DB::Execute('SELECT * FROM utils_attachment_download');
while ($row = $ret->FetchRow()) {
	if (!is_numeric($row['created_on'])) $row['created_on'] = strtotime($row['created_on']);
	DB::Execute('UPDATE utils_attachment_download SET expires_on=%T WHERE id=%s', array($row['created_on']+3600*24*7, $row['id']));
}

?>