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

class CRM_Calendar_EventCommon extends Utils_Calendar_EventCommon {
	public static $filter = null;

	public static function get_available_colors() {
		static $color = array(0 => '', 1 => 'green', 2 => 'yellow', 3 => 'red', 4 => 'blue', 5=> 'gray', 6 => 'cyan', 7 =>'magenta');
		$color[0] = $color[Base_User_SettingsCommon::get('CRM_Calendar','default_color')];
		return $color;
	}

	public function get_emp_and_cus($id){
		$def = array();
		$def['cus_id'] = array();
		$ret = DB::Execute('SELECT contact FROM crm_calendar_event_group_cus WHERE id=%d', $id);
		while ($row=$ret->FetchRow())
			$def['cus_id'][] = $row['contact'];
		$def['emp_id'] = array();
		$ret = DB::Execute('SELECT contact FROM crm_calendar_event_group_emp WHERE id=%d', $id);
		while ($row=$ret->FetchRow())
			$def['emp_id'][] = $row['contact'];
		return $def;
	}

	public function get_followup_leightbox_href($id, $def){
		$prefix = 'crm_event_leightbox';
		CRM_FollowupCommon::drawLeightbox($prefix);
		$v = $def['status'];
		if (!$v) $v = 0;
		if (isset($_REQUEST['form_name']) && $_REQUEST['form_name']==$prefix.'_follow_up_form' && $_REQUEST['id']==$id) {
			unset($_REQUEST['form_name']);
			$v = $_REQUEST['closecancel'];
			$action  = $_REQUEST['action'];
			if ($action == 'set_in_progress') $v = 1;
			DB::Execute('UPDATE crm_calendar_event SET status=%d WHERE id=%d',array($v,$id));
			if ($action == 'set_in_progress') location(array());

			$values = $def;
			$values['date_and_time'] = date('Y-m-d H:i:s');
			$values['title'] = Base_LangCommon::ts('CRM/Calendar/Event','Follow up: ').$values['title'];
			unset($values['status']);

			if ($action != 'none') {
				$x = ModuleManager::get_instance('/Base_Box|0');
				$ec = CRM_Calendar_EventCommon::get_emp_and_cus($values['id']);
				$values['emp_id'] = $ec['emp_id'];
				$values['cus_id'] = $ec['cus_id'];
				if ($action == 'new_task') $x->push_main('Utils/RecordBrowser','view_entry',array('add', null, array('page_id'=>md5('crm_tasks'),'title'=>$values['title'],'permission'=>$values['access'],'priority'=>$values['priority'],'description'=>$values['description'],'deadline'=>date('Y-m-d H:i:s', strtotime('+1 day')),'employees'=>$values['emp_id'], 'customers'=>$values['cus_id'])), array('task'));
				if ($action == 'new_phonecall') $x->push_main('Utils/RecordBrowser','view_entry',array('add', null, array('subject'=>$values['title'],'permission'=>$values['access'],'priority'=>$values['priority'],'description'=>$values['description'],'date_and_time'=>date('Y-m-d H:i:s'),'employees'=>$values['emp_id'], 'contact'=>isset($values['cus_id'][0])?$values['cus_id'][0]:'')), array('phonecall'));
				if ($action == 'new_event') CRM_CalendarCommon::view_event('add',$values);
				return false;
			}

			location(array());
		}
		return 'href="javascript:void(0)" class="lbOn" rel="'.$prefix.'_followups_leightbox" onMouseDown="'.$prefix.'_set_id('.$id.');"';
	}


