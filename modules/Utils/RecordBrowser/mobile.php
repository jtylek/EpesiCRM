<?php
//!!! $caption and $table variables are passed globally
defined("_VALID_ACCESS") || die();

print('<h2>'.$caption.'</h2>');
$cols = Utils_RecordBrowserCommon::init($table);
$cols_out = array();
foreach($cols as $col) {
	if($col['visible'])
		$cols_out[] = array('name'=>$col['name'], 'width'=>1);
}
$data = Utils_RecordBrowserCommon::get_records($table,array(':Fav'=>true));
$data_out = array();
foreach($data as $v) {
	$row = array();
	foreach($cols as $k=>$col) {
		if(!$col['visible']) continue;
		$row[] = Utils_RecordBrowserCommon::get_val($table,$col['name'],$v,$v['id'],false,$col);
	}
	$data_out[] = $row;
}

Utils_GenericBrowserCommon::mobile_table($cols_out,$data_out);

?>