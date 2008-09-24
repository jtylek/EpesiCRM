<?php
//!!! $caption and $table variables are passed globally
defined("_VALID_ACCESS") || die();

$cols = Utils_RecordBrowserCommon::init($table);
$cols_out = array();
foreach($cols as $col) {
	if($col['visible'])
		$cols_out[] = array('name'=>$col['name'], 'width'=>1);
}
$crits = array();//array(':Fav'=>true); //TODO: Fav,Recent here
$offset = isset($_GET['rb_offset'])?$_GET['rb_offset']:0;
$data = Utils_RecordBrowserCommon::get_records($table,$crits,array(),array(/*TODO:add order here*/),array('numrows'=>10,'offset'=>10*$offset));
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

$num_rows = Utils_RecordBrowserCommon::get_records_limit($table,$crits);
if($offset>0) print('<a href="mobile.php?rb_offset='.($offset-1).'">'.Base_LangCommon::ts('Utils_RecordBrowser','prev').'</a>');
if($offset<$num_rows/10-1) print(' <a href="mobile.php?rb_offset='.($offset+1).'">'.Base_LangCommon::ts('Utils_RecordBrowser','next').'</a>');
if($num_rows>10) {
	$qf = new HTML_QuickForm('login', 'get','mobile.php?'.http_build_query($_GET));
	$qf->addElement('text', 'rb_offset', Base_LangCommon::ts('Base_User_Login','Page(0-%d)',array($num_rows/10)));
	$qf->addElement('submit', 'submit_button', Base_LangCommon::ts('Base_User_Login','OK'));
	$qf->addRule('username', Base_LangCommon::ts('Base_User_Login','Field required'), 'required');
	$qf->addRule('page', Base_LangCommon::ts('Base_User_Login','Invalid page number'), 'numeric');
	$qf->display();
}
?>