	public static function get($id) {
		if(self::$filter=='()')
			$fil = ' AND 1=0';
		else if(self::$filter)
			$fil = ' AND (SELECT id FROM crm_calendar_event_group_emp cg WHERE cg.id=e.id AND cg.contact IN '.self::$filter.' LIMIT 1) IS NOT NULL';
		else
			$fil = '';
		if(!Base_AclCommon::i_am_admin())
			$fil .= ' AND (e.access<2 OR (SELECT id FROM crm_calendar_event_group_emp cg2 WHERE cg2.id=e.id AND cg2.contact='.CRM_FiltersCommon::get_my_profile().' LIMIT 1) IS NOT NULL)';
		$t = microtime(true);
		$my_id = CRM_FiltersCommon::get_my_profile();
		$row = DB::GetRow('SELECT e.status,e.color,e.access,e.start,e.end,e.title,e.description,e.id,e.timeless,e.priority,e.created_by,e.created_on,e.edited_by,e.edited_on,GROUP_CONCAT(DISTINCT emp.contact SEPARATOR \',\') as employees,GROUP_CONCAT(DISTINCT cus.contact SEPARATOR \',\') as customers FROM crm_calendar_event e LEFT JOIN crm_calendar_event_group_emp emp ON emp.id=e.id LEFT JOIN crm_calendar_event_group_cus cus ON cus.id=e.id WHERE e.id=%d'.$fil.' GROUP BY e.id',array($id));
		$result = array();
		if ($row) {
			foreach (array('start','id','title','timeless','end','description') as $v)
				$result[$v] = $row[$v];
			$result['duration'] = $row['end']-$row['start'];
			$color = self::get_available_colors();
			if($row['status']>=2)
				$result['color'] = 'gray';
			else
				$result['color'] = $color[$row['color']];
			$access = array(0=>'public', 1=>'public, read-only', 2=>'private');
			$priority = array(0 =>'None', 1 => 'Low', 2 => 'Medium', 3 => 'High');
			$result['additional_info2'] = 	Base_LangCommon::ts('CRM_Calendar_Event','Access').': '.Base_LangCommon::ts('CRM_Calendar_Event',$access[$row['access']]).'<br>'.
								Base_LangCommon::ts('CRM_Calendar_Event','Priority').': '.Base_LangCommon::ts('CRM_Calendar_Event',$priority[$row['priority']]). '<br>'.
								Base_LangCommon::ts('CRM_Calendar_Event','Notes').': '.Utils_AttachmentCommon::count($row['id'],'CRM/Calendar/Event/'.$row['id']). '<br>'.
								Base_LangCommon::ts('CRM_Calendar_Event','Created by').' '.Base_UserCommon::get_user_login($row['created_by']). '<br>'.
											Base_LangCommon::ts('CRM_Calendar_Event','Created on').' '.$row['created_on']. '<br>'.
											(($row['edited_by'])?(
											Base_LangCommon::ts('CRM_Calendar_Event','Edited by').' '.Base_UserCommon::get_user_login($row['edited_by']). '<br>'.
											Base_LangCommon::ts('CRM_Calendar_Event','Edited on').' '.$row['edited_on']. '<br>'):'');

			$emps_tmp = explode(',',$row['employees']);
			$emps = array();
			foreach($emps_tmp as $k)
				if(is_numeric($k))
					$emps[] = CRM_ContactsCommon::contact_format_no_company(CRM_ContactsCommon::get_contact($k));
			$cuss_tmp = explode(',',$row['customers']);
			$cuss = array();
			foreach($cuss_tmp as $k)
				if(is_numeric($k))
					$cuss[] = CRM_ContactsCommon::contact_format_default(CRM_ContactsCommon::get_contact($k));

			$result['additional_info'] =  $row['description'].'<hr>'.
					Base_LangCommon::ts('CRM_Calendar_Event','Employees:').'<br>'.
						implode('<br>',$emps).
					(empty($cuss)?'':'<br>'.Base_LangCommon::ts('CRM_Calendar_Event','Customers:').'<br>'.
						implode('<br>',$cuss));
			if($row['access']>0 && !in_array($my_id,$emps_tmp) && !Base_AclCommon::i_am_admin()) {
				$result['edit_action'] = false;
				$result['delete_action'] = false;
			} elseif($row['status']<2)
					$result['actions'] = array(array('icon'=>Base_ThemeCommon::get_template_file('CRM_Calendar_Event','access-private.png'),'href'=>CRM_Calendar_EventCommon::get_followup_leightbox_href($row['id'], $row)));

		}
		return $result;
	}
	public static function get_event_days($start,$end) {
		if (!is_numeric($start)) $start = strtotime($start);
		if (!is_numeric($end)) $end = strtotime($end);
		if(self::$filter=='()')
			$fil = ' AND 1=0';
		else if(self::$filter)
			$fil = ' AND (SELECT id FROM crm_calendar_event_group_emp cg WHERE cg.id=e.id AND cg.contact IN '.self::$filter.' LIMIT 1) IS NOT NULL';
		else
			$fil = '';
		if(!Base_AclCommon::i_am_admin())
			$fil .= ' AND (e.access<2 OR (SELECT id FROM crm_calendar_event_group_emp cg2 WHERE cg2.id=e.id AND cg2.contact='.CRM_FiltersCommon::get_my_profile().' LIMIT 1) IS NOT NULL)';
		$ret = DB::Execute('SELECT color, e.start FROM crm_calendar_event AS e WHERE e.start>=%d AND e.start<=%d AND status<2 '.$fil.' ORDER BY e.start', array($start, $end));
		$rs = array();
		$last = '';
		while ($row = $ret->FetchRow()) {
			$next = date('Y-m-d',$row['start']);
			if ($next==$last) continue;
			$rs[] = array('time'=>strtotime($next),'color'=>$row['color']);
			$last = $next;
		}
		return $rs;
	}

