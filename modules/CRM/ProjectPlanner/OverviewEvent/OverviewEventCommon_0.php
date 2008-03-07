<?php
/**
 * Example event module
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package custom-projects-planner-overviewevent
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Custom_Projects_Planner_OverviewEventCommon extends Utils_Calendar_EventCommon {
	public static function get($id) {
		$result = DB::GetRow('SELECT \'blue\' as color,MIN(start) as min_start,MAX(end) as max_end,start,end,project_id,GROUP_CONCAT(DISTINCT employee_id SEPARATOR \',\') as employees,GROUP_CONCAT(DISTINCT id SEPARATOR \'_\') as id,0 as timeless FROM custom_projects_planner_work WHERE id in ('.str_replace('_',',',$id).') GROUP BY project_id,DATE(start)');
		self::add_info($result);
		return $result;
	}
	public static function get_all($start,$end,$order='') {
		$ret = DB::GetAll('SELECT \'blue\' as color,MIN(start) as min_start,MAX(end) as max_end,start,end,project_id,GROUP_CONCAT(DISTINCT employee_id SEPARATOR \',\') as employees,GROUP_CONCAT(DISTINCT id SEPARATOR \'_\') as id,0 as timeless FROM custom_projects_planner_work WHERE (start>=%T AND start<%T) GROUP BY project_id,DATE(start)',array($start,$end));
		foreach($ret as &$v) {
			self::add_info($v);
		}

		$ret2 = DB::GetAll('SELECT \'unassigned\' as custom_row_key,\'yellow\' as color,start,0 as duration,GROUP_CONCAT(DISTINCT employee_id SEPARATOR \',\') as employees,1 as timeless FROM custom_projects_planner_work WHERE (start>=%T AND start<%T) GROUP BY DATE(start)',array($start,$end));
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
			$vv['draggable'] = false;
			$vv['delete_action'] = false;
			$vv['view_action'] = false;
			$vv['edit_action'] = false;
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
				'draggable'=>false,
				'delete_action'=>false,
				'view_action'=>false,
				'edit_action'=>false,
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
		$v['edit_action'] = false;
	}

	public static function delete($id) {
		if(ereg('^un',$id)) return false;
		DB::Execute('DELETE FROM custom_projects_planner_work WHERE id in ('.str_replace('_',',',$id).')');
		print('Epesi.updateIndicatorText("updating calendar");Epesi.request("");');
		return true;
	}

	public static function update($id,$start,$duration,$timeless) {
		if(ereg('^un',$id)) return false;
		$old = DB::GetAll('SELECT * FROM custom_projects_planner_work WHERE id in ('.str_replace('_',',',$id).')');
		if(count($old)==0)
			return false;
			
		switch($_SESSION['client']['custom_projects_planner_drag_action']) {
			case 'move':
				self::delete($id);
			case 'copy':
				$interval = 1;
				$begin = $start;
				break;
			case 'copyX':
				$interval = 86400;
				$et = strtotime(date('Y-m-d',strtotime($old[0]['start'])));
				if($start>$et)
					$begin = $et+86400;
				else {
					$begin = $start;
					$start = $et;
				}
				break;
		}
		$zero_t = strtotime('0:00');
		for($ttt=$begin; $ttt<=$start; $ttt+=$interval)
			foreach($old as $v)
				DB::Execute('INSERT INTO custom_projects_planner_work(employee_id,project_id,allday,start,end,vacations) VALUES(%d,%d,%b,%T,%T,0)',
					array($v['employee_id'],$v['project_id'],$v['allday'],strtotime(date('H:i:s',strtotime($v['start'])))-$zero_t+$ttt,strtotime(date('H:i:s',strtotime($v['end'])))-$zero_t+$ttt));
		print('Epesi.updateIndicatorText("updating calendar");Epesi.request("");');
		return true;
	}
}

?>
