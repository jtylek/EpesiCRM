<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage planner
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_PlannerCommon extends ModuleCommon {
	public static function user_settings(){
		return array(__('Planners')=>array(
//			array('name'=>'per_page','label'=>'Records per page','type'=>'select','values'=>array(5=>5,10=>10,20=>20,50=>50,100=>100),'default'=>20),
//			array('name'=>'actions_position','label'=>'Position of \'Actions\' column','type'=>'radio','values'=>array(0=>'Left',1=>'Right'),'default'=>0),
//			array('name'=>'adv_search','label'=>'Advanced search by default','type'=>'bool','default'=>0),
//			array('name'=>'adv_history','label'=>'Advanced order history','type'=>'bool','default'=>0),
//			array('name'=>'display_no_records_message','label'=>'Hide \'No records found\' message','type'=>'bool','default'=>0),
//			array('name'=>'show_all_button','label'=>'Display \'Show all\' button','type'=>'bool','default'=>0)
			));
	}
	
	public static function get_resource_value($resource) {
		return $_SESSION['client']['utils_planner']['resources'][$resource]['value'];
	}
	
	public static function format_time($time) {
		static $base_unix_time = null;
		if ($base_unix_time===null) $base_unix_time = strtotime('1970-01-01 00:00');
		return Base_RegionalSettingsCommon::time2reg($base_unix_time+$time,'without_seconds',false,false);
	}
	
	public static function get_current_week() {
		$day = $_SESSION['client']['utils_planner']['date'];
		$days = array();
		while(count($days)<7) {
			$days[] = date('Y-m-d',$day);
			$day = strtotime('+1 day', $day);
		}
		return $days;
	}

	public static function resource_changed($resource, $value) {
		$js = '';
		$racc = 	$_SESSION['client']['utils_planner']['timeframe_availability_check_callback'];
		$in_use = 	$_SESSION['client']['utils_planner']['resources'][$resource]['in_use'];
		$grid = 	$_SESSION['client']['utils_planner']['grid']['timetable'];
		$new_in_use = array();
		$busy_times = call_user_func($racc, $resource, $value);
		foreach ($busy_times as $v) {
			if (!is_numeric($v['day'])) $v['day'] = strtotime($v['day']);
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
				foreach($_SESSION['client']['utils_planner']['resources'] as $g=>$r) 
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

	public static function timeframe_changed($timeframes) {
		foreach ($timeframes as $k=>$v) {
			list($day, $s, $e) = explode('::', $v);
			$timeframes[$k] = array('day'=>$day, 'start'=>$s*60, 'end'=>$e*60);
		}
		$js = '';
		$racc = $_SESSION['client']['utils_planner']['resource_availability_check_callback'];
		$result = call_user_func($racc, $timeframes);
		foreach ($result as $elem=>$values) {
			$next = '';
			$next = 'var conflicting = new Array();';
			foreach ($values as $v)
				if ($v!='') $next .= 'conflicting['.$v.']='.$v.';';
			$next .='i=0;'.
					'e=$("'.$elem.'");'.
					'while(i<e.options.length){'.
						'o=e.options[i];'.
						'o.className=conflicting[o.value]?"conflict":"noconflict";'.
						'i++;'.
					'}'.
					'if(!e.multiple)e.className=e.options[e.selectedIndex].className;';
			$js .= $next;
		}
		return $js;
	}
}

?>
