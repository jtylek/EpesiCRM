<?php

$tables = DB::GetAssoc('SELECT tab, tab FROM recordbrowser_table_properties');

foreach ($tables as $tab) {
	Utils_RecordBrowserCommon::add_access($tab, 'print', 'SUPERADMIN');
	Utils_RecordBrowserCommon::add_access($tab, 'export', 'SUPERADMIN');
}

?>
