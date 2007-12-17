<?php
/**
 * Example event module
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package tests-calendar-event
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_EventCommon extends Utils_Calendar_EventCommon {
	static function get($id) {
		return null;	
	}
	public static function get_all($start,$end) {
		return DB::GetAll('SELECT start,end,title,description,id,timeless FROM crm_calendar_event WHERE ((start>=%d AND start<%d) OR (end>=%d AND end<%d))',array($start,$end,$start,$end));
	}

	public static function delete($id) { //make sure that event owner is Acl::get_user....
		DB::Execute('DELETE FROM crm_calendar_event WHERE id=%d',array($id));
	}

	public static function update($id,$start,$timeless) { //make sure that event owner is Acl::get_user....
		DB::Execute('UPDATE crm_calendar_event SET start=%d, timeless=%b WHERE id=%d',array($start,$timeless,$id));
	}

	public static function decode_contact($id) {
		$contact = CRM_ContactsCommon::get_contact($id);
		return $contact['First Name']." ".$contact['Last Name'];
	}
}

?>