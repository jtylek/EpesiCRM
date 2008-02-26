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
		$result = DB::GetRow('SELECT \'blue\' as color,start,end,project_id,id,0 as timeless FROM crm_projectplanner_work WHERE id=%d',array($id));
		self::add_info($result);
		return $result;
	}
	public static function get_all($start,$end,$order='') {
		$ret = DB::GetAll('SELECT \'blue\' as color,start,end,project_id,id,0 as timeless FROM crm_projectplanner_work WHERE ((start>=%T AND start<%T) AND employee_id=%d)',array($start,$end,self::$employee));
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
		$proj_info = Apps_ProjectsCommon::get_project($v['project_id']);
		$v['title'] = $proj_info['project_name'];
		$v['description'] = 'Address 1: '.$proj_info['address_1'].'<br>Address 2: '.$proj_info['address_2'].'<br>City: '.$proj_info['city'];
		$v['additional_info'] = $v['additional_info2'] = '';
		$v['end'] = strtotime($v['end']);
		$v['start'] = strtotime($v['start']);
		$v['duration'] = $v['end'] - $v['start'];
		if(date('G:i',strtotime($v['start']))==$sd && date('G:i',strtotime($v['end']))==$ed) {
			$v['timeless'] = 1;
			$v['timeless_key'] = 'allday';
		}
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
