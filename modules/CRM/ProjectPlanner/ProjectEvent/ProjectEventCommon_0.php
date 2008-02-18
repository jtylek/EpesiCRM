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
		$result = DB::GetRow('SELECT \'blue\' as color,start,(end-start) as duration,employee_id,id,0 as timeless FROM crm_projectplanner_work WHERE id=%d',array($id));
		self::add_info($result);
		return $result;
	}
	public static function get_all($start,$end,$order='') {
		$ret = DB::GetAll('SELECT \'blue\' as color,start,end,(end-start) as duration,employee_id,id,0 as timeless FROM crm_projectplanner_work WHERE ((start>=%d AND start<%d) AND project_id=%d)',array($start,$end,self::$project));
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
		$emp_info = CRM_ContactsCommon::get_contact($v['employee_id']);
		$v['title'] = $emp_info['last_name'].' '.$emp_info['first_name'];
		$v['description'] = '';
		$v['additional_info'] = $v['additional_info2'] = '';
		if(date('G:i',$v['start'])==$sd && date('G:i',$v['end'])==$ed) {
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
		DB::Execute('UPDATE crm_projectplanner_work SET start=%d,end=%d WHERE id=%d',array($start,$end,$id));
	}
}

?>
