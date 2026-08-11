<?php
/**
 * @author abisaga@telaxus.com
 * @copyright 2008 Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-applets
 * @subpackage monthview
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_MonthView extends Module {
	private $date;

	public function body() {

	}

	public function applet($conf, & $opts) {
		$opts['go'] = false;
		$this->date = $this->get_module_variable_or_unique_href_variable('date');
		if ($this->date==null) $this->date = date('Y-m-15');
		$this->set_module_variable('date', $this->date);
		$this->date = strtotime($this->date);

		Base_ThemeCommon::load_css('Applets_MonthView', 'fullcalendar-embed');

		// "mine" scope - same "my own records" semantics this applet always
		// had (previously CRM_Calendar_EventCommon::$filter='('.$me['id'].')'
		// set directly here), now applied server-side by
		// CRM_CalendarCommon::fullcalendar_events() instead.
		$events_feed_url = $this->create_ajax_callback_url(array('CRM_CalendarCommon', 'fullcalendar_events'), array('scope' => 'mine'));

		$day_click_href_js = function($date) {
			// Same jump_to_date/switch_to_tab deep link this applet's day
			// cells always used, plus open_add=1 so CRM_Calendar::body() also
			// opens the add-event flow automatically on arrival - the mini
			// calendar itself can't create events directly (see
			// CRM_Calendar_Event::add(), an empty stub - the real add flow
			// needs CRM_Calendar's own event-type picker), so this gets there
			// in one click instead of two.
			return Base_BoxCommon::create_href_js($this, 'CRM_Calendar', null, null, null, array('jump_to_date'=>$date, 'switch_to_tab'=>'Day', 'open_add'=>1));
		};

		// Constructed (not yet rendered - init_module() alone has no output)
		// before the "jump to date" trigger below, so its own get_path() is
		// available to compute the exact same mount_id Utils_Calendar::
		// fullcalendar() derives internally, letting the popup position
		// itself off the FullCalendar title instead of this trigger.
		$c = $this->init_module(Utils_Calendar::module_name(), array(
			CRM_Calendar_Event::module_name(),
			array(
				'engine' => 'fullcalendar',
				'compact' => true,
				'default_view' => 'Month',
				'default_date' => $this->date,
				'first_day_of_week' => Utils_PopupCalendarCommon::get_first_day_of_week(),
				'explicit_navigation' => true,
				// Forwards a click on the "August 2026" toolbar title to the
				// popup-calendar trigger printed below (.epesi-mv-jump's own
				// <a>) - frees the toolbar from also needing its own visible
				// icon for the same action.
				'title_click_forward_selector' => '.epesi-mv-jump a',
			),
			function($t, $tl) { return false; }, // no drag-to-create in this small embed
			$events_feed_url,
			null, // no write URL -> not draggable/resizable here
			$day_click_href_js,
		));

		// "Jump to date" trigger - the <a> itself is display:none (see
		// fullcalendar-embed.css), only ever activated programmatically via
		// the title-click forwarding above, never by its own visible affordance
		// - so its own geometry can't be used to position the popup (a
		// display:none element always reports an all-zero rect, and every
		// attempt at a "hidden but positionable" middle ground broke in ways
		// that weren't diagnosable without a live browser). $pos_js overrides
		// PopupCalendarCommon_0.php's default (which clonePosition()s off the
		// trigger itself) to target the FullCalendar title instead - always
		// visible, never hidden, and it's genuinely where the user just
		// clicked, so anchoring there is more correct anyway. 'utils-calendar-
		// fc-'.md5(...) duplicates Utils_Calendar::fullcalendar()'s own
		// $mount_id formula exactly (see that method) - $c->get_path() is
		// stable/deterministic, so this matches regardless of call order.
		$title_sel = '#utils-calendar-fc-'.md5($c->get_path()).' .fc-toolbar-title';
		$pos_js = 'var t=document.querySelector(\''.$title_sel.'\');if(t)jQuery(popup).clonePosition(t,{setWidth:false,setHeight:false,offsetTop:t.offsetHeight});';
		$link_text = $this->create_unique_href_js(array('date'=>'__YEAR__-__MONTH__-__DAY__'));
		// 'monthview_selector' (own name, not the generic 'week_selector' the
		// original code used) - Utils_PopupCalendarCommon::show()'s $name
		// becomes a literal DOM id ("datepicker_<name>_button" etc.), not
		// scoped per module instance, and 'week_selector' is also used
		// verbatim by CRM_Calendar's own legacy views, Utils_Planner, and
		// Utils_CalendarBusyReport - any of those left in the page (Epesi's
		// box-stack model doesn't necessarily destroy a popped module's DOM)
		// would collide via getElementById().
		print('<div class="epesi-mv-jump">'.Utils_PopupCalendarCommon::show('monthview_selector', $link_text, 'month', null, $pos_js, '').'</div>');

		$this->display_module($c);
	}

}

?>