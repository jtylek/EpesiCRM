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
		$result = DB::GetRow('SELECT \'blue\' as color,MIN(start) as min_start,MAX(end) as max_end,start,end,project_id,GROUP_CONCAT(DISTINCT employee_id SEPARATOR \',\') as employees,GROUP_CONCAT(DISTINCT id SEPARATOR \'_\') as id,0 as timeless FROM crm_projectplanner_work WHERE id in ('.str_replace('_',',',$id).') GROUP BY project_id,DATE(start)');
		self::add_info($result);
		return $result;
	}
	public static function get_all($start,$end,$order='') {
		$ret = DB::GetAll('SELECT \'blue\' as color,MIN(start) as min_start,MAX(end) as max_end,start,end,project_id,GROUP_CONCAT(DISTINCT employee_id SEPARATOR \',\') as employees,GROUP_CONCAT(DISTINCT id SEPARATOR \'_\') as id,0 as timeless FROM crm_projectplanner_work WHERE (start>=%T AND start<%T) GROUP BY project_id,DATE(start)',array($start,$end));
		foreach($ret as &$v) {
			self::add_info($v);
		}

		$ret2 = DB::GetAll('SELECT \'unassigned\' as custom_row_key,\'yellow\' as color,start,0 as duration,GROUP_CONCAT(DISTINCT employee_id SEPARATOR \',\') as employees,1 as timeless FROM crm_projectplanner_work WHERE (start>=%T AND start<%T) GROUP BY DATE(start)',array($start,$end));
		$uns = array();
		foreach($ret2 as $vv) {
			$busy = explode(',',$vv['employees']);
			$emps_tmp = CRM_ContactsCommon::get_contacts(array('company_name'=>array(CRM_ContactsCommon::get_main_company()),'!id'=>$busy),array('id','first_name','last_name'));
			if(empty($emps_tmp)) continue;
			$desc = array();
			foreach($emps_tmp as $k)
				$desc[] = $k['last_name'].' '.$k['first_name'];
			$vv['title'] = count($emps_tmp);
			$vv['description'] = implode('<br>',$desc);
			$vv['additional_info'] = $vv['additional_info2'] = '';
			$vv['start'] = strtotime($vv['start']);
			$vv['id'] = 'un'.$vv['start'];
//			print($vv['start'].'<hr>');
			$ret[] = $vv;
			$uns[date('Y-m-d',$vv['start'])] = 1;
		}
		
		$emps_tmp = CRM_ContactsCommon::get_contacts(array('company_name'=>array(CRM_ContactsCommon::get_main_company())),array('id','first_name','last_name'));
		$desc = array();
		foreach($emps_tmp as $k)
			$desc[] = $k['last_name'].' '.$k['first_name'];
		$vv = array('title'=>count($emps_tmp),
				'description'=>implode('<br>',$desc),
				'additional_info'=>'',
				'additional_info2'=>'',
				'color'=>'green',
				'custom_row_key'=>'unassigned',
				'duration'=>0,
				'timeless'=>1);
		for($i=Base_RegionalSettingsCommon::reg2time(date('Y-m-d',$start)); $i<$end; $i+=86400) {
			if(isset($uns[date('Y-m-d',$i)])) continue;
			$vv['start'] = $i;
			$vv['id'] = 'un'.$i;
			$ret[] = $vv;
		}
//		print_r($ret);
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
		if(ereg('^un',$id)) return false;
		DB::Execute('DELETE FROM crm_projectplanner_work WHERE id in ('.str_replace('_',',',$id).')');
		return true;
	}

	public static function update($id,$start,$duration,$timeless) {
		return false;
	}
}

?>
