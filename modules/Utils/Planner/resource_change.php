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

function utils_planner_resource_changed($resource, $value) {
	$js = '';
	$racc = 	$_SESSION['client']['utils_planner']['resource_availability_check_callback'];
	$in_use = 	$_SESSION['client']['utils_planner']['resources'][$resource]['in_use'];
	$grid = 	$_SESSION['client']['utils_planner']['grid'];
	$new_in_use = array();
	$busy_times = call_user_func($racc, $resource, $value);
	
	foreach ($busy_times as $v) {
		foreach (array('start', 'end') as $w)
			if (!is_numeric($v[$w])) {
				$e = explode(':',$v[$w]);
				$v[$w] = $e[0]*60+$e[1];
			}
		$i = 0;
		while (isset($grid[$i])) {
			if (!isset($grid[$i+1]) || $grid[$i+1]>$v['start']) {
				while (isset($grid[$i]) && $grid[$i]<$v['end']) {
					$new_in_use[$grid[$i].'__'.$v['day']] = true;
					$js .= 'time_grid_change_conflicts('.$grid[$i].','.$v['day'].',1);';
					$i++;
				}
				break;
			}
			$i++;
		}
	}
	
	$_SESSION['client']['utils_planner']['resources'][$resource]['in_use'] = $new_in_use;
	
	foreach ($in_use as $k=>$v) {
		if (!isset($new_in_use[$k])) {
			$should_clear = true;
			foreach($_SESSION['client']['utils_planner']['resources'] as $r) 
				if (isset($r['in_use'][$k])) {
					$should_clear = false;
					break;
				}
			if ($should_clear) {
				$args = explode('__',$k);
				$js .= 'time_grid_change_conflicts('.$args[0].','.$args[1].',0);';
			}
		}
	}
	return $js;
}

$js .= utils_planner_resource_changed($resource, $value);
if (isset($_SESSION['client']['utils_planner']['resources'][$resource]['chained'])) {
	foreach ($_SESSION['client']['utils_planner']['resources'][$resource]['chained'] as $v)
		$js .= utils_planner_resource_changed($v, $_SESSION['client']['utils_planner']['resources'][$v]['value']);
}

print($js);
?>
