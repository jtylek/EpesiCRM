<?php
/**
 * Download data
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage openflashchart
 */
if(!isset($_REQUEST['id']) || !isset($_REQUEST['chart'])) die('Invalid usage');
$id = $_REQUEST['id'];
$chart_id = $_REQUEST['chart'];

define('CID', $id);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');

$fn = Module::static_get_module_variable($chart_id,'data',null);

echo $fn;
?>
