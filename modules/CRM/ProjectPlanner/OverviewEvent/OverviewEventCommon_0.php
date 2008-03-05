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
		$result = DB::GetRow('SELECT \'blue\' as color,MIN(start) as min_start,MAX(end) as max_end,start,end,project_id,GROUP_CONCAT(DISTINCT employee_id SEPARATOR \',\') as employees,GROUP_CONCAT(DISTINCT id SEPARATOR \',\') as id,0 as timeless FROM crm_projectplanner_work WHERE id in ('.$id.') GROUP BY project_id,DATE(start)');
		self::add_info($result);
		return $result;
	}
	public static function get_all($start,$end,$order='') {
		$ret = DB::GetAll('SELECT \'blue\' as color,MIN(start) as min_start,MAX(end) as max_end,start,end,project_id,GROUP_CONCAT(DISTINCT employee_id SEPARATOR \',\') as employees,GROUP_CONCAT(DISTINCT id SEPARATOR \',\') as id,0 as timeless FROM crm_projectplanner_work WHERE (start>=%T AND start<%T) GROUP BY project_id,DATE(start)',array($start,$end));
		foreach($ret as &$v) {
			self::add_info($v);
		}
		return $ret;
	}

	private static function add_info(& $v) {
		$v['title'] = Base_RegionalSettingsCommon::time2reg($v['min_start'],true,false).' - '.Base_RegionalSettingsCommon::time2reg($v['max_end'],true,false);

		$emps = CRM_ContactsCommon::get_contacts(array('id'=>explode(',',$v['employees'])),array('first_name','last_name'));
		$emps2 = array();
		foreach($emps as $x) {
			$emps2[] = $x['last_name'].' '.$x['first_name'];
		}
		$v['description'] = implode('<br>',$emps2);

		$v['additional_info'] = $v['additional_info2'] = '';
		$v['timeless'] = 0;
		$v['custom_row_key'] = 'p'.$v['project_id'];
		$v['end'] = strtotime($v['max_end']);
		$v['start'] = strtotime($v['min_start']);
		$v['duration'] = $v['end'] - $v['start'];
	}

	public static function delete($id) {
		DB::Execute('DELETE FROM crm_projectplanner_work WHERE id in ('.$id.')');
	}

	public static function update($id,$start,$duration,$timeless) {
		return false;
	}
}

?>
