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

	
	public static function get($id) {
		if(self::$filter && self::$filter!='()')
			$fil = ' AND (SELECT id FROM crm_calendar_event_group_emp cg WHERE cg.id=e.id AND cg.contact IN '.self::$filter.' LIMIT 1) IS NOT NULL';
		else
			$fil = '';
		$row = DB::GetRow('SELECT e.color,e.access,e.start,e.end,e.title,e.description,e.id,e.timeless,e.priority,e.created_by,e.created_on,e.edited_by,e.edited_on,GROUP_CONCAT(DISTINCT emp.contact SEPARATOR \',\') as employees,GROUP_CONCAT(DISTINCT cus.contact SEPARATOR \',\') as customers FROM crm_calendar_event e LEFT JOIN crm_calendar_event_group_emp emp ON emp.id=e.id LEFT JOIN crm_calendar_event_group_cus cus ON cus.id=e.id WHERE e.id=%d'.$fil.' GROUP BY e.id',array($id));
		$result = array();
		if ($row) {
			foreach (array('start','id','title','timeless','end','description') as $v)
				$result[$v] = $row[$v];
			$result['duration'] = $row['end']-$row['start'];
			$color = self::get_available_colors();
			$result['color'] = $color[$row['color']];
			$access = array(0=>'public', 1=>'public, read-only', 2=>'private');
			$priority = array(0 =>'None', 1 => 'Low', 2 => 'Medium', 3 => 'High');
			$result['additional_info2'] = 	
					'<table border=0><tr><td style="font-weight: bold;">'.Base_LangCommon::ts('CRM_Calendar_Event','Access').'</td><td>'.Base_LangCommon::ts('CRM_Calendar_Event',$access[$row['access']]).'</td></tr>'.
								'<tr><td style="font-weight: bold;">'.Base_LangCommon::ts('CRM_Calendar_Event','Priority').'</td><td>'.Base_LangCommon::ts('CRM_Calendar_Event',$priority[$row['priority']]). '</td></tr>'.
								'<tr><td style="font-weight: bold;">'.Base_LangCommon::ts('CRM_Calendar_Event','Notes').'</td><td>'.Utils_AttachmentCommon::count($row['id'],'CRM/Calendar/Event/'.$row['id']). '</td></tr></table><br>'.
											Base_LangCommon::ts('CRM_Calendar_Event','Created on').' '.$row['created_on']. '<br>'.
											(($row['edited_by'])?(
											Base_LangCommon::ts('CRM_Calendar_Event','Edited by').' '.Base_UserCommon::get_user_login($row['edited_by']). '<br>'.
											Base_LangCommon::ts('CRM_Calendar_Event','Edited on').' '.$row['edited_on']. '<br>'):'');
			$emps = explode(',',$row['employees']);
			$emps_tmp = CRM_ContactsCommon::get_contacts(array('company_name'=>array(CRM_ContactsCommon::get_main_company()),'id'=>$emps),array('id','first_name','last_name'),array('last_name'=>'ASC','first_name'=>'ASC'));
			$emps = array();
			foreach($emps_tmp as $k)
				$emps[] = $k['last_name'].' '.$k['first_name'];
			$cuss = explode(',',$row['customers']);
			$cuss_tmp = CRM_ContactsCommon::get_contacts(array('company_name'=>array(CRM_ContactsCommon::get_main_company()),'id'=>$cuss),array('id','first_name','last_name'),array('last_name'=>'ASC','first_name'=>'ASC'));
			$cuss = array();
			foreach($cuss_tmp as $k)
				$cuss[] = $k['last_name'].' '.$k['first_name'];
			$result['additional_info'] =  $row['description'].'<hr>'.
					Base_LangCommon::ts('CRM_Calendar_Event','Employees:').'<br>'.
						implode('<br>',$emps).
					(empty($cuss)?'':'<br>'.Base_LangCommon::ts('CRM_Calendar_Event','Customers:').'<br>'.
						implode('<br>',$cuss));
			
		}
		return $result;	
	}
	public static function get_all($start,$end,$order='') {
		if(self::$filter && self::$filter!='()')
			$fil = ' AND (SELECT id FROM crm_calendar_event_group_emp cg WHERE cg.id=e.id AND cg.contact IN '.self::$filter.' LIMIT 1) IS NOT NULL';
		else
			$fil = '';
		$count = DB::GetOne('SELECT count(e.id) FROM crm_calendar_event e WHERE ((e.start>=%d AND e.start<%d) OR (e.end>=%d AND e.end<%d)) '.$fil.$order,array($start,$end,$start,$end));
		if($count>50) {
			Epesi::alert(Base_LangCommon::ts('CRM_Calendar_Event','Displaying only 50 of %d events',array($count)));
		}
		$ret = DB::Execute('SELECT e.color,e.access,e.start,e.end,e.title,e.description,e.id,e.timeless,e.priority,e.created_by,e.created_on,e.edited_by,e.edited_on,GROUP_CONCAT(DISTINCT emp.contact SEPARATOR \',\') as employees,GROUP_CONCAT(DISTINCT cus.contact SEPARATOR \',\') as customers FROM crm_calendar_event e LEFT JOIN crm_calendar_event_group_emp emp ON emp.id=e.id LEFT JOIN crm_calendar_event_group_cus cus ON cus.id=e.id WHERE ((e.start>=%d AND e.start<%d) OR (e.end>=%d AND e.end<%d)) '.$fil.' GROUP BY e.id '.$order.' LIMIT 50',array($start,$end,$start,$end));
		$result = array();
		$access = array(0=>'public', 1=>'public, read-only', 2=>'private');
		$priority = array(0 =>'None', 1 => 'Low', 2 => 'Medium', 3 => 'High');
		while ($row = $ret->FetchRow()) {
			$next_result = array();
			foreach (array('start','id','title','timeless','description') as $v)
				$next_result[$v] = $row[$v];
			$next_result['duration'] = $row['end']-$row['start'];
			$color = self::get_available_colors();
			$next_result['color'] = $color[$row['color']];
			$next_result['additional_info2'] = 	'<table border=0><tr><td style="font-weight: bold;">'.Base_LangCommon::ts('CRM_Calendar_Event','Access').'</td><td>'.Base_LangCommon::ts('CRM_Calendar_Event',$access[$row['access']]).'</td></tr>'.
								'<tr><td style="font-weight: bold;">'.Base_LangCommon::ts('CRM_Calendar_Event','Priority').'</td><td>'.Base_LangCommon::ts('CRM_Calendar_Event',$priority[$row['priority']]). '</td></tr>'.
								'<tr><td style="font-weight: bold;">'.Base_LangCommon::ts('CRM_Calendar_Event','Notes').'</td><td>'.Utils_AttachmentCommon::count($row['id'],'CRM/Calendar/Event/'.$row['id']). '</td></tr></table><br>'.
								Base_LangCommon::ts('CRM_Calendar_Event','Created by').' '.Base_UserCommon::get_user_login($row['created_by']). '<br>'.
												Base_LangCommon::ts('CRM_Calendar_Event','Created on').' '.$row['created_on']. '<br>'.
												(($row['edited_by'])?(
												Base_LangCommon::ts('CRM_Calendar_Event','Edited by').' '.Base_UserCommon::get_user_login($row['edited_by']). '<br>'.
												Base_LangCommon::ts('CRM_Calendar_Event','Edited on').' '.$row['edited_on']. '<br>'):'');
			$emps_tmp = explode(',',$row['employees']);
			$emps = array();
			foreach($emps_tmp as $k)
				if(is_numeric($k))
					$emps[] = CRM_ContactsCommon::contact_format_default(CRM_ContactsCommon::get_contact($k));
			$cuss_tmp = explode(',',$row['customers']);
			$cuss = array();
			foreach($cuss_tmp as $k)
				if(is_numeric($k))
					$cuss[] = CRM_ContactsCommon::contact_format_default(CRM_ContactsCommon::get_contact($k));
			$next_result['additional_info'] =  $row['description'].	'<hr>'.
					Base_LangCommon::ts('CRM_Calendar_Event','Employees:').'<br>'.
						implode('<br>',$emps).
					(empty($cuss)?'':'<br>'.Base_LangCommon::ts('CRM_Calendar_Event','Customers:').'<br>'.
						implode('<br>',$cuss));
			$next_result['custom_agenda_col_0'] = $row['description'];
			$next_result['custom_agenda_col_1'] = implode(', ',$emps);
			$next_result['custom_agenda_col_2'] = implode(', ',$cuss);
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
		DB::Execute('UPDATE crm_calendar_event SET start=%d, end=%d, timeless=%b WHERE id=%d',array($start,$start+$duration,$timeless,$id));
		return true;
	}

	public static function decode_contact($id) {
		$contact = CRM_ContactsCommon::get_contact($id);
		return $contact['last_name']." ".$contact['first_name'];
		//return '['.$contact['Company Name'][0].'] '.$contact['First Name']." ".$contact['Last Name'];
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