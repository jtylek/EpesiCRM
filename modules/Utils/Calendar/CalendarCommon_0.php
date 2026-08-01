<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com> and Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-Utils
 * @subpackage calendar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CalendarCommon extends ModuleCommon {
	public static function print_event($ev,$mode='',$with_div=true) {
		$th = Base_ThemeCommon::init_smarty();
		$ex = self::process_event($ev);
		$th->assign('event_id',$ev['id']);
		$th->assign('draggable',!isset($ev['draggable']) || $ev['draggable']===true);
		$title = $ev['title'];
		$title_st = strip_tags($ev['title']);
		$title_s = $title;
		$th->assign('with_div',$with_div);
		$th->assign('title',$title);
		$th->assign('title_s',$title_s);
		$th->assign('description',$ev['description']);
		$th->assign('color',$ev['color']);
		$th->assign('start',$ex['start']);
		$th->assign('start_time',$ex['start_time']);
		$th->assign('end_time',$ex['end_time']);
		$th->assign('start_date',$ex['start_date']);
		$th->assign('end_date',$ex['end_date']);
		$th->assign('start_day',$ex['start_day']);
		$th->assign('end_day',$ex['end_day']);
		$th->assign('end',$ex['end']);
		$th->assign('duration',$ex['duration']);
		$th->assign('show_hide_info',__('Click to show / hide menu'));
		$th->assign('additional_info',$ev['additional_info']);
		$th->assign('additional_info2',$ev['additional_info2']);
		if(isset($ev['custom_tooltip']))
			$th->assign('custom_tooltip',$ev['custom_tooltip']);
		ob_start();
		Base_ThemeCommon::display_smarty($th,'Utils_Calendar','event_tip');
		$tip = ob_get_clean();
		$th->assign('tip_tag_attrs',Utils_TooltipCommon::open_tag_attrs($tip,false));

		if(!isset($ev['view_action']) || $ev['view_action']===true)
			$th->assign('view_href', Module::create_href(array('UCev_id'=>$ev['id'], 'UCaction'=>'view')));
		elseif ($ev['view_action']!==false)
			$th->assign('view_href', $ev['view_action']);

		if(!isset($ev['edit_action']) || $ev['edit_action']===true)
			$th->assign('edit_href', Module::create_href(array('UCev_id'=>$ev['id'], 'UCaction'=>'edit')));
		elseif ($ev['edit_action']!==false)
			$th->assign('edit_href', $ev['edit_action']);

		$link_text = Module::create_href_js(array('UCev_id'=>$ev['id'], 'UCaction'=>'move','UCdate'=>'__YEAR__-__MONTH__-__DAY__'));
		if(!isset($ev['move_action']) || $ev['move_action']===true)
			$th->assign('move_href', Utils_PopupCalendarCommon::create_href('move_event'.str_replace(array('#','-'),'_',$ev['id']), $link_text,null,null,'popup.clonePosition(\'utils_calendar_event:'.$ev['id'].'\',{setWidth:false,setHeight:false,offsetTop:$(\'utils_calendar_event:'.$ev['id'].'\').getHeight()})'));

		if(!isset($ev['delete_action']) || $ev['delete_action']===true)
			$th->assign('delete_href', Module::create_confirm_href(__('Delete this event?'),array('UCev_id'=>$ev['id'], 'UCaction'=>'delete')));
		elseif ($ev['delete_action']!==false)
			$th->assign('delete_href', $ev['delete_action']);

		$th->assign('handle_class','handle');
		$th->assign('custom_actions',$ev['actions']);
		Base_ThemeCommon::display_smarty($th,'Utils_Calendar','event'.($mode?'_'.$mode:''));
	}

	/**
	 * Translates the array shape every event-source handler's get_all()/get()
	 * already returns (id, start/timeless, duration, title, description, color,
	 * view_action/edit_action/delete_action/move_action, ...) into FullCalendar's
	 * event object shape. Pure shape translation - process_event() is reused
	 * as-is for its existing validation and its $row['end'] derivation (by
	 * reference) for timed events, so there is exactly one place that knows how
	 * to compute an event's end time.
	 *
	 * @param array $events array of event arrays as returned by
	 *   Utils_Calendar_EventCommon::get_all()/CRM_Calendar_EventCommon::get_all()
	 * @return array array of FullCalendar-shaped event objects, ready for
	 *   json_encode()
	 */
	public static function events_to_fullcalendar($events) {
		$ret = array();
		foreach ($events as $ev)
			$ret[] = self::event_to_fullcalendar($ev);
		return $ret;
	}

	public static function event_to_fullcalendar($ev) {
		self::process_event($ev); // validates required fields; derives $ev['end'] for timed events

		$timeless = isset($ev['timeless']) && $ev['timeless'];
		if ($timeless) {
			$start = is_numeric($ev['timeless']) ? date('Y-m-d', $ev['timeless']) : $ev['timeless'];
			$end = null;
		} else {
			$start = str_replace(' ', 'T', Base_RegionalSettingsCommon::time2reg($ev['start'], true, true, true, false));
			// duration==-1 ("no duration", the Meeting timeless sentinel that can
			// still reach here via a non-timeless code path) or 0 -> no end.
			$end = ($ev['duration'] > 0)
				? str_replace(' ', 'T', Base_RegionalSettingsCommon::time2reg($ev['end'], true, true, true, false))
				: null;
		}

		// Epesi's palette is 7 named colors, not hex values - emitted as a CSS
		// class (fc-epesi-ev--<name>) rather than inline backgroundColor/
		// borderColor, so adminlte and adminltedark can each style them for
		// their own contrast (an inline color would be unreadable in dark mode
		// for some of these).
		$classNames = array('fc-epesi-ev');
		if (!empty($ev['color'])) $classNames[] = 'fc-epesi-ev--'.preg_replace('/[^a-z0-9_-]/', '', (string)$ev['color']);

		return array(
			'id' => (string)$ev['id'],
			'title' => (string)$ev['title'],
			'start' => $start,
			'end' => $end,
			'allDay' => $timeless,
			'classNames' => $classNames,
			// Straight from the handler's own tri-state (true/absent = default
			// allowed, false = refused, e.g. CRM_MeetingCommon::crm_event_get()
			// already sets these false when the viewer lacks edit access) - no
			// new permission logic, just a type mapping.
			'startEditable' => !isset($ev['move_action']) || $ev['move_action'] !== false,
			'durationEditable' => !$timeless && (!isset($ev['edit_action']) || $ev['edit_action'] !== false),
			'extendedProps' => array(
				// Verbatim ' href="..." onClick="..." ' attribute fragments, the
				// exact same ones the legacy event chip (event.tpl/event_day.tpl)
				// already renders via Module::create_href()/create_record_href() -
				// the client-side glue applies these onto the rendered element
				// rather than re-deriving any href logic here. Every built-in
				// handler (Meeting/Tasks/PhoneCall) always sets these three keys
				// explicitly (see e.g. CRM_MeetingCommon::crm_event_get()), so in
				// practice the UCev_id/UCaction "default" fallback below - matching
				// Utils_CalendarCommon::print_event()'s legacy default construction
				// for API parity - is dead code for every handler shipped with this
				// app; it is NOT wired up to be handled by the FullCalendar render
				// path (Utils_Calendar::fullcalendar()), only by the legacy
				// day/week/month/year/agenda methods, so a future third-party
				// handler that omits view_action/edit_action/delete_action would
				// need that wired up too before its default links would work here.
				'viewAttrs' => self::action_href($ev, 'view_action',
					Module::create_href(array('UCev_id'=>$ev['id'], 'UCaction'=>'view'))),
				'editAttrs' => self::action_href($ev, 'edit_action',
					Module::create_href(array('UCev_id'=>$ev['id'], 'UCaction'=>'edit'))),
				'deleteAttrs' => self::action_href($ev, 'delete_action',
					Module::create_confirm_href(__('Delete this event?'), array('UCev_id'=>$ev['id'], 'UCaction'=>'delete'))),
				'tooltip' => strip_tags((string)($ev['description'] ?? '')),
			),
		);
	}

	/**
	 * Normalizes an event's view_action/edit_action/delete_action tri-state
	 * (true|absent = use the precomputed default href, false = suppressed,
	 * string = use verbatim - already a full ' href="..." onClick="..." '
	 * fragment from Module::create_href()) into either a fragment or null.
	 */
	private static function action_href($ev, $key, $href_if_default) {
		if (!isset($ev[$key]) || $ev[$key] === true)
			return $href_if_default;
		if ($ev[$key] === false)
			return null;
		return (string)$ev[$key];
	}

	public static function process_event(& $row) {
		if(!isset($row['start']) && !(isset($row['timeless']) && $row['timeless']))
			trigger_error('Invalid return of event method: get(_all) (missing field \'start\' or \'timeless\' in '.print_r($row, true).')',E_USER_ERROR);
		if(!isset($row['duration']) || !is_numeric($row['duration']))
			trigger_error('Invalid return of event method: get(_all) (missing or not numeric field \'duration\' in '.print_r($row, true).')',E_USER_ERROR);
		if(!isset($row['title']))
			trigger_error('Invalid return of event method: get(_all) (missing field \'title\' in '.print_r($row, true).')',E_USER_ERROR);
		if(!isset($row['description']))
			trigger_error('Invalid return of event method: get(_all) (missing field \'description\' in '.print_r($row, true).')',E_USER_ERROR);
		if(!isset($row['id']))
			trigger_error('Invalid return of event method: get(_all) (missing field \'id\' in '.print_r($row, true).')',E_USER_ERROR);
		if(!isset($row['additional_info']))
			$row['additional_info'] = '';
		if(!isset($row['additional_info2']))
			$row['additional_info2'] = '';
		if(!isset($row['actions']))
			$row['actions'] = array();


		if(isset($row['timeless']) && $row['timeless']) {
			if(!isset($row['timeless_caption']))
				$row['timeless_caption'] = str_replace(' ','&nbsp;',__('Timeless'));
			$start_time = $row['timeless_caption'];
			$end_time = $start_time;
			$ev_start = strtotime($row['timeless']);
			if (!isset($row['start'])) $start_day = _V(date('D', $ev_start));
			else $start_day = _V(date('D',$row['start']));
			$start_date = Base_RegionalSettingsCommon::time2reg($ev_start,false,true,false);
			if($start_date == Base_RegionalSettingsCommon::time2reg(time(),false))
				$start_t = __('Today');
			elseif($start_date == Base_RegionalSettingsCommon::time2reg(time()+3600*24,false))
				$start_t = __('Tomorrow');
			elseif($start_date == Base_RegionalSettingsCommon::time2reg(time()-3600*24,false))
				$start_t = __('Yesterday');
			else
				$start_t = $start_day.', '.$start_date;
			$end_date = $start_date;
			$end_day = $start_day;
			$end_t = $start_t;
		} else {
			if(!is_numeric($row['start']) && is_string($row['start'])) $row['start'] = strtotime($row['start']);
			if($row['start']===false)
				trigger_error('Invalid return of event method: get (start equal to null)',E_USER_ERROR);

			$row['end'] = $row['start']+$row['duration'];

			$ev_start = $row['start'];
			$ev_end = $row['end'];

			Base_RegionalSettingsCommon::set();
			$start_day = __date('D',$ev_start);
			$end_day = __date('D',$ev_end);
			Base_RegionalSettingsCommon::restore();

			$start_date = Base_RegionalSettingsCommon::time2reg($ev_start,false);
			$end_date = Base_RegionalSettingsCommon::time2reg($ev_end,false);
			$oneday = ($start_date==$end_date);
			if($oneday)
				$end_t = Base_RegionalSettingsCommon::time2reg($ev_end,2,false);

			$start_time = Base_RegionalSettingsCommon::time2reg($ev_start,2,false);
			$end_time = Base_RegionalSettingsCommon::time2reg($ev_end,2,false);
			if($start_date == Base_RegionalSettingsCommon::time2reg(time(),false))
				$start_t = __('Today').', '.$start_time;
			elseif($start_date == Base_RegionalSettingsCommon::time2reg(time()+3600*24,false))
				$start_t = __('Tomorrow').', '.$start_time;
			elseif($start_date == Base_RegionalSettingsCommon::time2reg(time()-3600*24,false))
				$start_t = __('Yesterday').', '.$start_time;
			else
				$start_t = $start_day.', '.$start_date.' '.$start_time;
			if(!$oneday)
				$end_t = $end_day.', '.$end_date.' '.$end_time;
		}

		if(isset($row['fake_duration']))
			$duration_str = Base_RegionalSettingsCommon::seconds_to_words($row['fake_duration']);
		elseif($row['duration']>0)
			$duration_str = Base_RegionalSettingsCommon::seconds_to_words($row['duration']);
		else
			$duration_str = '---';
		return array('duration'=>$duration_str,'start'=>$start_t,'end'=>$end_t,'start_time'=>$start_time,'end_time'=>$end_time,'start_date'=>$start_date,'end_date'=>$end_date,'start_day'=>$start_day,'end_day'=>$end_day);
	}

}

// ***** date("l") *****
// __('Monday')
// __('Tuesday')
// __('Wednesday')
// __('Thursday')
// __('Friday')
// __('Saturday')
// __('Sunday')
// ***** date("D") *****
// __('Mon')
// __('Tue')
// __('Wed')
// __('Thu')
// __('Fri')
// __('Sat')
// __('Sun')
// ***** date("F") *****
// __('January')
// __('February')
// __('March')
// __('April')
// __('May')
// __('June')
// __('July')
// __('August')
// __('September')
// __('October')
// __('November')
// __('December')
// ***** date("M") *****
// __('Jan')
// __('Feb')
// __('Mar')
// __('Apr')
// __('May')
// __('Jun')
// __('Jul')
// __('Aug')
// __('Sep')
// __('Oct')
// __('Nov')
// __('Dec')

function __date($f, $v) {
	return _V(date($f, $v)); // ****** Translation of pre-defined date formats
}

?>
