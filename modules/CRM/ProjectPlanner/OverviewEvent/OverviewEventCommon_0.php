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

class CRM_ProjectPlanner_OverviewEventCommon extends Utils_Calendar_EventCommon {
	public static function get($id) {
		$result = DB::GetRow('SELECT \'blue\' as color,start,(end-start) as duration,project_id,employee_id,id,0 as timeless FROM crm_projectplanner_work WHERE id=%d',array($id));
		self::add_info($result);
		return $result;
	}
	public static function get_all($start,$end,$order='') {
		$ret = DB::GetAll('SELECT \'blue\' as color,start,end,(end-start) as duration,project_id,employee_id,id,0 as timeless FROM crm_projectplanner_work WHERE (start>=%d AND start<%d)',array($start,$end));
		foreach($ret as &$v) {
			self::add_info($v);
		}
		return $ret;
	}

	private static function add_info(& $v) {
		$proj_info = CRM_ContactsCommon::get_contact($v['employee_id']);
		$v['title'] = 'data godzina_zespolona';//grupuj dniami
		$v['description'] = $proj_info['last_name'].' '.$proj_info['first_name']; //imiona pracownikow i godziny pracy
		$v['additional_info'] = $v['additional_info2'] = '';
		$v['timeless'] = 1;
		$v['timeless_key'] = 'p'.$v['project_id'];
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
