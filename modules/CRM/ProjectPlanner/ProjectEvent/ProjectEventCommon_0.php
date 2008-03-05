<?php
/**
 * Example event module
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-calendar-event
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_ProjectPlanner_ProjectEventCommon extends Utils_Calendar_EventCommon {
	public static $project;

	public static function get($id) {
		$result = DB::GetRow('SELECT start,end,employee_id,id,vacations,allday FROM crm_projectplanner_work WHERE id=%d',array($id));
		self::add_info($result);
		return $result;
	}
	public static function get_all($start,$end,$order='') {
		$ret = DB::GetAll('SELECT start,end,employee_id,id,vacations,allday FROM crm_projectplanner_work WHERE ((start>=%T AND start<%T) AND project_id=%d)',array($start,$end,self::$project));
		foreach($ret as &$v) {
			self::add_info($v);
		}
		return $ret;
	}

	private static function add_info(& $v) {
		static $sd,$ed;
		if(!isset($sd)) {
			$sd = Variable::get('CRM_ProjectsPlanner__start_day');
			$ed = Variable::get('CRM_ProjectsPlanner__end_day');
		}
		$v['title'] = '';
		$v['description'] = '';
		$v['custom_row_key'] = 'e'.$v['employee_id'];
		$v['additional_info'] = $v['additional_info2'] = '';
		if($v['allday']) {
			$v['color'] = 'blue';
			$x = strtotime($v['start']);
			$v['start'] = strtotime(date('Y-m-d',$x).' '.$sd);
			$v['end'] = strtotime(date('Y-m-d',$x).' '.$ed);
		} else {
			$v['color'] = 'red';
			$v['end'] = strtotime($v['end']);
			$v['start'] = strtotime($v['start']);
		}
		$v['duration'] = $v['end'] - $v['start'];
		$v['timeless'] = 0;
	}

	public static function delete($id) {
		DB::Execute('DELETE FROM crm_projectplanner_work WHERE id=%d',array($id));
	}

	public static function update($id,$start,$duration,$custom_row_key) {
		if($custom_row_key=='add')
			return false;
			
		$end = $start+$duration;

		DB::Execute('UPDATE crm_projectplanner_work SET employee_id=%d,start=%T,end=%T WHERE id=%d',array(ltrim($custom_row_key,'e'),$start,$end,$id));
		return true;
	}
}

?>
