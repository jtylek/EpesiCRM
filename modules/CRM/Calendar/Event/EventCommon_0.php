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
		$color[0] = $color[1];
		return $color;
	}

	public static function get($id) {
		$nid = explode('#', $id);
		if (!isset($nid[1])) trigger_error('Invalid ID:'.$id, E_USER_ERROR);
		else {
			$callback = DB::GetOne('SELECT handler_callback FROM crm_calendar_custom_events_handlers WHERE id=%d', $nid[0]);
			$callback = explode('::', $callback);
			$ret = call_user_func($callback, 'get', $nid[1]);
			if ($ret===null) return null;
			$ret['id'] = $nid[0].'#'.$ret['id'];
			return $ret;
		}
	}
	
	public static function get_event_days($start,$end) {
		// TODO
		return array();
	}

	public static function get_all($start,$end,$filter=null) {
		if ($filter===null) $filter = self::$filter;
		$custom_handlers = DB::GetAssoc('SELECT id, handler_callback FROM crm_calendar_custom_events_handlers');
		$result = array();

		if (self::$events_handlers===null) self::$events_handlers = array_keys($custom_handlers);
		$count = 0;
		foreach (self::$events_handlers as $handler) {
			$callback = explode('::',$custom_handlers[$handler]);
			$result_ext = call_user_func($callback, 'get_all', $start, $end, $filter);
			foreach ($result_ext as $v) if ($v!==null) {
				$v['id'] = $handler.'#'.$v['id'];
				$v['custom_agenda_col_0'] = isset($v['type'])?$v['type']:'---';
				if (isset($v['description'])) $v['custom_agenda_col_1'] = $v['description'];
				if (isset($v['employees'])) {
					$emps = array();
					if (is_array($v['employees']))
						foreach ($v['employees'] as $e) $emps[] = CRM_ContactsCommon::contact_format_no_company($e);
					$v['custom_agenda_col_2'] = implode('<br>',$emps);
				}
				if (isset($v['customers'])) {
					$cuss = array();
					if (is_array($v['customers']))
					foreach ($v['customers'] as $c) $cuss[] = CRM_ContactsCommon::display_company_contact(array('customers'=>$c), true, array('id'=>'customers'));
					$v['custom_agenda_col_3'] = implode('<br>',$cuss);
				}
				$result[] = $v;
				$count++;
				if ($count==CRM_CalendarCommon::$events_limit) break;
			}
			if ($count==CRM_CalendarCommon::$events_limit) break;
		}
		if ($count==CRM_CalendarCommon::$events_limit) print('<b>There were too many events to display on the Calendar, please change CRM Filter</b>');
		return $result;
	}

	public static function delete($id) {
		$check = explode('#', $id);
		if (isset($check[1])) {
			$callback = DB::GetOne('SELECT handler_callback FROM crm_calendar_custom_events_handlers WHERE id=%d', $check[0]);
			$callback = explode('::', $callback);
			return call_user_func($callback, 'delete', $check[1]);
		}
	}
	
	public static function update(&$id,$start,$duration,$timeless) {
		$check = explode('#', $id);
		if (isset($check[1])) {
			$callback = DB::GetOne('SELECT handler_callback FROM crm_calendar_custom_events_handlers WHERE id=%d', $check[0]);
			$callback = explode('::', $callback);
			return call_user_func($callback, 'update', $check[1], $start, $duration, $timeless);
		}
	}

	public static function get_alarm($id) {
		$recurrence = strpos($id,'_');
		if($recurrence!==false)
			$id = substr($id,0,$recurrence);
		$hndlr = DB::GetOne('SELECT id FROM crm_calendar_custom_events_handlers WHERE group_name=%s',array('Meetings'));

		$a = self::get($hndlr.'#'.$id);

		if (!$a) return __('Private record');

		if(isset($a['timeless']))
			$date = __('Timeless event: %s',array(Base_RegionalSettingsCommon::time2reg($a['timeless'],false)));
		else
			$date = __('Start: %s',array(Base_RegionalSettingsCommon::time2reg($a['start'],2)))."\n".__('End: %s', array(Base_RegionalSettingsCommon::time2reg($a['end'],2)));

		return $date."\n".__('Title: %s',array($a['title']));
	}
	
}

?>
