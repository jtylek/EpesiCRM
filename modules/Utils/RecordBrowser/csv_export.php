<?php
/**
 * Download file
 *
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @version 1.9.0
 * @license MIT
 * @package epesi-utils
 * @subpackage RecordBrowser
 */
if (!isset($_REQUEST['cid']) || !isset($_REQUEST['path']) || !isset($_REQUEST['tab']) || !isset($_REQUEST['admin'])) 
    die('Invalid usage - missing param');
$cid = $_REQUEST['cid'];
$tab = $_REQUEST['tab'];
$admin = $_REQUEST['admin'];
$path = $_REQUEST['path'];
define('CID', $cid);
define('READ_ONLY_SESSION', true);
require_once('../../../include.php');
$crits = Module::static_get_module_variable($path, 'crits_stuff', null);
$order = Module::static_get_module_variable($path, 'order_stuff', null);
if ($crits === null || $order === null) {
    $crits = $order = array();
}
ModuleManager::load_modules();
if (!Utils_RecordBrowserCommon::get_access($tab, 'export') && !Base_AclCommon::i_am_admin()) 
    die('Access denied');
set_time_limit(0);

$csv = new Utils_RecordBrowser_CsvExport($tab, $crits, $order, $admin);

header('Content-Type: text/csv');
//header('Content-Length: '.strlen($buffer));
header('Content-disposition: attachement; filename="' . $tab . '_export_' . date('Y_m_d__H_i_s') . '.csv"');
if (headers_sent()) 
    die('Some data has already been output to browser, can\'t send the file');

$csv->to_output();
