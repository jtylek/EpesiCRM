<?php
/**
 * Example event module
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage calendar-event
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_EventCommon extends Utils_Calendar_EventCommon {
	public static $filter = null;
	private static $my_id = null;
	public static $events_handlers = null;
	
	public static function get_available_colors() {
		static $color = array(0 => '', 1 => 'green', 2 => 'yellow', 3 => 'red', 4 => 'blue', 5=> 'gray', 6 => 'cyan', 7 =>'magenta');
		$color[0] = $color[Base_User_SettingsCommon::get('CRM_Calendar','default_color')];
		return $color;
	}

	public static function get($id) {
		$nid = explode('#', $id);
		if (!isset($nid[1])) trigger_error('Invalid ID:'.$id, E_USER_ERROR);
		else {
			$callback = DB::GetOne('SELECT handler_callback FROM crm_calendar_custom_events_handlers WHERE id=%d', $nid[0]);
			$ret = call_user_func($callback, 'get', $nid[1]);
			$ret['id'] = $nid[0].'#'.$ret['id'];
			return $ret;
		}
	}
	
	public static function get_event_days($start,$end) {
		// TODO
		return array();
	}

	public static function get_all($start,$end,$filter=null) {
		$custom_handlers = DB::GetAssoc('SELECT id, handler_callback FROM crm_calendar_custom_events_handlers');
		$result = array();
		
		if (self::$events_handlers===null) self::$events_handlers = array_keys($custom_handlers);
		foreach (self::$events_handlers as $handler) {
			$result_ext = call_user_func($custom_handlers[$handler], 'get_all', $start, $end, $filter);
			foreach ($result_ext as $v) {
				$v['id'] = $handler.'#'.$v['id'];
				if (isset($v['description'])) $v['custom_agenda_col_0'] = $v['description'];
				if (isset($v['employees'])) {
					$emps = array();
					if (is_array($v['employees']))
						foreach ($v['employees'] as $e) $emps[] = CRM_ContactsCommon::contact_format_no_company($e);
					$v['custom_agenda_col_1'] = implode('<br>',$emps);
				}
				if (isset($v['customers'])) {
					$cuss = array();
					if (is_array($v['customers']))
					foreach ($v['customers'] as $c) $cuss[] = CRM_ContactsCommon::display_company_contact(array('customers'=>$c), true, array('id'=>'customers'));
					$v['custom_agenda_col_2'] = implode('<br>',$cuss);
				}
				$result[] = $v;
			}
		}
		return $result;
	}

	public static function delete($id) {
		$check = explode('#', $id);
		if (isset($check[1])) {
			$callback = DB::GetOne('SELECT handler_callback FROM crm_calendar_custom_events_handlers WHERE id=%d', $check[0]);
			return call_user_func($callback, 'delete', $check[1]);
		}
	}
	
	public static function update(&$id,$start,$duration,$timeless) {
		$check = explode('#', $id);
		if (isset($check[1])) {
			$callback = DB::GetOne('SELECT handler_callback FROM crm_calendar_custom_events_handlers WHERE id=%d', $check[0]);
			return call_user_func($callback, 'update', $check[1], $start, $duration, $timeless);
		}
	}

	public static function get_alarm($id) {
		$recurrence = strpos($id,'_');
		if($recurrence!==false)
			$id = substr($id,0,$recurrence);

		$a = self::get($id);

		if (!$a) return Base_LangCommon::ts('CRM_Calendar_Event','Private record');

		if(isset($a['timeless']))
			$date = Base_LangCommon::ts('CRM_Calendar_Event','Timeless event: %s',array(Base_RegionalSettingsCommon::time2reg($a['timeless'],false)));
		else
			$date = Base_LangCommon::ts('CRM_Calendar_Event',"Start: %s\nEnd: %s",array(Base_RegionalSettingsCommon::time2reg($a['start'],2), Base_RegionalSettingsCommon::time2reg($a['end'],2)));

		return $date."\n".Base_LangCommon::ts('CRM_Calendar_Event',"Title: %s",array($a['title']));
	}
	
}

?>
