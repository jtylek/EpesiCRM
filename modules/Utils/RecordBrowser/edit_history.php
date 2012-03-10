<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-premium
 * @subpackage timesheet
 */
if(!isset($_POST['tab']) || !isset($_POST['id']) || !isset($_POST['cid']))
	die('alert(\'Invalid request\')');

define('JS_OUTPUT',1);
define('CID',$_POST['cid']); 
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

$id = $_POST['id'];
$tab = $_POST['tab'];

$now = $_POST['date'];

$created = Utils_RecordBrowserCommon::get_record($tab, $id, true);
$access = Utils_RecordBrowserCommon::get_access($tab, 'view', $created);
$created['created_by_login'] = Base_UserCommon::get_user_login($created['created_by']);
$field_hash = array();
$edited = DB::GetRow('SELECT ul.login, c.edited_on FROM '.$tab.'_edit_history AS c LEFT JOIN user_login AS ul ON ul.id=c.edited_by WHERE c.'.$tab.'_id=%d ORDER BY edited_on DESC',array($id));
Utils_RecordBrowserCommon::init($tab);

$table_rows = Utils_RecordBrowserCommon::$table_rows;

foreach($table_rows as $field => $args)
	$field_hash[$args['id']] = $field;	

$ret = DB::Execute('SELECT ul.login, c.id, c.edited_on, c.edited_by FROM '.$tab.'_edit_history AS c LEFT JOIN user_login AS ul ON ul.id=c.edited_by WHERE c.'.$tab.'_id=%d AND edited_on>%T ORDER BY edited_on DESC, id DESC',array($id, $now));
while ($row = $ret->FetchRow()) {
	$changed = array();
	$ret2 = DB::Execute('SELECT * FROM '.$tab.'_edit_history_data WHERE edit_id=%d',array($row['id']));
	while($row2 = $ret2->FetchRow()) {
		if ($row2['field']!='id' && (!isset($access[$row2['field']]) || !$access[$row2['field']])) continue;
		$changed[$row2['field']] = $row2['old_value'];
		$last_row = $row2;
	}
	foreach($changed as $k=>$v) {
		if ($k!='id') {
			if (!isset($field_hash[$k])) continue;
			if (!isset($table_rows[$field_hash[$k]])) continue;
			if ($table_rows[$field_hash[$k]]['type']=='multiselect') $v = Utils_RecordBrowserCommon::decode_multi($v);
			$created[$k] = $v;
		}
	}
}

foreach($table_rows as $field => $args) {
	ob_start();
	$val = @Utils_RecordBrowserCommon::get_val($tab, $field, $created, false, $args);
	if (!$val)
		$val = Utils_RecordBrowserCommon::get_val($tab, $field, $created, true, $args);
	ob_end_clean();
	print('if($("_'.$args['id'].'__data"))$("_'.$args['id'].'__data").innerHTML = "'.Epesi::escapeJS($val).'";');
//	if (!$access[$args['id']]) continue;
//	if ($created[$args['id']] !== '') $created[$args['id']] = $val; // TRSL
}

?>