<?php
/**
 * Download file
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage RecordBrowser
 */
if(!isset($_REQUEST['cid']) || !isset($_REQUEST['path']) || !isset($_REQUEST['tab']) || !isset($_REQUEST['admin'])) die('Invalid usage - missing param');
$cid = $_REQUEST['cid'];
$tab = $_REQUEST['tab'];
$admin = $_REQUEST['admin'];
$path = $_REQUEST['path'];

define('CID', $cid);
require_once('../../../include.php');
$crits = Module::static_get_module_variable($path, 'crits_stuff', null);
$order = Module::static_get_module_variable($path, 'order_stuff', null);
if ($crits===null || $order===null) {
	$crits = $order = array();
}
ModuleManager::load_modules();
if (!Utils_RecordBrowserCommon::get_access('access_listmanager_history', 'export'))
	die('Invalid usage - access denied');

$tab_info = Utils_RecordBrowserCommon::init($tab);
$records = Utils_RecordBrowserCommon::get_records($tab, $crits, array(), $order, array(), $admin);
session_commit();

header('Content-Type: text/csv');
//header('Content-Length: '.strlen($buffer));
header('Content-disposition: attachement; filename="'.$tab.'_export_'.date('Y_m_d__h_i_s').'.csv"');
if (headers_sent())
    die('Some data has already been output to browser, can\'t send the file');
$cols = array('Record ID');
foreach ($tab_info as $v)
	$cols[] = Base_LangCommon::ts('Utils_RecordBrowser',$v['name']);
$f = fopen('php://output','w');
//fwrite($f, "\xEF\xBB\xBF");
fputcsv($f, $cols);
foreach ($records as $r) {
	$rec = array($r['id']);
	foreach ($tab_info as $v) {
		$val = Utils_RecordBrowserCommon::get_val($tab, $v['name'], $r, null, true, $v);
		$val = htmlspecialchars_decode(strip_tags(preg_replace('/\<[Bb][Rr]\/?\>/',"\n",$val)));
		$rec[] = $val;
	}
	fputcsv($f, $rec);
}
?>