	public static function get_all($start,$end,$order=' ORDER BY e.start') {
		if(self::$filter=='()')
			$fil = ' AND 1=0';
		else if(self::$filter)
			$fil = ' AND (SELECT id FROM crm_calendar_event_group_emp cg WHERE cg.id=e.id AND cg.contact IN '.self::$filter.' LIMIT 1) IS NOT NULL';
		else
			$fil = '';
		$my_id = CRM_FiltersCommon::get_my_profile();
		if(!Base_AclCommon::i_am_admin())
			$fil .= ' AND (e.access<2 OR (SELECT id FROM crm_calendar_event_group_emp cg2 WHERE cg2.id=e.id AND cg2.contact='.$my_id.' LIMIT 1) IS NOT NULL)';
		$count = DB::GetOne('SELECT count(e.id) FROM crm_calendar_event e WHERE ((e.start>=%d AND e.start<%d) OR (e.end>=%d AND e.end<%d)) '.$fil.$order,array($start,$end,$start,$end));
		if($count>50) {
			Epesi::alert(Base_LangCommon::ts('CRM_Calendar_Event','Displaying only 50 of %d events',array($count)));
		}
		$ret = DB::Execute('SELECT e.status,e.color,e.access,e.start,e.end,e.title,e.description,e.id,e.timeless,e.priority,e.created_by,e.created_on,e.edited_by,e.edited_on,(SELECT GROUP_CONCAT(DISTINCT emp.contact SEPARATOR \',\') FROM crm_calendar_event_group_emp emp WHERE emp.id=e.id GROUP BY emp.id) as employees,(SELECT GROUP_CONCAT(DISTINCT cus.contact SEPARATOR \',\') FROM crm_calendar_event_group_cus cus WHERE cus.id=e.id GROUP BY cus.id) as customers FROM crm_calendar_event e WHERE ((e.start>=%d AND e.start<%d) OR (e.end>=%d AND e.end<%d)) '.$fil.$order.' LIMIT 50',array($start,$end,$start,$end));
		$result = array();
		$access = array(0=>'public', 1=>'public, read-only', 2=>'private');
		$priority = array(0 =>'None', 1 => 'Low', 2 => 'Medium', 3 => 'High');
		while ($row = $ret->FetchRow()) {
			$next_result = array();
			foreach (array('start','id','title','timeless','description') as $v)
				$next_result[$v] = $row[$v];
			$next_result['duration'] = $row['end']-$row['start'];
			$color = self::get_available_colors();
			if($row['status']>=2)
				$next_result['color'] = 'gray';
			else
				$next_result['color'] = $color[$row['color']];

			$next_result['additional_info2'] = 	Base_LangCommon::ts('CRM_Calendar_Event','Access').': '.Base_LangCommon::ts('CRM_Calendar_Event',$access[$row['access']]).'<br>'.
								Base_LangCommon::ts('CRM_Calendar_Event','Priority').': '.Base_LangCommon::ts('CRM_Calendar_Event',$priority[$row['priority']]). '<br>'.
								Base_LangCommon::ts('CRM_Calendar_Event','Notes').': '.Utils_AttachmentCommon::count($row['id'],'CRM/Calendar/Event/'.$row['id']). '<br>'.
								Base_LangCommon::ts('CRM_Calendar_Event','Created by').' '.Base_UserCommon::get_user_login($row['created_by']). '<br>'.
											Base_LangCommon::ts('CRM_Calendar_Event','Created on').' '.$row['created_on']. '<br>'.
											(($row['edited_by'])?(
											Base_LangCommon::ts('CRM_Calendar_Event','Edited by').' '.Base_UserCommon::get_user_login($row['edited_by']). '<br>'.
											Base_LangCommon::ts('CRM_Calendar_Event','Edited on').' '.$row['edited_on']. '<br>'):'');

			$emps_tmp = explode(',',$row['employees']);
			$emps = array();
			foreach($emps_tmp as $k)
				if(is_numeric($k))
					$emps[] = CRM_ContactsCommon::contact_format_no_company(CRM_ContactsCommon::get_contact($k));
			$cuss_tmp = explode(',',$row['customers']);
			$cuss = array();
			foreach($cuss_tmp as $k)
				if(is_numeric($k))
					$cuss[] = CRM_ContactsCommon::contact_format_default(CRM_ContactsCommon::get_contact($k));
			$next_result['additional_info'] =  $row['description'].	'<hr>'.
					'<b>'.Base_LangCommon::ts('CRM_Calendar_Event','Employees:').'</b><br>'.
						implode('<br>',$emps).
					(empty($cuss)?'':'<br><b>'.Base_LangCommon::ts('CRM_Calendar_Event','Customers:').'</b><br>'.
						implode('<br>',$cuss));
			$next_result['custom_agenda_col_0'] = $row['description'];
			$next_result['custom_agenda_col_1'] = implode(', ',$emps);
			$next_result['custom_agenda_col_2'] = implode(', ',$cuss);
			//$next_result['actions'] = array();
			if($row['access']>0 && !in_array($my_id,$emps_tmp) && !Base_AclCommon::i_am_admin()) {
				$next_result['edit_action'] = false;
				$next_result['delete_action'] = false;
			} elseif($row['status']<2)
					$next_result['actions'] = array(array('icon'=>Base_ThemeCommon::get_template_file('CRM_Calendar_Event','access-private.png'),'href'=>CRM_Calendar_EventCommon::get_followup_leightbox_href($row['id'], $row)));

			$result[] = $next_result;
		}
		return $result;
	}

