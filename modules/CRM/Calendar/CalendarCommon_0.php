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
	public static $mode = 'none';
	public static $events_limit = 100;

	public static function menu() {
		if (Utils_RecordBrowserCommon::get_access('crm_meeting','browse'))
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
		if(Base_AclCommon::check_permission('Calendar')) {
			$start_day = array();
			foreach(range(0, 23) as $x)
				$start_day[$x.':00'] = Base_RegionalSettingsCommon::time2reg($x.':00',2,false,false);
			$end_day = $start_day;

			$color = array(1 => 'green', 2 => 'yellow', 3 => 'red', 4 => 'blue', 5=> 'gray', 6 => 'cyan', 7 =>'magenta');
			return array(
				'Calendar'=>array(
					array('name'=>'default_view','label'=>'Default view', 'type'=>'select', 'values'=>array('agenda'=>'Agenda', 'day'=>'Day', 'week'=>'Week', 'month'=>'Month', 'year'=>'Year'), 'default'=>'week'),

					array('name'=>'start_day','label'=>'Start day at', 'type'=>'select', 'values'=>$start_day, 'default'=>'8:00', 'translate'=>false),
					array('name'=>'end_day','label'=>'End day at', 'type'=>'select', 'values'=>$end_day, 'default'=>'17:00', 'translate'=>false),
					array('name'=>'interval','label'=>'Interval of grid', 'type'=>'select', 'values'=>array('0:30'=>'30 minutes','1:00'=>'1 hour','2:00'=>'2 hours'), 'default'=>'1:00'),
					array('name'=>'default_color','label'=>'Default event color', 'type'=>'select', 'values'=>$color, 'default'=>'1')
				)
			);
		}
		return array();
	}

	public static function applet_caption() {
		if(!Base_AclCommon::check_permission('Calendar'))
			return false;

		return "Agenda";
	}

	public static function applet_info() {
		return "Displays Calendar Agenda";
	}

	public static function applet_settings() {
		$cols = CRM_Calendar_EventCommon::get_available_colors();
		$cols[0] = 'All';
		$ret = array(	array('name'=>'days', 'label'=>'Look for events in', 'type'=>'select', 'default'=>'7', 'values'=>array('1'=>'1 day','2'=>'2 days','3'=>'3 days','5'=>'5 days','7'=>'1 week','14'=>'2 weeks', '30'=>'1 month', '61'=>'2 months')));
		$custom_events = DB::GetAssoc('SELECT id, group_name FROM crm_calendar_custom_events_handlers ORDER BY group_name');
		if (!empty($custom_events)) {
			foreach ($custom_events as $id=>$l)
				$ret[] = array('name'=>'events_handlers__'.$id, 'label'=>$l, 'type'=>'checkbox', 'default'=>'1');
		}
		return $ret;
	}
	
	public static function watchdog_label($rid = null, $events = array()) {
	        return null;
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
		print('<center>'.Base_RegionalSettingsCommon::time2reg(time()+$time_shift,false,true).' - '.Base_RegionalSettingsCommon::time2reg(time()+7*24*3600+$time_shift,false,true).'</center>');
	
//		print('<a '.(IPHONE?'class="button blue" ':'').mobile_stack_href(array('CRM_CalendarCommon','mobile_add_event')).'>'.Base_LangCommon::ts('Utils_Calendar','Add event').'</a>');
		CRM_Calendar_EventCommon::$filter = CRM_FiltersCommon::get();
		if($time_shift)
			print('<a '.(IPHONE?'class="button red" ':'').mobile_stack_href(array('CRM_CalendarCommon','mobile_agenda'),array(0)).'>'.Base_LangCommon::ts('Utils_Calendar','Show current week').'</a>');
		else
			print('<a '.(IPHONE?'class="button green" ':'').mobile_stack_href(array('CRM_CalendarCommon','mobile_agenda'),array(7 * 24 * 60 * 60)).'>'.Base_LangCommon::ts('Utils_Calendar','Show next week').'</a>');
		Utils_CalendarCommon::mobile_agenda('CRM/Calendar/Event',array('custom_agenda_cols'=>array('Description','Assigned to','Related with')),$time_shift,array('CRM_CalendarCommon','mobile_view_event'));
	}
	
	public static function mobile_add_event() {
		$qf = new HTML_QuickForm('calendar_add_ev', 'get','mobile.php?'.http_build_query($_GET));
		$qf->addElement('text', 'title', Base_LangCommon::ts('CRM_Calendar','Title'), array('style'=>'width: 100%;', 'id'=>'event_title'));
		$qf->addRule('title', 'Field is required!', 'required');
		
		$qf->addElement('commondata', 'status', Base_LangCommon::ts('CRM_Calendar','Status'),'CRM/Status',array('order_by_key'=>true));

		$time_format = 'Y M d<br>';
		$time_format .= Base_RegionalSettingsCommon::time_12h()?'h:i a':'H:i';
		$lang_code = Base_LangCommon::get_lang_code();
		$qf->addElement('date', 'time', Base_LangCommon::ts('CRM_Calendar','Time'), array('format'=>$time_format, 'optionIncrement'  => array('i' => 5),'language'=>$lang_code));
		$qf->addRule('time', Base_LangCommon::ts('CRM_Calendar','Field required'), 'required');

		$qf->addElement('checkbox', 'timeless', Base_LangCommon::ts('CRM_Calendar','Timeless'));

		$dur = array(
			-1=>Base_LangCommon::ts('CRM_Calendar','---'),
			300=>Base_LangCommon::ts('CRM_Calendar','5 minutes'),
			900=>Base_LangCommon::ts('CRM_Calendar','15 minutes'),
			1800=>Base_LangCommon::ts('CRM_Calendar','30 minutes'),
			2700=>Base_LangCommon::ts('CRM_Calendar','45 minutes'),
			3600=>Base_LangCommon::ts('CRM_Calendar','1 hour'),
			7200=>Base_LangCommon::ts('CRM_Calendar','2 hours'),
			14400=>Base_LangCommon::ts('CRM_Calendar','4 hours'),
			28800=>Base_LangCommon::ts('CRM_Calendar','8 hours'));
		$qf->addElement('select', 'duration', Base_LangCommon::ts('CRM_Calendar','Duration'),$dur);
		$qf->addRule('duration',Base_LangCommon::ts('CRM_Calendar','Duration not selected'),'neq','-1');

		$color = CRM_Calendar_EventCommon::get_available_colors();
		$color[0] = Base_LangCommon::ts('CRM_Calendar','Default').': '.Base_LangCommon::ts('CRM_Calendar',ucfirst($color[0]));
		for($k=1; $k<count($color); $k++)
			$color[$k] = '&bull; '.Base_LangCommon::ts('CRM_Calendar',ucfirst($color[$k]));

		$qf->addElement('textarea', 'description',  Base_LangCommon::ts('CRM_Calendar','Description'), array('rows'=>4, 'style'=>'width: 100%;'));

		$qf->addElement('select', 'access', Base_LangCommon::ts('CRM_Calendar','Access'), Utils_CommonDataCommon::get_translated_array('CRM/Access'), array('style'=>'width: 100%;'));
		$qf->addElement('select', 'priority', Base_LangCommon::ts('CRM_Calendar','Priority'), Utils_CommonDataCommon::get_translated_array('CRM/Priority'), array('style'=>'width: 100%;'));
		$qf->addElement('select', 'color', Base_LangCommon::ts('CRM_Calendar','Color'), $color, array('style'=>'width: 100%;'));

		$qf->addElement('submit', 'submit_button', Base_LangCommon::ts('CRM_Calendar','OK'),IPHONE?'class="button white"':'');
		$renderer =& $qf->defaultRenderer();
		$qf->accept($renderer);
		print($renderer->toHtml());

	}
	
	public static function mobile_view_event($id) {
		$row = CRM_Calendar_EventCommon::get($id);
		$ex = Utils_CalendarCommon::process_event($row);
		
		print('<ul class="field">');
		print('<li>'.Base_LangCommon::ts('CRM_Calendar','Title').': '.$row['title'].'</li>');
		print('<li>'.Base_LangCommon::ts('CRM_Calendar','Starts').': '.$ex['start'].'</li>');
		print('<li>'.Base_LangCommon::ts('CRM_Calendar','Duration').': '.$ex['duration'].'</li>');
		print('<li>'.Base_LangCommon::ts('CRM_Calendar','Ends').': '.$ex['end'].'</li>');
		print('<li>'.Base_LangCommon::ts('CRM_Calendar','Description').': '.$row['description'].'</li>');
		print('</ul>');
	}
	
	public static function new_event_handler($name, $callback) {
		if (DB::GetOne('SELECT group_name FROM crm_calendar_custom_events_handlers WHERE group_name=%s', array($name))) return;
		DB::Execute('INSERT INTO crm_calendar_custom_events_handlers(group_name, handler_callback) VALUES (%s, %s)', array($name, implode('::',$callback)));
	}
	
	public static function delete_event_handler($name) {
		DB::Execute('DELETE FROM crm_calendar_custom_events_handlers WHERE group_name=%s', array($name));
	}

}

?>
