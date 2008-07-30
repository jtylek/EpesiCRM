<?php
/**
 * Download data
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-libs
 * @subpackage open-flash-chart
 */
if(!isset($_REQUEST['id']) || !isset($_REQUEST['chart'])) die('Invalid usage');
$id = $_REQUEST['id'];
$chart_id = $_REQUEST['chart'];

define('CID', $id);
require_once('../../../include.php');
error_log(print_r($_REQUEST,true),3,'data/logK');

$fn = Module::static_get_module_variable($chart_id,'data',null);
session_commit();

echo $fn;
?>
