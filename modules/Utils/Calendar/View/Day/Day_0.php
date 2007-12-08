<?php
/**
 * UtilsCalendar class.
 *
 * Calendar module with support for managing events.
 *
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.99
 * @package tcms-mini
 */

/**
 * status: 0 - open; 1 - in progres; 3 - done; 4 - canceled
 * access: 0 - public; 1 - public, r/o; 2 - private (r/w for creator and related people)
 */

defined("_VALID_ACCESS") || die();

class Utils_Calendar_View_Day extends Module {
	private $date;
	private $name_of_month_abbr;
	private $name_of_day_abbr;
	private $available_view_style;
	private $first_day;
	private $view_style;
	private $admin;
	private $logged;
	private $activities;
	private $week;
	private $lang;
	private $event_access;
	private $max_per_day;
	private $nearest_delim;
	private $settings;
	private $func;

	private function init() {
		$this->first_day = 1;
		$this->activities = array();
		$this->max_per_day = 2;
		$this->nearest_delim = 8;
		$this->settings['start_day'] = Base_User_SettingsCommon::get('Utils/Calendar', 'start_day');
		$this->settings['end_day'] = Base_User_SettingsCommon::get('Utils/Calendar', 'end_day');;
		$this->settings['grid_morning'] = 1;
		$this->settings['grid_day'] = 8;
		$this->settings['grid_evening'] = 1;
		$this->settings['display_type'] = 'per_day';
		$this->lang = $this->pack_module('Base/Lang');
		if(Base_AclCommon::i_am_user()) {
			$this->logged = 1;
		} else {
			$this->logged = -1;
		}
	}

	public function add_event($date, $time = null) {
		$event = & $this->init_module('Utils/Calendar/Event');
		return $event->add_event($date, $time);
	}

	public function edit_event($event_type, $event_id) {
		$event = & $this->init_module($event_type);
		return $event->edit_event($event_id);
	}
	public function details_event($event_type, $event_id) {
		$this->pack_module($event_type, array(array('subject'=>$event_id, 'action'=>'details')));
		if($this->is_back()) return false;
		return true;
	}
	public function delete_event($event_type, $event_id) {
		$event = & $this->init_module('Utils/Calendar/Event');
		return $event->delete_event($event_type, $event_id);
	}


	public function show_calendar_day($date) {

			$theme = & $this->pack_module('Base/Theme');

			$timetable = array();

			$header_month = array(
					'info'=>
						'<a '.$this->parent->create_unique_href(array('action'=>'show', 'view_style'=>'month', 'date'=>array('year'=>$date['year'], 'month'=>$date['month'], 'day'=>1)) ).'>'
						.'</a>'
			);


			$cnt['info'] = Utils_TooltipCommon::create("<a ".$this->create_callback_href(array($this,'add_event'),array('date'=>$date))."><table width=100%><tr><td width=50% align=left><span class=day_number >".$date['day']."</span></td>".
				"<td width=50% align=right>"."Buuuu</td></tr></table></a>", "Click to add new event on ".$date['day']." ".$date['month'].".");
			$header_day = array('info'=>$cnt['info'], 'class'=>'mwahaha');

			for($j = 0; $j < 24; $j++) {
				$timetable[] = array('hours'=>$j.' - '.$j+1, 'events'=>array());
			}

			$theme->assign('header_month', $header_month);
			$theme->assign('header_day', $header_day);
			$theme->assign('timetable', $timetable);

			$theme->display();
	} //show calendar week

	public function menu($date) {

		print '<div class="day-menu">';
		print '<table border="0"><tr>';
		print '<td style="padding-left: 180px;"></td>';
		print '<td class="empty"></td>';

		$link_text = $this->create_unique_href_js(array( 'date'=>array('year'=>'__YEAR__', 'month'=>'__MONTH__', 'day'=>'__DAY__') ));

		$next = '<a class="button" '.$this->create_unique_href(array( 'date'=>$date )).'>Next day&nbsp;&nbsp;<img border="0" width="8" height="8" src='.Base_ThemeCommon::get_template_file('Utils_Calendar', 'next.png').'></a>';
		$today = '<a class="button" '.$this->create_unique_href(array( 'date'=>$date )).'>Today&nbsp;&nbsp;<img border="0" width="8" height="8" src='.Base_ThemeCommon::get_template_file('Utils_Calendar', 'this.png').'></a>';
		$prev = '<a class="button" '.$this->create_unique_href(array( 'date'=>$date )).'><img border="0" width="8" height="8" src='.Base_ThemeCommon::get_template_file('Utils_Calendar', 'prev.png').'>&nbsp;&nbsp;Previous day</a>';

		print '<td style="width: 10px;"></td>';
		print '<td>' . $prev . '</td>';
		print '<td>' . $today . '</td>';
		print '<td>' . $next . '</td>';
		print '<td style="width: 10px;"></td>';
		print '<td>' . Utils_PopupCalendarCommon::show('week_selector', $link_text) . '</td>';
		print '<td class="empty"></td>';
		print '<td class="add-info">Double&nbsp;click&nbsp;on&nbsp;cell&nbsp;to&nbsp;add&nbsp;event</td>';
		print '</tr></table></div>';

	} // calendar menu

	public function body($arg = null) {
		$this->init();
		$date = array('year'=>date('y'), 'month'=>date('m'), 'day'=>date('d'), 'week'=>date('W'));
		$this->menu($date);
		$this->show_calendar_day($date);
		Base_ActionBarCommon::add('add',$this->lang->t('Add Event'), $this->create_callback_href(array($this,'add_event'),array($this->date)));

	}

}
?>
