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
		if (Base_AclCommon::check_permission('Calendar'))
			return array(_M('CRM')=>array('__submenu__'=>1,_M('Calendar')=>array()));
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

			$color = array(1 => __('Green'), 2 => __('Yellow'), 3 => __('Red'), 4 => __('Blue'), 5=> __('Gray'), 6 => __('Cyan'), 7 =>__('Magenta'));
			return array(
				__('Calendar')=>array(
					array('name'=>'default_view','label'=>__('Default view'), 'type'=>'select', 'values'=>array('agenda'=>__('Agenda'), 'day'=>__('Day'), 'week'=>__('Week'), 'month'=>__('Month'), 'year'=>__('Year')), 'default'=>'week'),

					array('name'=>'start_day','label'=>__('Start day at'), 'type'=>'select', 'values'=>$start_day, 'default'=>'8:00'),
					array('name'=>'end_day','label'=>__('End day at'), 'type'=>'select', 'values'=>$end_day, 'default'=>'17:00'),
					array('name'=>'interval','label'=>__('Interval of grid'), 'type'=>'select', 'values'=>array('0:30'=>__('30 minutes'),'1:00'=>__('1 hour'),'2:00'=>__('2 hours')), 'default'=>'1:00')
				)
			);
		}
		return array();
	}

	public static function applet_caption() {
		if(!Base_AclCommon::check_permission('Calendar'))
			return false;

		return __('Agenda');
	}

	public static function applet_info() {
		return __('Displays Calendar Agenda');
	}

	public static function applet_settings() {
		$ret = array(	array('name'=>'days', 'label'=>__('Look for events in'), 'type'=>'select', 'default'=>'7', 'values'=>array('1'=>__('1 day'),'2'=>__('2 days'),'3'=>__('3 days'),'5'=>__('5 days'),'7'=>__('1 week'),'14'=>__('2 weeks'), '30'=>__('1 month'), '61'=>__('2 months'))));
		$custom_events = self::get_event_handlers();
		if (!empty($custom_events)) {
			foreach ($custom_events as $id=>$l)
				$ret[] = array('name'=>'events_handlers__'.$id, 'label'=>$l, 'type'=>'checkbox', 'default'=>'1');
		}
		return $ret;
	}
	
	public static function get_event_handlers() {
		$custom_events = DB::GetAssoc('SELECT id, group_name FROM crm_calendar_custom_events_handlers ORDER BY group_name');
		foreach ($custom_events as $k=>$v) $custom_events[$k] = _V($v); // ****** Calendar Custom handler label
		return $custom_events;
	}
	
	public static function watchdog_label($rid = null, $events = array()) {
	    return null;
	}
	
	//////////////////////////////////////////////
	/// mobile methods
	
	public static function mobile_menu() {
		if(Acl::is_user())
			return array(__('Calendar')=>array('func'=>'mobile_agenda','color'=>'green'));
	}
	
	public static function mobile_agenda($time_shift=0) {
		print('<center>'.Base_RegionalSettingsCommon::time2reg(time()+$time_shift,false,true).' - '.Base_RegionalSettingsCommon::time2reg(time()+7*24*3600+$time_shift,false,true).'</center>');
	
		CRM_Calendar_EventCommon::$filter = CRM_FiltersCommon::get();
		if($time_shift)
			print('<a '.(IPHONE?'class="button red" ':'').mobile_stack_href(array('CRM_CalendarCommon','mobile_agenda'),array(0)).'>'.__('Show current week').'</a>');
		else
			print('<a '.(IPHONE?'class="button green" ':'').mobile_stack_href(array('CRM_CalendarCommon','mobile_agenda'),array(7 * 24 * 60 * 60)).'>'.__('Show next week').'</a>');
		Utils_CalendarCommon::mobile_agenda('CRM/Calendar/Event',array('custom_agenda_cols'=>array(__('Description'),__('Assigned to'),__('Related with'))),$time_shift,array('CRM_CalendarCommon','mobile_view_event'));
	}
	
	public static function mobile_view_event($id) {
		$row = CRM_Calendar_EventCommon::get($id);
		$ex = Utils_CalendarCommon::process_event($row);
		
		print('<ul class="field">');
		print('<li>'.__('Title').': '.$row['title'].'</li>');
		print('<li>'.__('Starts').': '.$ex['start'].'</li>');
		print('<li>'.__('Duration').': '.$ex['duration'].'</li>');
		print('<li>'.__('Ends').': '.$ex['end'].'</li>');
		print('<li>'.__('Description').': '.$row['description'].'</li>');
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
