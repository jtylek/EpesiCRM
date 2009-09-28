<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage calendar
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_CalendarCommon extends ModuleCommon {
	public static $last_added = null;
	public static $trash = 0;

	public static function body_access() {
		return self::Instance()->acl_check('access');
	}
	
	public static function menu() {
		if(self::Instance()->acl_check('access'))
			return array('CRM'=>array('__submenu__'=>1,'Calendar'=>array()));
		else
			return array();
	}

	public static function view_event($func, $def) {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if (!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		if ($func=='add') $def = array(date('Y-m-d H:i:s'), false, $def);
		$x->push_main('CRM_Calendar_Event',$func,$def);
	}

	public static function get_new_event_href($def, $id='none'){
		if (self::$last_added!==null) {
			if (is_numeric(self::$last_added)) self::view_event('view', self::$last_added);
			self::$last_added = null;
		}
		if (isset($_REQUEST['__add_event']) &&
			($id==$_REQUEST['__add_event'])) {
			unset($_REQUEST['__add_event']);
			self::view_event('add',$def);
			return array();
		}
		return array('__add_event'=>$id);
	}
	public static function create_new_event_href($def, $id='none'){
		return Module::create_href(self::get_new_event_href($def, $id));
	}

	public static function user_settings() {
		if(Acl::is_user()) {
/*			$start_day = array();
			foreach(range(0, 11) as $x)
				$start_day[$x.':00'] = Base_RegionalSettingsCommon::time2reg($x.':00',2,false,false);
			$end_day = array();
			foreach(range(12, 23) as $x)
				$end_day[$x.':00'] = Base_RegionalSettingsCommon::time2reg($x.':00',2,false,false);*/
			$start_day = array();
			foreach(range(0, 23) as $x)
				$start_day[$x.':00'] = Base_RegionalSettingsCommon::time2reg($x.':00',2,false,false);
			$end_day = $start_day;

			$color = array(1 => 'green', 2 => 'yellow', 3 => 'red', 4 => 'blue', 5=> 'gray', 6 => 'cyan', 7 =>'magenta');
			return array(
				'Calendar'=>array(
					array('name'=>'default_view','label'=>'Default view', 'type'=>'select', 'values'=>array('agenda'=>'Agenda', 'day'=>'Day', 'week'=>'Week', 'month'=>'Month', 'year'=>'Year'), 'default'=>'week'),

					array('name'=>'start_day','label'=>'Start day at', 'type'=>'select', 'values'=>$start_day, 'default'=>'8:00'),
					array('name'=>'end_day','label'=>'End day at', 'type'=>'select', 'values'=>$end_day, 'default'=>'17:00'),
					array('name'=>'interval','label'=>'Interval of grid', 'type'=>'select', 'values'=>array('0:30'=>'30 minutes','1:00'=>'1 hour','2:00'=>'2 hours'), 'default'=>'1:00'),
					array('name'=>'default_color','label'=>'Default event color', 'type'=>'select', 'values'=>$color, 'default'=>'1')
				)
			);
		}
		return array();
	}

	public static function applet_caption() {
		return "Agenda";
	}

	public static function applet_info() {
		return "Displays Calendar Agenda";
	}

	public static function applet_settings() {
		$cols = CRM_Calendar_EventCommon::get_available_colors();
		$cols[0] = 'All';
		$ret = array(	array('name'=>'days', 'label'=>'Look for events in', 'type'=>'select', 'default'=>'7', 'values'=>array('1'=>'1 day','2'=>'2 days','3'=>'3 days','5'=>'5 days','7'=>'1 week','14'=>'2 weeks', '30'=>'1 month', '61'=>'2 months')),
						array('name'=>'color', 'label'=>'Only events with selected color', 'type'=>'select', 'default'=>'0', 'values'=>$cols));
		$custom_events = DB::GetAssoc('SELECT id, group_name FROM crm_calendar_custom_events_handlers ORDER BY group_name');
		if (!empty($custom_events)) {
			$ret[] = array('name'=>'events_handlers__', 'label'=>'Meetings', 'type'=>'checkbox', 'default'=>'1');
			foreach ($custom_events as $id=>$l)
				$ret[] = array('name'=>'events_handlers__'.$id, 'label'=>$l, 'type'=>'checkbox', 'default'=>'1');
		}
		return $ret;
	}
	
	public static function search_format($id) {
		if(!self::Instance()->acl_check('access'))
			return false;

		$query = 'SELECT ev.starts as start,ev.title,ev.id FROM crm_calendar_event ev '.
					'WHERE deleted='.CRM_CalendarCommon::$trash.' AND ((ev.access<2 OR ev.created_by='.Acl::get_user().') AND '.
 					'ev.id=%d)';
 		$row = DB::GetRow($query,array($id));
		
		if(!$row) return false;
		return '<a '.Base_BoxCommon::create_href(null, 'CRM_Calendar', null, array(), array(), array('search_date'=>$row['start'],'ev_id'=>$row['id'])).'>'.Base_LangCommon::ts('CRM_Calendar','Event (attachment) #%d, %s',array($row['id'], $row['title'])).'</a>';
	}

	public static function search($word){
		if(!self::Instance()->acl_check('access'))
			return array();
		
		$query = 'SELECT ev.starts as start,ev.title,ev.id FROM crm_calendar_event ev '.
					'WHERE deleted='.CRM_CalendarCommon::$trash.' AND ((ev.access<2 OR ev.created_by='.Acl::get_user().') AND (ev.title LIKE '.DB::Concat('\'%\'',DB::qstr($word),'\'%\'').
 					' OR ev.description LIKE '.DB::Concat('\'%\'',DB::qstr($word),'\'%\'').
					'))';
 		$recordSet = DB::Execute($query);
 		$result = array();

 		while (!$recordSet->EOF){
 			$row = $recordSet->FetchRow();
			$result[$row['id']] = '<a '.Base_BoxCommon::create_href(null, 'CRM_Calendar', null, array(), array(), array('search_date'=>$row['start'],'ev_id'=>$row['id'])).'>'.Base_LangCommon::ts('CRM_Calendar','Event #%d, %s',array($row['id'], $row['title'])).'</a>';
 		} 		
		return $result;
	}

	public static function watchdog_label($rid = null, $events = array()) {
		$ret = array('category'=>Base_LangCommon::ts('CRM_Calendar', 'Events'));
		if ($rid!==null) {
			$title = DB::GetOne('SELECT title FROM crm_calendar_event WHERE id=%d',array($rid));
			if ($title===false || $title===null)
				return null;
			$access = DB::GetOne('SELECT access FROM crm_calendar_event WHERE id=%d',array($rid));
			if ($access>=2) {
				$me = CRM_ContactsCommon::get_my_record();
				$am_i_there = DB::GetOne('SELECT 1 FROM crm_calendar_event_group_emp WHERE id=%d AND contact=%d',array($rid, $me['id']));
				if ($am_i_there===false || $am_i_there===null) return null;
			}
			$ret['view_href'] = Module::create_href(array('crm_calendar_watchdog_view_event'=>$rid));
			if (isset($_REQUEST['crm_calendar_watchdog_view_event'])
				&& $_REQUEST['crm_calendar_watchdog_view_event']==$rid) {
				unset($_REQUEST['crm_calendar_watchdog_view_event']);
				$x = ModuleManager::get_instance('/Base_Box|0');
				if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
				$x->push_main('CRM_Calendar_Event','view',$rid);
			}
			$ret['title'] = '<a '.$ret['view_href'].'>'.$title.'</a>';
			$events_display = array();
			foreach ($events as $v) {
				$events_display[] = '<b>'.Base_LangCommon::ts('CRM_Calendar',$v).'</b>';	
			}
			$ret['events'] = implode('<hr>',array_reverse($events_display));
		}
		return $ret;
	}
	
	//////////////////////////////////////////////
	/// mobile methods
	
	public static function mobile_menu() {
		if(Acl::is_user())
			return array('Calendar'=>array('func'=>'mobile_agenda','color'=>'green'));
	}
	
	public static function mobile_agenda($time_shift=0) {
		print(Base_RegionalSettingsCommon::time2reg(time()+$time_shift,false,true).' - '.Base_RegionalSettingsCommon::time2reg(time()+7*24*3600+$time_shift,false,true).'<br>');
	
		CRM_Calendar_EventCommon::$filter = CRM_FiltersCommon::get();
		if($time_shift)
			print('<a '.(IPHONE?'class="button red" ':'').mobile_stack_href(array('CRM_CalendarCommon','mobile_agenda'),array(0)).'>'.Base_LangCommon::ts('Utils_Calendar','Show current week').'</a>');
		else
			print('<a '.(IPHONE?'class="button green" ':'').mobile_stack_href(array('CRM_CalendarCommon','mobile_agenda'),array(7 * 24 * 60 * 60)).'>'.Base_LangCommon::ts('Utils_Calendar','Show next week').'</a>');
		Utils_CalendarCommon::mobile_agenda('CRM/Calendar/Event',array('custom_agenda_cols'=>array('Description','Assigned to','Related with')),$time_shift,array('CRM_CalendarCommon','mobile_view_event'));
	}
	
	public static function mobile_view_event($id) {
		$recurrence = strpos($id,'_');
		if($recurrence!==false)
			$id = substr($id,0,$recurrence);
		$row = CRM_Calendar_EventCommon::get($id);
		$row_orig = DB::GetRow('SELECT * FROM crm_calendar_event WHERE id=%d',array($id));
		$ex = Utils_CalendarCommon::process_event($row);
		
		print('<ul class="field">');
		print('<li>'.Base_LangCommon::ts('CRM_Calendar','Title').': '.$row['title'].'</li>');
		print('<li>'.Base_LangCommon::ts('CRM_Calendar','Starts').': '.$ex['start'].'</li>');
		print('<li>'.Base_LangCommon::ts('CRM_Calendar','Duration').': '.$ex['duration'].'</li>');
		print('<li>'.Base_LangCommon::ts('CRM_Calendar','Ends').': '.$ex['end'].'</li>');
		print('<li>'.Base_LangCommon::ts('CRM_Calendar','Description').': '.$row['description'].'</li>');
		print('<li>'.Base_LangCommon::ts('CRM_Calendar','Priority').': '.Utils_CommonDataCommon::get_value('CRM/Priority/'.$row_orig['priority'],true).'</li>');
		print('<li>'.Base_LangCommon::ts('CRM_Calendar','Status').': '.Utils_CommonDataCommon::get_value('CRM/Status/'.$row_orig['status'],true).'</li>');
		print('<li>'.Base_LangCommon::ts('CRM_Calendar','Access').': '.Utils_CommonDataCommon::get_value('CRM/Access/'.$row_orig['access'],true).'</li>');
		print('</ul>');
//		'color I1 DEFAULT 0, '.
	}
	
	public static function new_event_handler($name, $callbacks) {
		DB::Execute('INSERT INTO crm_calendar_custom_events_handlers(group_name) VALUES (%s)', array($name));
		$possible_callbacks = array('get_callback', 'get_all_callback', 'update_callback', 'delete_callback');
		foreach ($possible_callbacks as $callback_name) {
			if (!isset($callbacks[$callback_name])) continue;
			$callback = $callbacks[$callback_name];
			if (is_array($callback)) $callback = implode('::', $callback);
			DB::Execute('UPDATE crm_calendar_custom_events_handlers SET '.$callback_name.'=%s WHERE group_name=%s', array($callback, $name));
		}
	}
	
	public static function delete_event_handler($name) {
		DB::Execute('DELETE FROM crm_calendar_custom_events_handlers WHERE group_name=%s', array($name));
	}

}
?>
