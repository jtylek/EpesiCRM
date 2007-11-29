<?php
/**
 * CRMCalendar class.
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

class CRM_Calendar_View_Day extends Module {
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
		global $database;
		$tmp;
		$this->first_day = 1;
		$this->activities = array();
		$this->max_per_day = 2;
		$this->nearest_delim = 8;
		$this->settings['start_day'] = Base_User_SettingsCommon::get('CRM/Calendar', 'start_day');
		$this->settings['end_day'] = Base_User_SettingsCommon::get('CRM/Calendar', 'end_day');;
		$this->settings['grid_morning'] = 1;
		$this->settings['grid_day'] = 8;
		$this->settings['grid_evening'] = 1;
		$this->settings['display_type'] = 'per_day';
		$this->lang = $this->pack_module('Base/Lang');
		//admin mode?
		$tmp = 'month';
		if(Base_AclCommon::i_am_user()) {
			$ret = 0;
			$this->logged = 1;
			if(!$this->logged)
				$this->logged = 1;
			$this->admin = true;
			//print Base_User::get_User_login($this->logged);

		} else {
			$this->logged = -1;
		}
		CRM_Calendar_Utils_SidetipCommon::load();
	}

	//ADD EVENT
	public function add_event($date, $time = null) {
		$event = & $this->init_module('CRM/Calendar/Event');
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
		$event = & $this->init_module('CRM/Calendar/Event');
		return $event->delete_event($event_type, $event_id);
	}
	////////////////////////////////////////////////////////////////////////////
	// week view
	/////////////////////////////////////////////////////////////////////////////
	public function extract_timeless_from_day(&$event_groups) {
		$event = array();
		$g = 0;
		foreach($event_groups as $module => $args) {
			$events = $args['events']['timeless'];
			if(isset($events) && is_array($events)) {
				if(CRM_Calendar_Utils_FuncCommon::get_settings('show_event_types') == 1)
					$event[] = array('brief'=>$args['title'], 'full'=>$args['description'], 'div_id'=>$module);
				foreach($events as $key=>$EV) {
					$g++;
					$event[$g] = array();

					$event[$g]['brief'] = '';
					$event[$g]['full'] = call_user_func(array($module.'Common', 'get_text'), $EV, 'full');

					// special priviliges
					if($this->logged > 0) {
						$event[$g]['full'] .= '<br>';
						// edit
						if($EV['access'] == 0 || $EV['created_by'] == $this->logged)
							$event[$g]['full'] .= '<a '.$this->parent->create_callback_href(array($this, 'edit_event'), array($module, $EV['id'])).' class=icon><img border="0" width="14" height="14" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', 'icon-edit.gif').'></a> ';
						// details
						if($EV['access'] <= 1 || $EV['created_by'] == $this->logged)
							$event[$g]['full'] .= '<a '.$this->parent->create_callback_href(array($this, 'details_event'), array($module, $EV['id'])).' class=icon><img border="0" width="14" height="14" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', "icon-view.gif").'></a> ';
						// delete
						if($EV['access'] == 0 || $EV['created_by'] == $this->logged)
							$event[$g]['full'] .= '<a '.$this->parent->create_confirm_callback_href('Are you sure, you want to delete this event?', array($this, 'delete_event'), array($module, $EV['id'])).' class=icon><img  border="0" width="14" height="14" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', 'icon-delete.gif').'></a> ';

					}
					$event[$g]['brief'] .= call_user_func(array($module.'Common', 'get_text'), $EV, 'line');

					$div_id = generate_password(4);
					$div_id = sprintf('%s_%4d%2d%2d0000X%d', $div_id, $this->date['year'], $this->date['month'], $this->date['day'], $EV['id']);
					$event[$g]['div_id'] = $div_id;
					CRM_Calendar_Utils_SidetipCommon::create($div_id, $div_id, $event[$g]['full'], 'vertical');
				}
			}
		}
		return $event;
	}
	public function extract_hour_events_from_day($event_groups, $from, $to) {
		$event = array();
		$g = 0;
		foreach($event_groups as $module => $args) {
			$events = $args['events']['regular'];
			for($hour = $from; $hour < $to; $hour++) {
				$idx = sprintf("%02d", $hour);
				if(isset($events[$idx]) && is_array($events[$idx])) {
					//$event[] = array('brief'=>$args['title'], 'full'=>$args['description'], 'div_id'=>$module.$idx);
					foreach($events[$idx] as $key=>$events_array) {
						foreach($events_array as $EV) {
							$g++;
							$event[$g] = array();
							$event[$g]['brief'] = '';
							$event[$g]['full'] = call_user_func(array($module.'Common', 'get_text'), $EV, 'full');

							// special priviliges
							if($this->logged > 0) {
								$event[$g]['full'] .= '<br>';
								// edit
								if($EV['access'] == 0 || $EV['created_by'] == $this->logged)
									$event[$g]['full'] .= '<a '.$this->parent->create_callback_href(array($this, 'edit_event'), array($module, $EV['id'])).' class=icon><img  border="0" width="14" height="14" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', 'icon-edit.gif').'></a> ';
								// details
								if($EV['access'] <= 1 || $EV['created_by'] == $this->logged)
									$event[$g]['full'] .= '<a '.$this->parent->create_callback_href(array($this, 'details_event'), array($module, $EV['id'])).' class=icon><img  border="0" width="14" height="14" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', "icon-view.gif").'></a> ';
								// delete
								if($EV['access'] == 0 || $EV['created_by'] == $this->logged)
									$event[$g]['full'] .= '<a '.$this->parent->create_confirm_callback_href('Are you sure, you want to delete this event?', array($this, 'delete_event'), array($module, $EV['id'])).' class=icon><img  border="0" width="14" height="14" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', 'icon-delete.gif').'></a> ';

							}
							$event[$g]['brief'] .= call_user_func(array($module.'Common', 'get_text'), $EV, 'line');

							$div_id = 'daylistevent';
							$div_id = sprintf('%s_%sX%d', $div_id, $EV['datetime_start'], $EV['id']);
							$event[$g]['div_id'] = $div_id;
							CRM_Calendar_Utils_SidetipCommon::create($div_id, $div_id, $event[$g]['full'], 'vertical');
						}
					}
				}
			}
		}
		return $event;
	}

	public function show_calendar_day($date) {

			$theme = & $this->pack_module('Base/Theme');
			load_js('modules/CRM/Calendar/View/Day/js/Day.js');
			load_js('modules/CRM/Calendar/dnd.js');

			$current_month = CRM_Calendar_Utils_FuncCommon::name_of_month( $date['month'] );
			$header_weekday = array();
			$header_day = array();
			$tt = array();

			$events = CRM_Calendar_EventCommon::get_day($date);
			$timeless_events = $this->extract_timeless_from_day($events);
			$id = sprintf('daylist_%4d%02d%02dtt', $date['year'], $date['month'], $date['day']);
			$has_events = '0';
			if(count($timeless_events) > 0)
				$has_events = 'has_events';
			$timeless_events = array(
				'has_events'=>$has_events, 'id'=>$id, 'event'=>$timeless_events,
				'add'=>$this->create_callback_href_js(array($this,'add_event'),array('date'=>array('year'=>$date['year'], 'month'=>$date['month'], 'day'=>$date['day'])))
			);

			eval_js('CRMCalendarDND.add_containment("'.$id.'")');

			$i = CRM_Calendar_Utils_FuncCommon::day_of_week_r(CRM_Calendar_Utils_FuncCommon::today());
			$limit = CRM_Calendar_Utils_FuncCommon::days_in_month_r($date);
			$day_of_week = CRM_Calendar_Utils_FuncCommon::translate($i);

			print $this->get_module_variable_or_unique_href_variable('view_style'); // <hr>
			// header
			$header_month = array(
					'info'=>
						'<a '.$this->parent->create_unique_href( array('action'=>'show', 'view_style'=>'month', 'date'=>array('year'=>$date['year'], 'month'=>$date['month'], 'day'=>1)) ).'>'.
						CRM_Calendar_Utils_FuncCommon::name_of_month( $date['month'] )
						."</a>"
			);


			$today = CRM_Calendar_Utils_FuncCommon::today();
			// check if day to display is today's day
			if(intval($date['year']) == intval($today['year']) && intval($date['month']) == intval($today['month']) && intval($date['day']) == intval($today['day']))
				$cnt['class'] = "today";
			else
				$cnt['class'] = "day";
			$cnt['info'] = Utils_TooltipCommon::create("<a ".$this->create_callback_href(array($this,'add_event'),array('date'=>$date))."><table width=100%><tr><td width=50% align=left><span class=day_number >".$date['day']."</span></td>".
				"<td width=50% align=right>".$this->name_of_day_abbr[CRM_Calendar_Utils_FuncCommon::day_of_week_r($date)]."</td></tr></table></a>", "Click to add new event on ".$date['day']." ".$date['month'].".");
			$header_day = array('info'=>$cnt['info'], 'class'=>$cnt['class']);

			// header's end
			for($j = 0; $j < 24; ) {
				$midday = "";
				$x = $j;
				if($j < $this->settings['start_day']) {
					if($x + round($this->settings['start_day'] / $this->settings['grid_morning']) <= $this->settings['start_day'])
						$x = $j + round($this->settings['start_day'] / $this->settings['grid_morning']);
					else
						$x = $this->settings['start_day'];
				} else if($j < $this->settings['end_day']) {
					$midday = "midday_";
					if($x + round(($this->settings['end_day']-$this->settings['start_day']) / $this->settings['grid_day']) <= $this->settings['end_day']){
						$x = $j + round(($this->settings['end_day']-$this->settings['start_day']) / $this->settings['grid_day']);
					} else
						$x = $this->settings['end_day'];
				} else {
					$x = $j + round((24-$this->settings['end_day']) / $this->settings['grid_evening']);
					if($x > 24)
						$x = 24;
				}
				$cnt = $j."<sup>00</sup>&nbsp;-&nbsp;" . $x . "<sup>00</sup>";
				if(Base_RegionalSettingsCommon::time_12h()) {
					if($j > 12)
						$cnt = ($j-12)."&nbsp;-&nbsp;";
					else
						$cnt = $j."&nbsp;-&nbsp;";
					//-----------------
					if($j == 0)
						$cnt = "12&nbsp;-&nbsp;";
					//-----------------
					if($x > 12)
						$cnt .= ($x-12)." pm";
					else
						$cnt .= $x." am";
				}
				
				$tt[] = array('info'=>$cnt, 'class'=>'hour', 'midday'=>$midday, 'event_num'=>0);

				$event = $this->extract_hour_events_from_day($events, $j, $x);
				$cnt = "<a style='position: absolute; float: right; align: right'".$this->create_callback_href(array($this,'add_event'),array('date'=>array('year'=>$date['year'], 'month'=>$date['month'], 'day'=>$date['day']), 'time'=>array('hour'=>$j, 'minute'=>'00'))).">+</a>";

				$id = sprintf( 'daylist_%4d%02d%02d%02d',$date['year'], $date['month'], $date['day'], $j);

				$has_events = '0';
				if(count($event) > 0)
					$has_events = 'has_events';
				$tt[] = array(
					'has_events'=>$has_events, 'class'=>'inter', 'id'=>$id, 'event'=>$event, 'midday'=>$midday,
					'add'=>$this->parent->create_callback_href_js(array($this,'add_event'),array('date'=>$date, 'time'=>array('hour'=>$j, 'minute'=>'00')))
				);

				eval_js('CRMCalendarDND.add_containment("'.$id.'")');

				if($j < $this->settings['start_day']) {
						$j = $j + round($this->settings['start_day'] / $this->settings['grid_morning']);
				} else if($j < $this->settings['end_day']) {
						$j = $j + round(($this->settings['end_day']-$this->settings['start_day']) / $this->settings['grid_day']);
				} else
					$j = $j + round((24-$this->settings['end_day']) / $this->settings['grid_evening']);
			}
			$theme->assign('header_month', $header_month);
			$theme->assign('header_day', $header_day);
			$theme->assign('timeless_event', $timeless_events);
			$theme->assign('tt', $tt);

			$theme->display();
			eval_js('CRMCalendarDND.create_containments()');
			CRM_Calendar_Utils_SidetipCommon::create_all();
	} //show calendar week

	///////////////////////////////////////////////////////////////////////////
	// MENUS ///////////////////////////////////////////////////////////

	////////////////////////////////////////////////////////////////////////

	/////////////////////////////////////////////////////////////////////////////
	public function menu($date) {
		print '<div class="day-menu"><table border="0"><tr>';
		$link_text = $this->create_unique_href_js(array( 'date'=>array('year'=>'__YEAR__', 'month'=>'__MONTH__', 'day'=>'__DAY__') ));

		$next = '<a class="button" '.$this->create_unique_href(array( 'date'=>CRM_Calendar_Utils_FuncCommon::next_day($date) )).'>Next day&nbsp;&nbsp;<img border="0" width="8" height="8" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', 'next.png').'></a>';
		$today = '<a class="button" '.$this->create_unique_href(array( 'date'=>CRM_Calendar_Utils_FuncCommon::today() )).'>Today&nbsp;&nbsp;<img border="0" width="8" height="8" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', 'this.png').'></a>';
		$prev = '<a class="button" '.$this->create_unique_href(array( 'date'=>CRM_Calendar_Utils_FuncCommon::prev_day($date) )).'><img border="0" width="8" height="8" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', 'prev.png').'>&nbsp;&nbsp;Previous day</a>';

		print '<td>' . $prev . '</td>';
		print '<td>' . $today. '</td>';
		print '<td>' . $next . '</td>';
		print '<td style="width: 10px;"></td><td>' . Utils_CalendarCommon::show('week_selector', $link_text) . '</td>';
		print '</tr></table></div><br>';
	} // calendar menu

	public function parse_links($date) {
		$action = $this->get_module_variable_or_unique_href_variable('action', '');
		$subject = $this->get_module_variable_or_unique_href_variable('subject', '');
		switch($action) {
			case 'edit':
				$event = & $this->init_module('CRM/Calendar/Event/Personal');
				$this->display_module($event, array(array('action'=>'edit', 'subject'=>$subject)));
				break;
			case 'details':
				$event = & $this->init_module('CRM/Calendar/Event/Personal');
				$this->display_module($event, array(array('action'=>'details', 'subject'=>$subject)));
				break;
			case 'show':
			default:
				$this->show_calendar_day($date);
		}
	}
	// BODY //////////////////////////////////////////////////////////////////////////////////////////////////////
	public function body($arg = null) {
		$this->init();
		print 
		$this->date = $this->get_unique_href_variable('date', CRM_Calendar_Utils_FuncCommon::today());
		if(!isset($arg['date'])) {

			$def = CRM_Calendar_Utils_FuncCommon::begining_of_week_r(CRM_Calendar_Utils_FuncCommon::today());
			if(Base_User_SettingsCommon::get('CRM/Calendar', 'default_today') == 1)
				$def = CRM_Calendar_Utils_FuncCommon::today();

			$this->date = $this->get_module_variable_or_unique_href_variable('date', $def);
			if(!is_array($this->date))
				$this->date = $def;
			if(!$this->isset_module_variable('first_run')) {
				$this->set_module_variable('first_run', 1);
				$this->date = CRM_Calendar_Utils_FuncCommon::today();
			}
		} else {
			$this->date = $arg['date'];
		}

		$this->menu($this->date);
		$this->parse_links($this->date);
		Base_ActionBarCommon::add('add',$this->lang->t('Add Event'), $this->create_callback_href(array($this,'add_event'),array($this->date)));

	}

}
?>
