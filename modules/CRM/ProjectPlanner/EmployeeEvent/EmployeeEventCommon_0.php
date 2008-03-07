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
			$v['custom_row_key'] = 'vacations';
		} else {
			$v['title'] = '';
			$v['description'] = '';
			$v['custom_row_key'] = 'p'.$v['project_id'];
		}
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
		return true;
	}

	public static function update($id,$start,$duration,$custom_row_key) {
		if($custom_row_key=='add')
			return false;
			
		$end = $start+$duration;

		if($custom_row_key=='vacations')
			DB::Execute('UPDATE crm_projectplanner_work SET project_id=null,vacations=1,start=%T,end=%T WHERE id=%d',array($start,$end,$id));
		else
			DB::Execute('UPDATE crm_projectplanner_work SET project_id=%d,vacations=0,start=%T,end=%T WHERE id=%d',array(ltrim($custom_row_key,'p'),$start,$end,$id));
		return true;
	}
}

?>
