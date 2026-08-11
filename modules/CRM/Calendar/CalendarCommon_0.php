<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage calendar
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class CRM_CalendarCommon extends ModuleCommon {
	// AdminLTE-only: Base_AdminlteIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/adminlte_icons.php.
	public static function adminlte_icon() { return 'bi-calendar3'; }

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
		if ($func=='add') $def = array(date('Y-m-d H:i:s'), false, $def);
		Base_BoxCommon::push_module(CRM_Calendar_Event::module_name(),$func,$def);
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
					// Feature flag for the FullCalendar (fullcalendar.io, MIT-licensed
					// standard bundle only) rendering path alongside the legacy
					// Smarty grid - see Utils_Calendar::body()/fullcalendar().
					// Now has view parity (month/week/day/list/year), regional
					// settings, AdminLTE/adminltedark theming, and writes
					// (drag-move/resize/drag-to-create/delete), so it's the
					// default for anyone who hasn't already chosen a value here;
					// 'legacy' stays selectable, e.g. to fall back to the PDF
					// export the new engine doesn't rewire yet (see
					// CRM_Calendar::body()'s PDF block).
					array('name'=>'calendar_engine','label'=>__('Calendar grid'), 'type'=>'select', 'values'=>array('legacy'=>__('Classic'), 'fullcalendar'=>__('Modern')), 'default'=>'fullcalendar'),

					array('name'=>'start_day','label'=>__('Start day at'), 'type'=>'select', 'values'=>$start_day, 'default'=>'8:00'),
					array('name'=>'end_day','label'=>__('End day at'), 'type'=>'select', 'values'=>$end_day, 'default'=>'17:00'),
					array('name'=>'interval','label'=>__('Interval of grid'), 'type'=>'select', 'values'=>array('0:15'=>__('15 minutes'),'0:30'=>__('30 minutes'),'1:00'=>__('1 hour'),'2:00'=>__('2 hours')), 'default'=>'1:00')
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
	
	public static function new_event_handler($name, $callback) {
		if (DB::GetOne('SELECT group_name FROM crm_calendar_custom_events_handlers WHERE group_name=%s', array($name))) return;
		DB::Execute('INSERT INTO crm_calendar_custom_events_handlers(group_name, handler_callback) VALUES (%s, %s)', array($name, implode('::',$callback)));
	}
	
	public static function delete_event_handler($name) {
		DB::Execute('DELETE FROM crm_calendar_custom_events_handlers WHERE group_name=%s', array($name));
	}

	// ---- FullCalendar JSON endpoints (ajax.php) ----
	//
	// Reached via $this->create_ajax_callback_url(array('CRM_CalendarCommon',
	// 'fullcalendar_events'), null) from Utils_Calendar::fullcalendar(). Must
	// stay a plain static array-callable, never a closure/first-class-callable
	// ($this->fullcalendar_events(...)): Module::get_ajax_callback_key() does
	// md5(serialize($func).serialize($args)), and serialize() fatals on a
	// Closure. Passing $args=null (rather than e.g. the date range) is also
	// deliberate - get_ajax_callback_key() mints a NEW $_SESSION['ajax_callbacks']
	// entry for every distinct $args, so anything that legitimately varies
	// per request (the date range) has to travel in the URL's query string
	// instead, or the session would grow by one entry per calendar navigation.
	//
	// Neither endpoint trusts the client for anything beyond "which dates am I
	// looking at" / "what did I drag this to" - the CRM Filters Perspective,
	// which event sources are toggled on, and all ACL enforcement are read live
	// from the session/DB exactly as the legacy Smarty-rendered grid does.

	/**
	 * GET start=Y-m-d&end=Y-m-d (exclusive) -> {"ok":true,"notice":?,"events":[...]}
	 * in FullCalendar's event object shape (see Utils_CalendarCommon::
	 * events_to_fullcalendar()). 403s if the caller can't use Calendar at all;
	 * per-record ACL is still enforced underneath by CRM_Calendar_EventCommon::
	 * get_all() -> each handler's crm_event_get() ->
	 * Utils_RecordBrowserCommon::filter_record_by_access(), unchanged.
	 */
	public static function fullcalendar_events(Request $request, $args) {
		if (!Base_AclCommon::check_permission('Calendar'))
			return new JsonResponse(array('ok'=>false, 'error'=>'Forbidden'), 403);

		$start = $request->query->get('start');
		$end = $request->query->get('end');
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$end))
			return new JsonResponse(array('ok'=>false, 'error'=>'Invalid start/end'), 400);

		// Clamps a crafted range so it can't force an unbounded recurrence
		// expansion - CRM_MeetingCommon::crm_event_get_all() re-walks every
		// recurring meeting day-by-day across the whole requested window on
		// every single request (no caching, no materialized occurrences).
		$max_days = 400;
		if ((strtotime($end) - strtotime($start)) / 86400 > $max_days)
			$end = date('Y-m-d', strtotime($start.' +'.$max_days.' days'));

		// Same session state the existing "Perspective" filter and the
		// per-user event-source checkboxes already persist - see
		// CRM_Calendar::body() and CRM_Calendar_Event::get_navigation_bar_additions().
		// scope=mine is a compact-embed opt-in (Applets_MonthView) - restricts
		// to the current user's own records, same scope string CRM_Calendar's
		// own Agenda dashboard applet already uses (CRM_FiltersCommon::
		// get_my_profile()). CRM_Calendar::body() never sends this param, so
		// its own feed is unaffected.
		CRM_Calendar_EventCommon::$filter = $request->query->get('scope') === 'mine'
			? '('.CRM_FiltersCommon::get_my_profile().')'
			: CRM_FiltersCommon::get();
		CRM_Calendar_EventCommon::$events_handlers = Base_User_SettingsCommon::get('CRM_Calendar_Event', 'event_handlers');

		// get_all() print()s a "too many events" notice on hitting
		// self::$events_limit - must be captured, not leaked into the JSON body.
		ob_start();
		$events = CRM_Calendar_EventCommon::get_all($start, $end);
		$notice = ob_get_clean();

		return new JsonResponse(array(
			'ok' => true,
			'notice' => $notice !== '' ? strip_tags($notice) : null,
			'events' => Utils_CalendarCommon::events_to_fullcalendar($events),
		));
	}

	/**
	 * POST id, start (naive local 'Y-m-d\TH:i:s'), duration (seconds, -1 = none),
	 * allDay (0/1) -> {"ok":true} | {"ok":false,"error":"..."}. Requires the
	 * X-Epesi-Calendar header as light CSRF mitigation (forces a preflight,
	 * blocks a simple cross-origin form POST) - ajax.php itself has no CSRF
	 * token, matching the legacy modules/Utils/Calendar/update.php script this
	 * replaces, so this is a net improvement rather than a regression. No new
	 * business logic: calls the exact same CRM_Calendar_EventCommon::update()
	 * static the legacy drag-and-drop endpoint already calls.
	 */
	public static function fullcalendar_update(Request $request, $args) {
		if (!Base_AclCommon::check_permission('Calendar'))
			return new JsonResponse(array('ok'=>false, 'error'=>'Forbidden'), 403);
		if (!$request->isMethod('POST') || $request->headers->get('X-Epesi-Calendar') !== '1')
			return new JsonResponse(array('ok'=>false, 'error'=>'Bad request'), 400);

		$id = $request->request->get('id');
		$start = $request->request->get('start');
		$duration = $request->request->get('duration');
		$all_day = $request->request->getBoolean('allDay');
		if (!$id || !$start || !is_numeric($duration))
			return new JsonResponse(array('ok'=>false, 'error'=>'Invalid request'), 400);

		Base_RegionalSettingsCommon::set_tz();
		$start_ts = strtotime(str_replace('T', ' ', $start));
		Base_RegionalSettingsCommon::restore_tz();
		if ($start_ts === false)
			return new JsonResponse(array('ok'=>false, 'error'=>'Invalid start'), 400);

		ob_start();
		$ok = CRM_Calendar_EventCommon::update($id, $start_ts, (int)$duration, $all_day);
		ob_get_clean();

		return new JsonResponse(array('ok' => $ok !== false));
	}

}

?>
