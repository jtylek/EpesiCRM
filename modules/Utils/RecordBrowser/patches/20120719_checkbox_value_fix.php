<?php

$tables = DB::GetAssoc('SELECT tab, tab FROM recordbrowser_table_properties');

foreach ($tables as $tab) {
	$fields = DB::GetAssoc('SELECT field, field FROM '.$tab.'_field WHERE type=%s', array('checkbox'));
	foreach ($fields as $f) {
		$f = Utils_RecordBrowserCommon::get_field_id($f);
		DB::Execute('UPDATE '.$tab.'_data_1 SET f_'.$f.'=NULL WHERE f_'.$f.'=0');
	}
}

?>
