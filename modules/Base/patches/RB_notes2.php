<?php
if (ModuleManager::is_installed('Utils_RecordBrowser')==-1) return;

$tables = DB::GetAssoc('SELECT tab, tab FROM recordbrowser_table_properties');

$func = serialize(array('Utils_RecordBrowserCommon','create_default_linked_label'));
$ret = DB::Execute('SELECT * FROM utils_attachment_link WHERE func=%s', array(serialize(null)));
while ($row = $ret->FetchRow()) {
	$l = explode('/',$row['local']);
	if (count($l)==2 && isset($tables[$l[0]])) {
		DB::Execute('UPDATE utils_attachment_link SET func=%s, args=%s WHERE id=%d', array($func, serialize($l), $row['id']));
	}
}

?>
