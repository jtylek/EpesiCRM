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
	
	public static function get($id) {
		if(self::$filter)
			$fil = ' AND (SELECT id FROM crm_calendar_event_group_emp cg WHERE cg.id=e.id AND cg.contact IN '.self::$filter.' LIMIT 1) IS NOT NULL';
		else
			$fil = '';
		$row = DB::GetRow('SELECT e.color,e.access,e.start,e.end,e.title,e.description,e.id,e.timeless,e.priority,e.created_by,e.created_on,e.edited_by,e.edited_on FROM crm_calendar_event e WHERE e.id=%d'.$fil,array($id));
		$result = array();
		if ($row) {
			foreach (array('start','id','title','description','timeless') as $v)
				$result[$v] = $row[$v];
			$result['duration'] = $row['end']-$row['start'];
			$color = array(0 => '', 1 => 'green', 2 => 'yellow', 3 => 'red', 4 => 'blue', 5=> 'gray');
			$color[0] = $color[Base_User_SettingsCommon::get('CRM_Calendar','default_color')];
			$result['color'] = $color[$row['color']];
			$result['additional_info'] = 	Base_LangCommon::ts('CRM_Calendar_Event','Created by').' '.Base_UserCommon::get_user_login($row['created_by']). '<br>'.
											Base_LangCommon::ts('CRM_Calendar_Event','Created on').' '.$row['created_on']. '<br>'.
											(($row['edited_by'])?(
											Base_LangCommon::ts('CRM_Calendar_Event','Edited by').' '.Base_UserCommon::get_user_login($row['edited_by']). '<br>'.
											Base_LangCommon::ts('CRM_Calendar_Event','Edited on').' '.$row['edited_on']. '<br>'):'');
			$access = array(0=>'public', 1=>'public, read-only', 2=>'private');
			$result['additional_info2'] = Base_LangCommon::ts('CRM_Calendar_Event','Access: ').Base_LangCommon::ts('CRM_Calendar_Event',$access[$row['access']]);
		}
		return $result;	
	}
	public static function get_all($start,$end,$order='') {
		if(self::$filter)
			$fil = ' AND (SELECT id FROM crm_calendar_event_group_emp cg WHERE cg.id=e.id AND cg.contact IN '.self::$filter.' LIMIT 1) IS NOT NULL';
		else
			$fil = '';
		print('SELECT start,end,title,description,id,timeless,priority,created_by,created_on,edited_by,edited_on FROM crm_calendar_meeting_event WHERE ((start>=%d AND start<%d) OR (end>=%d AND end<%d)) '.$fil);
		$ret = DB::Execute('SELECT e.color,e.access,e.start,e.end,e.title,e.description,e.id,e.timeless,e.priority,e.created_by,e.created_on,e.edited_by,e.edited_on FROM crm_calendar_event e WHERE ((e.start>=%d AND e.start<%d) OR (e.end>=%d AND e.end<%d)) '.$fil.$order,array($start,$end,$start,$end));
		$result = array();
		while ($row = $ret->FetchRow()) {
			$next_result = array();
			foreach (array('start','id','title','description','timeless') as $v)
				$next_result[$v] = $row[$v];
			$next_result['duration'] = $row['end']-$row['start'];
			$color = array(0 => '', 1 => 'green', 2 => 'yellow', 3 => 'red', 4 => 'blue', 5=> 'gray');
			$color[0] = $color[Base_User_SettingsCommon::get('CRM_Calendar','default_color')];
			$next_result['color'] = $color[$row['color']];
			$next_result['additional_info'] = 	Base_LangCommon::ts('CRM_Calendar_Event','Created by').' '.Base_UserCommon::get_user_login($row['created_by']). '<br>'.
												Base_LangCommon::ts('CRM_Calendar_Event','Created on').' '.$row['created_on']. '<br>'.
												(($row['edited_by'])?(
												Base_LangCommon::ts('CRM_Calendar_Event','Edited by').' '.Base_UserCommon::get_user_login($row['edited_by']). '<br>'.
												Base_LangCommon::ts('CRM_Calendar_Event','Edited on').' '.$row['edited_on']. '<br>'):'');
			$access = array(0=>'public', 1=>'public, read-only', 2=>'private');
			$next_result['additional_info2'] =  Base_LangCommon::ts('CRM_Calendar_Event','Access: ').Base_LangCommon::ts('CRM_Calendar_Event',$access[$row['access']]);
			$result[] = $next_result;
		}
		return $result;
	}

	public static function delete($id) { //make sure that event owner is Acl::get_user....
		DB::Execute('DELETE FROM crm_calendar_event WHERE id=%d',array($id));
	}

	public static function update($id,$start,$duration,$timeless) { //make sure that event owner is Acl::get_user....
		DB::Execute('UPDATE crm_calendar_event SET start=%d, end=%d, timeless=%b WHERE id=%d',array($start,$start+$duration,$timeless,$id));
	}

	public static function decode_contact($id) {
		$contact = CRM_ContactsCommon::get_contact($id);
		return $contact['last_name']." ".$contact['first_name'];
		//return '['.$contact['Company Name'][0].'] '.$contact['First Name']." ".$contact['Last Name'];
	}
}

?>