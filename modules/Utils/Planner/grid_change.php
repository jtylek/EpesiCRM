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
define('READ_ONLY_SESSION',1);
define('CID',$_POST['cid']); 
require_once('../../../include.php');
ModuleManager::load_modules();

$frames = json_decode($_POST['frames']);
asort($frames);

if (!Acl::is_user()) die('Unauthorized access');
if (!isset($_SESSION['client']['utils_planner'])) return;

$js = '';

$timeframe = array();

$cleanFrames = array();
foreach ($frames as $v) {
	$v = explode('__',$v);
	if (!isset($cleanFrames[$v[0]])) $cleanFrames[$v[0]] = array();
	$cleanFrames[$v[0]][$v[1]] = 1;
}
$headers = $_SESSION['client']['utils_planner']['grid']['days'];

$selected_frames = array();
$label_h = __('Hours');
$label_h = $label_h[0];
foreach ($cleanFrames as $day=>$v) {
	$start = null;
	foreach ($_SESSION['client']['utils_planner']['grid']['timetable'] as $t) {
		if (isset($v[$t])) {
			if ($start===null) $start = $t;
		} elseif ($start!==null) {
			$dur = ($t-$start);
			$h = floor($dur/60);
			$min = $dur%60;
			$duration = '';
			if ($h) $duration .= $h.$label_h.' ';
			if ($min || !$duration) $duration .= $min.__('min');
			$next = '<tr>'.
						'<td style="width:90px">'.$headers[$day].'</td>'.
						'<td>'.Utils_PlannerCommon::format_time($start*60).'</td>'.
						'<td>'.Utils_PlannerCommon::format_time($t*60).'</td>'.
						'<td>'.$duration.'</td>'.
					'</tr>';
			$selected_frames[] = $day.'::'.$start.'::'.$t;
			$timeframe[$day][] = $next;
			$start = null;		
		}
	}
}

$js .= '$("grid_selected_frames").value="'.implode(';',$selected_frames).'";';

$timeframe_string = '<table class="time_frames">';
if (isset($_SESSION['client']['utils_planner']['date']))
	$day = $_SESSION['client']['utils_planner']['date'];
else
	$day = Utils_PopupCalendarCommon::get_first_day_of_week();
$count = 0;
do {
	if (isset($timeframe[$day]))
		foreach($timeframe[$day] as $v)
			$timeframe_string .= $v;
	if (isset($_SESSION['client']['utils_planner']['date']))
		$day = strtotime('+1 day', $day);
	else {
		$day++;
		if ($day==7) $day = 0;
	}
	$count++;
} while ($count<7); 

$timeframe_string .= '</table>';

$js .= '$("Utils_Planner__time_frames").innerHTML="'.Epesi::escapeJS($timeframe_string).'";';

$js .= Utils_PlannerCommon::timeframe_changed($selected_frames);

print($js);
?>
