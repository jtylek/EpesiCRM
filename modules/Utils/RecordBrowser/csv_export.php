<?php
/**
 * Download file
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-libs
 * @subpackage fpdf
 */
if(!isset($_REQUEST['cid']) || !isset($_REQUEST['path']) || !isset($_REQUEST['tab']) || !isset($_REQUEST['admin'])) die('Invalid usage');
$cid = $_REQUEST['cid'];
$tab = $_REQUEST['tab'];
$admin = $_REQUEST['admin'];
$path = $_REQUEST['path'];

define('CID', $cid);
require_once('../../../include.php');
$crits = Module::static_get_module_variable($path, 'crits_stuff', null);
$order = Module::static_get_module_variable($path, 'order_stuff', null);
if ($crits===null || $order===null) die('Invalid usage');
ModuleManager::load_modules();
if (!Base_AclCommon::i_am_admin()) die('Invalid usage');

$tab_info = Utils_RecordBrowserCommon::init($tab);
$records = Utils_RecordBrowserCommon::get_records($tab, $crits, array(), $order, array(), $admin);
session_commit();

header('Content-Type: text/plain');
//header('Content-Length: '.strlen($buffer));
header('Content-disposition: attachement; filename="'.$tab.'_export_'.date('Y_m_d__h_i_s').'.csv"');
if (headers_sent())
    die('Some data has already been output to browser, can\'t send PDF file');
foreach ($tab_info as $v) {
	$cols[] = $v['name'];
}
$f = fopen('php://output','w');
fputcsv($f, $cols);
foreach ($records as $r) {
	$rec = array();
	foreach ($tab_info as $v) {
		$val = str_replace('&nbsp;',' ',strip_tags(preg_replace('/\<[Bb][Rr]\/?\>/',"\n",Utils_RecordBrowserCommon::get_val($tab, $v['name'], $r, null, true, $v))));
		$rec[] = $val;
	}
	fputcsv($f, $rec);
}
?>
