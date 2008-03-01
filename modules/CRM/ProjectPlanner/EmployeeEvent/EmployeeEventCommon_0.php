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

class CRM_ProjectPlanner_EmployeeEventCommon extends Utils_Calendar_EventCommon {
	public static $employee;

	public static function get($id) {
		$result = DB::GetRow('SELECT start,end,project_id,id,vacations,allday FROM crm_projectplanner_work WHERE id=%d',array($id));
		self::add_info($result);
		return $result;
	}
	public static function get_all($start,$end,$order='') {
		$ret = DB::GetAll('SELECT start,end,project_id,id,vacations,allday FROM crm_projectplanner_work WHERE ((start>=%T AND start<%T) AND employee_id=%d)',array($start,$end,self::$employee));
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
		if($v['vacations']) {
			$v['title'] = 'vacations';
			$v['description'] = '';
			$v['timeless_key'] = 'vacations';
		} else {
			$proj_info = Apps_ProjectsCommon::get_project($v['project_id']);
			$v['title'] = $proj_info['project_name'];
			$v['description'] = 'Address 1: '.(isset($proj_info['address_1'])?$proj_info['address_1']:'').'<br>Address 2: '.(isset($proj_info['address_2'])?$proj_info['address_2']:'').'<br>City: '.(isset($proj_info['city'])?$proj_info['city']:'');
			$v['timeless_key'] = 'p'.$v['project_id'];
		}
		$v['additional_info'] = $v['additional_info2'] = '';
		if($v['allday']) {
			$v['color'] = 'blue';
			$v['start'] = strtotime(date('Y-m-d',strtotime($v['start'])).' '.$sd);
			$v['end'] = strtotime(date('Y-m-d',strtotime($v['start'])).' '.$ed);
		} else {
			$v['color'] = 'red';
			$v['end'] = strtotime($v['end']);
			$v['start'] = strtotime($v['start']);
		}
		$v['duration'] = $v['end'] - $v['start'];
		$v['timeless'] = 1;
	}

	public static function delete($id) {
		DB::Execute('DELETE FROM crm_projectplanner_work WHERE id=%d',array($id));
	}

	public static function update($id,$start,$duration,$timeless) {
		if($timeless) {
			$start = strtotime(date('Y-m-d',$start).' '.Variable::get('CRM_ProjectsPlanner__start_day'));
			$end = strtotime(date('Y-m-d',$start).' '.Variable::get('CRM_ProjectsPlanner__end_day'));
		} else {
			$end = $start+$duration;
		}
		DB::Execute('UPDATE crm_projectplanner_work SET start=%T,end=%T WHERE id=%d',array($start,$end,$id));
	}
}

?>
