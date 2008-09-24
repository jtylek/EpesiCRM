<?php
//!!! $table,$crits and sort variables are passed globally
defined("_VALID_ACCESS") || die();

//init
$ret = DB::GetRow('SELECT caption, recent, favorites FROM recordbrowser_table_properties WHERE tab=%s',array($table));
$type = isset($_GET['type'])?$_GET['type']:Base_User_SettingsCommon::get('Utils_RecordBrowser',$table.'_default_view');
$order_num = (isset($_GET['order']) && isset($_GET['order_dir']))?$_GET['order']:-1;
$order = false;
print(Base_LangCommon::ts('Utils_RecordBrowser',$ret['caption']).' - '.Base_LangCommon::ts('Utils_RecordBrowser',ucfirst($type)).'<br>');

//TODO: simple search

//cols
$cols = Utils_RecordBrowserCommon::init($table);
$cols_out = array();
foreach($cols as $col) {
	if($col['visible']) {
		if(count($cols_out)==$order_num) $order=$col['id'];
		if($type!='recent')
			$cols_out[] = array('name'=>$col['name'], 'order'=>$col['id'], 'width'=>1);
		else
			$cols_out[] = array('name'=>$col['name'], 'width'=>1);
	}
}

//views
if($ret['recent'] && $type!='recent') print('<a href="mobile.php?'.http_build_query(array_merge($_GET,array('type'=>'recent'))).'">'.Base_LangCommon::ts('Utils_RecordBrowser','Recent').'</a> ');
if($ret['favorites'] && $type!='favorites') print('<a href="mobile.php?'.http_build_query(array_merge($_GET,array('type'=>'favorites'))).'">'.Base_LangCommon::ts('Utils_RecordBrowser','Favorites').'</a> ');
if(($ret['recent'] || $ret['favorites']) && $type!='all') print('<a href="mobile.php?'.http_build_query(array_merge($_GET,array('type'=>'all'))).'">'.Base_LangCommon::ts('Utils_RecordBrowser','All').'</a> ');
//$crits = array();
//$sort = array();
switch($type) {
	case 'favorites':
		$crits[':Fav'] = true;
		break;
	case 'recent':
		$crits[':Recent'] = true;
		$sort = array(':Visited_on' => 'DESC');
		break;
}
if($type!='recent' && $order && ($_GET['order_dir']=='asc' || $_GET['order_dir']=='desc')) {
	$sort = array($order => strtoupper($_GET['order_dir']));
}
$offset = isset($_GET['rb_offset'])?$_GET['rb_offset']:0;
$data = Utils_RecordBrowserCommon::get_records($table,$crits,array(),$sort,array('numrows'=>10,'offset'=>10*$offset));

//parse data
$data_out = array();
foreach($data as $v) {
	$row = array();
	foreach($cols as $k=>$col) {
		if(!$col['visible']) continue;
		$row[] = Utils_RecordBrowserCommon::get_val($table,$col['name'],$v,$v['id'],false,$col);
	}
	$data_out[] = $row;
}

//display table
Utils_GenericBrowserCommon::mobile_table($cols_out,$data_out,false);

//display paging
$num_rows = Utils_RecordBrowserCommon::get_records_limit($table,$crits);
if($offset>0) print('<a href="mobile.php?'.http_build_query(array_merge($_GET,array('rb_offset'=>($offset-1)))).'">'.Base_LangCommon::ts('Utils_RecordBrowser','prev').'</a>');
if($offset<$num_rows/10-1) print(' <a href="mobile.php?'.http_build_query(array_merge($_GET,array('rb_offset'=>($offset+1)))).'">'.Base_LangCommon::ts('Utils_RecordBrowser','next').'</a>');
if($num_rows>10) {
	$qf = new HTML_QuickForm('login', 'get','mobile.php?'.http_build_query($_GET));
	$qf->addElement('text', 'rb_offset', Base_LangCommon::ts('Base_User_Login','Page(0-%d)',array($num_rows/10)));
	$qf->addElement('submit', 'submit_button', Base_LangCommon::ts('Base_User_Login','OK'));
	$qf->addRule('username', Base_LangCommon::ts('Base_User_Login','Field required'), 'required');
	$qf->addRule('page', Base_LangCommon::ts('Base_User_Login','Invalid page number'), 'numeric');
	$qf->display();
}
?>