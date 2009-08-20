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
if(!isset($_POST['frames']) || !isset($_POST['cid']))
	die('alert(\'Invalid request\')');

define('JS_OUTPUT',1);
define('CID',$_POST['cid']); 
require_once('../../../include.php');
ModuleManager::load_modules();

$frames = json_decode($_POST['frames']);
asort($frames);

if (!Acl::is_user()) die('Unauthorized access');

$js = '';

$timeframe = array();

$cleanFrames = array();
foreach ($frames as $v) {
	$v = explode('__',$v);
	if (!isset($cleanFrames[$v[0]])) $cleanFrames[$v[0]] = array();
	$cleanFrames[$v[0]][$v[1]] = 1;
}

$headers = $_SESSION['client']['utils_planner']['grid']['days'];

foreach ($cleanFrames as $day=>$v) {
	$start = null;
	foreach ($_SESSION['client']['utils_planner']['grid']['timetable'] as $t) {
		if (isset($v[$t])) {
			if ($start==null) $start = $t;
		} elseif ($start!==null) {
			$dur = ($t-$start);
			$h = floor($dur/60);
			$min = $dur%60;
			$duration = '';
			if ($h) $duration .= $h.'h ';
			if ($min || !$duration) $duration .= $min.'min';
			$next = '<tr>'.
						'<td>'.$headers[$day].'</td>'.
						'<td>'.Utils_PlannerCommon::format_time($start*60).'</td>'.
						'<td>'.Utils_PlannerCommon::format_time($t*60).'</td>'.
						'<td>'.$duration.'</td>'.
					'</tr>';
			$timeframe[$day][] = $next;
			$start = null;		
		}
	}
}

$timeframe_string = '<table class="time_frames">';
$day = $fdow = Utils_PopupCalendarCommon::get_first_day_of_week();
do {
	if (isset($timeframe[$day]))
		foreach($timeframe[$day] as $v)
			$timeframe_string .= $v;
	$day++;
	if ($day==7) $day = 0;
} while ($day!=$fdow); 

$timeframe_string .= '</table>';

$js .= '$("Utils_Planner__time_frames").innerHTML="'.Epesi::escapeJS($timeframe_string).'";';

print($js);
?>