	public static function delete($id) { //make sure that event owner is Acl::get_user....
		DB::Execute('DELETE FROM crm_calendar_event_group_emp WHERE id=%d', array($id));
		DB::Execute('DELETE FROM crm_calendar_event_group_cus WHERE id=%d', array($id));
		DB::Execute('DELETE FROM crm_calendar_event WHERE id=%d',array($id));
		Utils_AttachmentCommon::persistent_mass_delete($id,'CRM/Calendar/Event/'.$id);
		Utils_MessengerCommon::delete_by_id('CRM_Calendar_Event:'.$id);
		return true;
	}

	public static function update(&$id,$start,$duration,$timeless) { //make sure that event owner is Acl::get_user....
		$prev = DB::GetRow('SELECT * FROM crm_calendar_event WHERE id=%d',array($id));
		if(isset($prev['recurrence_id']) && $prev['recurrence_id']!==null) {
			$start_diff = $prev['start']-$start;
			$end_diff = $prev['end']-$start-$duration;
			DB::Execute('UPDATE crm_calendar_event SET start=start-%d, end=end-%d, timeless=%b WHERE recurrence_id=%d',array($start_diff,$end_diff,$timeless,$prev['recurrence_id']));
			print('Epesi.updateIndicatorText("updating calendar");Epesi.request("");');
		} else
			DB::Execute('UPDATE crm_calendar_event SET start=%d, end=%d, timeless=%b WHERE id=%d',array($start,$start+$duration,$timeless,$id));
		return true;
	}

	public static function get_alarm($id) {
		$a = self::get($id);

		if($a['timeless'])
			$date = Base_LangCommon::ts('CRM_Calendar_Event','Timeless event: %s',array(Base_RegionalSettingsCommon::time2reg($a['start'],false)));
		else
			$date = Base_LangCommon::ts('CRM_Calendar_Event',"Start: %s\nEnd: %s",array(Base_RegionalSettingsCommon::time2reg($a['start']), Base_RegionalSettingsCommon::time2reg($a['end'])));

		return $date."\n".Base_LangCommon::ts('CRM_Calendar_Event',"Title: %s",array($a['title']));
	}
}

?>
