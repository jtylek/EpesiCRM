<?php
/**
 * WARNING: This is a commercial software
 * Please see the included license.html file for more information
 *
 * Warehouse - Items Orders
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license Commercial
 * @version 1.0
 * @package epesi-premium
 * @subpackage warehouse-items-orders
 */
if(!isset($_POST['resource']) || !isset($_POST['options']) || !isset($_POST['value']) || !isset($_POST['cid']))
	die('alert(\'Invalid request\')');

define('JS_OUTPUT',1);
define('CID',$_POST['cid']); 
require_once('../../../include.php');
ModuleManager::load_modules();

$resource = trim($_POST['resource'], '"');

if (!Acl::is_user()) die('Unauthorized access');

$js = '';
$value = null;
switch ($_SESSION['client']['utils_planner']['resources'][$resource]['type']) {
	case 'automulti': 	$value=json_decode($_POST['options']);
						break;
	case 'select': 	$value=trim($_POST['value'],'"');
					break;
}

$_SESSION['client']['utils_planner']['resources'][$resource]['value'] = $value;

$js .= Utils_PlannerCommon::resource_changed($resource, $value);
if (isset($_SESSION['client']['utils_planner']['resources'][$resource]['chained'])) {
	foreach ($_SESSION['client']['utils_planner']['resources'][$resource]['chained'] as $v)
		$js .= Utils_PlannerCommon::resource_changed($v, $_SESSION['client']['utils_planner']['resources'][$v]['value']);
}

print($js);
?>
