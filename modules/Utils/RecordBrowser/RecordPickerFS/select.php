<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage RecordBrowser-RecordPicker
 */
if(!isset($_POST['select']) || !isset($_POST['row']) || !is_numeric($_POST['row']) || !isset($_POST['path']) || !isset($_POST['cid']))
	die('alert(\'Invalid request\')');

define('JS_OUTPUT',1);
define('CID',$_POST['cid']); 
require_once('../../../../include.php');
foreach ($_POST as $k=>$v)
	$_POST[$k] = trim($v,'"');
$path = $_POST['path'];
$tab = Module::static_get_module_variable($path, 'tab', null);
$crits = Module::static_get_module_variable($path, 'crits_stuff', null);
$rp_path = Module::static_get_module_variable($path, 'rp_fs_path', null);
$selected = & Module::static_get_module_variable($rp_path, 'selected', array());
ModuleManager::load_modules();
if ($tab===null || $crits===null || $rp_path===null) die('alert(\'Invalid usage - variables not set (path - '.$path.', module vars - '.epesi::escapeJS(print_r($_SESSION['client']['__module_vars__'][$path],true)).')\');');

$record = Utils_RecordBrowserCommon::get_record($tab, $_POST['row']);
if(is_array($record)) {
	if($_POST['select'] && $_POST['select']!='false')
		$selected[$_POST['row']] = 1;
	else
		unset($selected[$_POST['row']]);
}
session_commit();

?>