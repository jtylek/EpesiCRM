<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage RecordBrowser-RecordPicker
 */
if(!isset($_POST['select']) || !isset($_POST['path']) || !isset($_POST['cid']))
	die('alert(\'Invalid request\')');

define('JS_OUTPUT',1);
define('CID',$_POST['cid']); 
require_once('../../../../include.php');
foreach ($_POST as $k=>$v)
	$_POST[$k] = trim($v,'"');
$path = $_POST['path'];
$select = json_decode($_POST['select']);
$tab = Module::static_get_module_variable($path, 'tab', null);
$crits = Module::static_get_module_variable($path, 'crits_stuff', null);
$rp_path = Module::static_get_module_variable($path, 'rp_fs_path', null);
$selected = & Module::static_get_module_variable($rp_path, 'selected', array());
ModuleManager::load_modules();
if ($tab===null || $crits===null || $rp_path===null) die('alert(\'Invalid usage - variables not set (path - '.$path.', module vars - '.epesi::escapeJS(print_r($_SESSION['client']['__module_vars__'][$path],true)).')\');');

$tab_info = Utils_RecordBrowserCommon::init($tab);
$records = Utils_RecordBrowserCommon::get_records($tab, $crits, array('id'));
foreach($records as $r) {
	if($select) {
		$selected[$r['id']] = 1;
	} else {
		unset($selected[$r['id']]);
	}
}
session_commit();

print('Epesi.procOn--;_chj(\'\',\'\',\'queue\');');
?>