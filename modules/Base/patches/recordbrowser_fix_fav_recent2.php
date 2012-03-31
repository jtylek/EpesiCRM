<?php
if (ModuleManager::is_installed('Utils_RecordBrowser')==-1) return;

$tables = DB::GetAssoc('SELECT tab, tab FROM recordbrowser_table_properties');
foreach ($tables as $t) {
	// incompatible with Postgresql, but PGSQL doesn't need those columns
	@DB::Execute('ALTER TABLE '.$t.'_favorite DROP COLUMN id');
	@DB::Execute('ALTER TABLE '.$t.'_recent DROP COLUMN id');
	@DB::Execute('ALTER TABLE '.$t.'_favorite ADD fav_id INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY');
	@DB::Execute('ALTER TABLE '.$t.'_recent ADD recent_id INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY');
}

?>