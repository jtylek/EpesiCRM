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

class CRM_Calendar_View_Week extends Module {
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
	private $tmp;
	private $event_access;
	private $max_per_day;
	private $nearest_delim;
	private $settings;
	private $lang;

	private function init() {
		$this->lang = & $this->pack_module('Base/Lang');
		$tmp;
		$this->first_day = CRM_Calendar_Utils_FuncCommon::get_settings('first_day');
		$this->activities = array();
		$this->max_per_day = 2;
		$this->nearest_delim = 8;
		$this->settings['start_day'] = CRM_Calendar_Utils_FuncCommon::get_settings('start_day');
		$this->settings['end_day'] = CRM_Calendar_Utils_FuncCommon::get_settings('end_day');
		$this->settings['display_type'] = 'per_day';
		//admin mode?
		$tmp = 'month';
		if(Acl::is_user()) {
			$ret = 0;
			$this->logged = 0;
			if(!$this->logged)
				$this->logged = Acl::get_user();
			$this->admin = true;
			//print Base_User::get_User_login($this->logged);

		} else {
			$this->logged = -1;
		}
		CRM_Calendar_Utils_SidetipCommon::load();
		/*$this->view_style = $this->get_module_variable_or_unique_href_variable('view_style', $tmp);

		$this->date = array();
		//
		$this->date['year'] = $this->get_module_variable_or_unique_href_variable('date_year', date("Y"));
		//
		$this->date['month'] = $this->get_module_variable_or_unique_href_variable('date_month', date("m"));
		//
		$this->date['day'] = $this->get_module_variable_or_unique_href_variable('date_day', date("d"));
		$this->week = $this->get_module_variable_or_unique_href_variable('week', CRM_Calendar_Utils_FuncCommon::week_of_year_r($this->date));
		$this->max_per_day = $this->get_module_variable_or_unique_href_variable('max_per_day', CRM_Calendar_Utils_FuncCommon::get_settings('max_per_day'));
		//$this->parent->set_module_variable('view_style', 'month');
		*/
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
	public function create_month_header($start) {
		$header_month = array();
		$header_month[0] = array(
				'colspan' => 7,
				'info'=>
					'<a '.$this->parent->create_unique_href( array('action'=>'show', 'view_style'=>'month', 'date'=>array('year'=>$start['year'], 'month'=>$start['month'], 'day'=>1), 'direct'=>'yes') ).'>'.
					CRM_Calendar_Utils_FuncCommon::name_of_month( $start['month'], 2 ) . " &bull; ".$start['year'] . "</a>"
		);

		$limit = CRM_Calendar_Utils_FuncCommon::days_in_month_r($start);
		if($start['day'] + 6 > $limit) {
			if($start['month'] == 12) {
				$start['month'] = 1 ;
				$start['year']++;
				$current_month = $this->name_of_month_abbr[ $start['month'] ]." &bull; ".$start['year'];
			} else {
			 	$start['month']++;
			 	$current_month = $this->name_of_month_abbr[ $start['month'] ];
			}
			$header_month[1] = array(
				'colspan' => 0,
				'info'=>
					'<a '.$this->parent->create_unique_href( array('action'=>'show', 'view_style'=>'month', 'date'=>array('year'=>$start['year'], 'month'=>$start['month'], 'day'=>1), 'direct'=>'yes') ).'>'.
					CRM_Calendar_Utils_FuncCommon::name_of_month( $start['month'] ) . " &bull; ".$start['year'] .
					"</a>"
			);
			$span = 7;
			if($limit - $start['day'] < 7)
				$span = $limit - $start['day'] + 1;
			$header_month[0]['colspan'] = $span;
			$header_month[1]['colspan'] = 7 - $span;
		}
		return $header_month;
	}
	public function create_day_header($start) {
		$new_month = true;
		$header_day = array();
		$i = CRM_Calendar_Utils_FuncCommon::day_of_week_r(CRM_Calendar_Utils_FuncCommon::today());
		$limit = CRM_Calendar_Utils_FuncCommon::days_in_month_r($start);
		$b = CRM_Calendar_Utils_FuncCommon::translate($i) - 1;
		$current_month = CRM_Calendar_Utils_FuncCommon::name_of_month( $start['month'] );
		for($i = $start['day'], $a = 0; $i < $start['day']+7; $i++, $a++) {
			$current_day = $i ;
			$cnt = array();

			// determine end of month
			if($current_day > $limit) {
				$current_day -= $limit;
				if($new_month) {
					if($start['month'] == 12) {
						$start['month'] = 1 ;
						$start['year']++;
					} else {
					 	$start['month']++;
					}
					$current_month = $this->name_of_month_abbr[ $start['month'] ];
				}
				$new_month = false;
			}
			$today = CRM_Calendar_Utils_FuncCommon::today();
			// check if day to display is today's day
			if(intval($start['year']) == intval($today['year']) && intval($start['month']) == intval($today['month']) && intval($current_day) == intval($today['day']))
				$cnt['class'] = "today";
			else
				$cnt['class'] = "day";
			$k = CRM_Calendar_Utils_FuncCommon::day_of_week($start['year'], $start['month'], $current_day);

			$cnt['info'] = "<a ".$this->parent->create_unique_href( array('action'=>'show', 'view_style'=>'day', 'date'=>array('year'=>$start['year'], 'month'=>$start['month'], 'day'=>$current_day)) )."><table width=100%><tr>".
				'<td width="50%" align="left">' . CRM_Calendar_Utils_FuncCommon::name_of_day($k) . '</td>' .
				'<td width="50%" align="right"><span class="day_number">' . $current_day . '</span></td>' . '</tr></table></a>';

			//$cnt['info'] .= Utils_TooltipCommon::create("<a ".$this->create_callback_href(array($this,'add_event'),array('date'=>array('year'=>$start['year'], 'month'=>$start['month'], 'day'=>$current_day))).">add</a>", "Click to add new event on $current_day $current_month.");
			$header_day[] = array('info'=>$cnt['info'], 'week'=>CRM_Calendar_Utils_FuncCommon::name_of_day($k), 'class'=>$cnt['class']);
		}
		return $header_day;
	}

	// exxtracting events from tables
	public function extract_timeless_from_week(&$event_groups, $day) {
		$event = array();
		$g = 0;
		foreach($event_groups as $module => $args) {
			$events = $args['events']['timeless'];
			//print '<div align = left><pre>';
			//print_r($events);
			//print '</pre></div>';
			if(isset($events[$day]) && is_array($events[$day])) {
				if(CRM_Calendar_Utils_FuncCommon::get_settings('show_event_types') == 1)
					$event[] = array('brief'=>$args['title'], 'full'=>$args['description'], 'div_id'=>$module.$day);
				foreach($events[$day] as $key=>$EV) {
					$g++;
					$div_id = generate_password(4);
					$div_id = sprintf('%s_%04d%02d%02d0000X%d', $div_id, $this->date['year'], $this->date['month'], $this->date['day'], $EV['id']);

					$event[$g] = array();
					$event[$g]['full'] = call_user_func(array($module.'Common', 'get_text'), $EV, 'full');
					$more = '';

					// special priviliges
					if($this->logged > 0) {
						// edit
						if($EV['access'] == 0 || $EV['created_by'] == $this->logged)
							$more .= '<a '.$this->parent->create_callback_href(array($this, 'edit_event'), array($module, $EV['id'])).' class=icon><img style="vertical-align: middle;" border="0" width="14" height="14" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', 'icon-edit.gif').'></a> ';
						// details
						if($EV['access'] <= 1 || $EV['created_by'] == $this->logged)
							$more .= '<a '.$this->parent->create_callback_href(array($this, 'details_event'), array($module, $EV['id'])).' class=icon><img style="vertical-align: middle;" border="0" width="14" height="14" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', "icon-view.gif").'></a> ';
						// delete
						if($EV['access'] == 0 || $EV['created_by'] == $this->logged)
							$more .= '<a '.$this->parent->create_confirm_callback_href('Are you sure, you want to delete this event?', array($this, 'delete_event'), array($module, $EV['id'])).' class=icon><img  border="0" width="14" height="14" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', 'icon-delete.gif').'></a> ';

						if($EV['access'] <= 1 || $EV['created_by'] == $this->logged)
							$more .= '<br>';
					}
					$more .= call_user_func(array($module.'Common', 'get_text'), $EV, 'edit');
					$event[$g]['more'] = '<img style="vertical-align: middle;" id="'.$div_id.'_more" border="0" width="14" height="14" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', 'info.png').'>';

					//$event[$g]['brief'] = call_user_func(array($module.'Common', 'get_text'), $EV, 'brief');
					$event[$g]['time'] = call_user_func(array($module.'Common', 'get_text'), $EV, 'time');
					$event[$g]['title'] = '<a '.$this->parent->create_callback_href(array($this, 'details_event'), array($module, $EV['id'])).'>'.call_user_func(array($module.'Common', 'get_text'), $EV, 'title').'</a> ';
					$event[$g]['move'] = '<img  border="0" width="14" height="14" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', 'grab.png').'>';

					$event[$g]['div_id'] = $div_id;
					//CRM_Calendar_Utils_SidetipCommon::create($div_id.'_brief', $div_id, $event[$g]['full']);
					CRM_Calendar_Utils_SidetipCommon::create($div_id.'_time', $div_id, $event[$g]['full']);
					CRM_Calendar_Utils_SidetipCommon::create($div_id.'_title', $div_id, $event[$g]['full']);
					CRM_Calendar_Utils_SidetipCommon::create($div_id.'_more', $div_id, $more);
				}
			}
		}
		return $event;
	}
	public function extract_hour_events_from_week($event_groups, $day, $from, $to) {
		$event = array();
		$g = 0;
		foreach($event_groups as $module => $args) {
			$events = $args['events']['regular'];
			for($hour = $from; $hour < $to; $hour++) {
				$idx = sprintf("%02d%02d", $day, $hour);
				if(isset($events[$idx]) && is_array($events[$idx])) {
					if(CRM_Calendar_Utils_FuncCommon::get_settings('show_event_types') == 1)
						$event[] = array('brief'=>$args['title'], 'full'=>$args['description'], 'div_id'=>$module.$idx);
					foreach($events[$idx] as $key=>$events_array) {
						foreach($events_array as $EV) {
							$g++;
							$event[$g] = array();
							$div_id = 'daylistevent';
							$div_id = sprintf('%s_%sX%d', $div_id, $EV['datetime_start'], $EV['id']);

							$event[$g]['full'] = call_user_func(array($module.'Common', 'get_text'), $EV, 'full');
							$more = '';

							// special priviliges
							if($this->logged > 0) {
								// edit
								//if($EV['access'] == 0 || $EV['created_by'] == $this->logged)
								//	$more .= '<a '.$this->parent->create_callback_href(array($this, 'edit_event'), array($module, $EV['id'])).' class=icon><img style="vertical-align: middle;" border="0" width="14" height="14" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', 'icon-edit.gif').'></a> ';
								// details
								if($EV['access'] <= 1 || $EV['created_by'] == $this->logged)
									$more .= '<a '.$this->parent->create_callback_href(array($this, 'details_event'), array($module, $EV['id'])).' class=icon><img style="vertical-align: middle;" border="0" width="14" height="14" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', "icon-view.gif").'></a> ';
								// delete
								if($EV['access'] == 0 || $EV['created_by'] == $this->logged)
									$more .= '<a '.$this->parent->create_confirm_callback_href('Are you sure, you want to delete this event?', array($this, 'delete_event'), array($module, $EV['id'])).' class=icon><img  border="0" width="14" height="14" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', 'icon-delete.gif').'></a> ';

								if($EV['access'] <= 1 || $EV['created_by'] == $this->logged)
									$more .= '<br>';
							}
							$more .= call_user_func(array($module.'Common', 'get_text'), $EV, 'edit');
							$event[$g]['more'] = '<img style="vertical-align: middle;" id="'.$div_id.'_more" border="0" width="14" height="14" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', 'info.png').'>';
							if($EV['access'] > 1 || $EV['created_by'] != $this->logged)
								$event[$g]['more'] = '<img style="vertical-align: middle; visibility: hidden" id="'.$div_id.'_more" border="0" width="14" height="14" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', 'info.png').'>';

							//$event[$g]['brief'] = call_user_func(array($module.'Common', 'get_text'), $EV, 'brief');
							$event[$g]['time'] = call_user_func(array($module.'Common', 'get_text'), $EV, 'time');
							$event[$g]['title'] = '<a '.$this->parent->create_callback_href(array($this, 'details_event'), array($module, $EV['id'])).'>'.call_user_func(array($module.'Common', 'get_text'), $EV, 'title').'</a> ';
							$event[$g]['move'] = '<img  border="0" width="14" height="14" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', 'grab.png').'>';
							$event[$g]['div_id'] = $div_id;

							//if($EV['created_by'] != Base_UserCommon::get_My_user_id())
							//	eval_js('crm_calendar_view_week__remove_element("'.$div_id.'")');
							//else
							//CRM_Calendar_Utils_SidetipCommon::create($div_id.'_brief', $div_id, $event[$g]['full']);
							CRM_Calendar_Utils_SidetipCommon::create($div_id.'_time', $div_id, $event[$g]['full']);
							CRM_Calendar_Utils_SidetipCommon::create($div_id.'_title', $div_id, $event[$g]['full']);
							CRM_Calendar_Utils_SidetipCommon::create($div_id.'_more', $div_id, $more);
						}
					}
				}
			}
		}
		return $event;
	}
	/////////////////////////////////////////////////////////////////////////////
	public function show_calendar_week($date = array()) {

			# initialize user settings
			$start_day=$this->settings['start_day'];
			$end_day=$this->settings['end_day'];

			$theme = & $this->pack_module('Base/Theme');
			load_js('modules/CRM/Calendar/View/Week/js/Week.js');
			load_js('modules/CRM/Calendar/dnd.js');

			eval_js('mini_calendar_week_visibleDetails = new Array();');
			$start = $date;
			$events = CRM_Calendar_EventCommon::get_7days($start);
			//$start = CRM_Calendar_Utils_FuncCommon::begining_of_week($date['year'], $date['week']);
			$limit = CRM_Calendar_Utils_FuncCommon::days_in_month_r($start);
			$i = CRM_Calendar_Utils_FuncCommon::day_of_week_r(CRM_Calendar_Utils_FuncCommon::today());
			$day_of_week = CRM_Calendar_Utils_FuncCommon::translate($i);
			$today = CRM_Calendar_Utils_FuncCommon::today();

			// month header
			$header_month = $this->create_month_header($start);

			// day header
			$header_day = $this->create_day_header($start);

			$t = $this->date['year'];
			$x = 0;
			// timeless events
			$timeless_tt = array();
			for($i = $start['day']; $i < $start['day']+7; $i++) {
				$cnt = '&nbsp;';
				$current_day = $i ;

				// check for new month
				if($current_day > $limit) {
					$current_day -= $limit;
					$tmp = $this->date['month']+1;
					if($tmp > 12)
						$tmp = 1;
					$date['month'] = $tmp;
				} else {
					$date['month'] = $this->date['month'];
				}
				$event = $this->extract_timeless_from_week($events, $current_day);
				$id = 'daylist_'.CRM_Calendar_EventCommon::make_containment_id($date['year'], $date['month'], $current_day);

				$class = "day";
				if(intval($date['year']) == intval($today['year']) && intval($date['month']) == intval($today['month']) && intval($current_day) == intval($today['day']))
					$class = "today";

				$has_events = '0';
				if(count($event) > 0)
					$has_events = 'has_events';
				$timeless_tt[] = array(
					'has_events'=>$has_events, 'class'=>$class, 'id'=>$id, 'event'=>$event,
					'add'=>$this->create_callback_href_js(array($this,'add_event'),array('date'=>array('year'=>$start['year'], 'month'=>$start['month'], 'day'=>$current_day)))
				);
				eval_js('CRMCalendarDND.add_containment("'.$id.'")');
			}

			// regular events
			for($j = 0; $j <= $end_day; $j++) {
				$midday = "";
				$x = $j;

				// START
				if($j < $start_day) {
					$x = $start_day;
				} else if($j < $end_day) {
					$midday = "midday_";
					$x = $j+1;
				} else {
					$x = 0;
				}

				// SLOT
				$cnt = $j."<sup>00</sup>&nbsp;-&nbsp;" . $x . "<sup>00</sup>";

				if(Base_RegionalSettingsCommon::time_12h()) {
					$jj=$j.':00:00';
					$xx=$x.':00:00';
					$cnt =date("g",strtotime($jj)).'-'.date("g a",strtotime($xx));
				}

				$tt[] = array('info'=>$cnt, 'event'=>array(), 'event_num'=>0, 'class'=>'hour', 'midday'=>$midday);
				for($i = $start['day']; $i < $start['day']+7; $i++) {
					$current_day = $i;
					// check for new month

					$date = $start;
					if($current_day > $limit) {
						$current_day -= $limit;
						$tmp = $start['month']+1;
						if($tmp > 12) {
							$tmp = 1;
							$date['year'] = $start['year']+1;
						}
						$date['month'] = $tmp;
					}
					$cnt = "<a style='position: absolute; float: right; align: right'".$this->parent->create_callback_href(array($this,'add_event'),array('date'=>array('year'=>$date['year'], 'month'=>$date['month'], 'day'=>$current_day), 'time'=>array('hour'=>$j, 'minute'=>'00'))).">+</a>";
					$event = $this->extract_hour_events_from_week($events, $current_day, $j, $x);
					$id = sprintf( 'daylist_%4d%02d%02d%02d',$date['year'], $date['month'], $current_day, $j);
					$class = "day";
					if(intval($date['year']) == intval($today['year']) && intval($date['month']) == intval($today['month']) && intval($current_day) == intval($today['day']))
						$class = "today";

					$has_events = '0';
					if(count($event) > 0)
						$has_events = 'has_events';
					$tt[] = array(
						'has_events'=>$has_events, 'class'=>$class, 'id'=>$id, 'event'=>$event, 'midday'=>$midday,
						'add'=>$this->parent->create_callback_href_js(array($this,'add_event'),array('date'=>array('year'=>$date['year'], 'month'=>$date['month'], 'day'=>$current_day), 'time'=>array('hour'=>$j, 'minute'=>'00')))
					);
					eval_js('CRMCalendarDND.add_containment("'.$id.'")');
				}
				if ($j<$start_day){
					$j=$start_day-1;
				}
			}

			$theme->assign('header_month', $header_month);
			$theme->assign('header_day', $header_day);
			$theme->assign('timeless_event', $timeless_tt);
			$theme->assign('tt', $tt);

			$theme->display();
			eval_js('CRMCalendarDND.create_containments()');
			CRM_Calendar_Utils_SidetipCommon::create_all();
			//eval_js('CRMCalendarDND.create_droppables()');
			//eval_js('crm_calendar_view_week__add_elements()');
	} //show calendar week

	///////////////////////////////////////////////////////////////////////////
	// MENUS ///////////////////////////////////////////////////////////

	////////////////////////////////////////////////////////////////////////

	/////////////////////////////////////////////////////////////////////////////
	public function menu($date) {

		print '<div class="week-menu">';
		print '<table border="0"><tr>';
		print '<td style="padding-left: 180px;"></td>';
		print '<td class="empty"></td>';

		$link_text = $this->create_unique_href_js(array( 'date'=>array('year'=>'__YEAR__', 'month'=>'__MONTH__', 'day'=>'__DAY__') ));
		$next = '<a class="button" '.$this->create_unique_href(array( 'date'=>CRM_Calendar_Utils_FuncCommon::next_day($date) )).'>Next day&nbsp;&nbsp;<img border="0" width="8" height="8" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', 'next.png').'></a>';
		$prev = '<a class="button" '.$this->create_unique_href(array( 'date'=>CRM_Calendar_Utils_FuncCommon::prev_day($date) )).'><img border="0" width="8" height="8" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', 'prev.png').'>&nbsp;&nbsp;Previous day</a>';

		$today = '<a class="button" '.$this->create_unique_href(array( 'date'=>CRM_Calendar_Utils_FuncCommon::begining_of_week_r( CRM_Calendar_Utils_FuncCommon::today() ))).'>Today&nbsp;&nbsp;<img border="0" width="8" height="8" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', 'this.png').'></a>';

		$next7 = '<a class="button" '.$this->create_unique_href(array( 'date'=>CRM_Calendar_Utils_FuncCommon::next_week($date) )).'>Next week&nbsp;&nbsp;<img border="0" width="8" height="8" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', 'next.png').'><img border="0" width="8" height="8" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', 'next.png').'></a>';
		$prev7 = '<a class="button" '.$this->create_unique_href(array( 'date'=>CRM_Calendar_Utils_FuncCommon::prev_week($date) )).'><img border="0" width="8" height="8" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', 'prev.png').'><img border="0" width="8" height="8" src='.Base_ThemeCommon::get_template_file('CRM_Calendar', 'prev.png').'>&nbsp;&nbsp;Previous week</a>';

		print '<td style="width: 10px;"></td>';
		print '<td>' . $prev7 . '</td>';
		print '<td>' . $prev . '</td>';
		print '<td>' . $today . '</td>';
		print '<td>' . $next . '</td>';
		print '<td>' . $next7 . '</td>';
		print '<td style="width: 10px;"></td>';
		print '<td>' . Utils_CalendarCommon::show('week_selector', $link_text) . '</td>';

		print '<td class="empty"></td>';
		print '<td class="add-info">Double&nbsp;click&nbsp;on&nbsp;cell&nbsp;to&nbsp;add&nbsp;event</td>';

		print '</tr></table></div>';



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
				$this->show_calendar_week($date);
		}
	}
	// BODY //////////////////////////////////////////////////////////////////////////////////////////////////////
	public function body($arg = null) {
		$this->init();
		if(!isset($arg['date'])) {
			$def = CRM_Calendar_Utils_FuncCommon::begining_of_week_r(CRM_Calendar_Utils_FuncCommon::today());
			if(Base_User_SettingsCommon::get('CRM/Calendar', 'default_today') == 1)
				$def = CRM_Calendar_Utils_FuncCommon::today();

			$this->date = $this->get_module_variable_or_unique_href_variable('date', $def);
		} else {
			$this->date = $arg['date'];
		}
		$this->menu($this->date);
		$this->parse_links($this->date);
		Base_ActionBarCommon::add('add',$this->lang->t('Add Event'), $this->parent->create_callback_href(array($this,'add_event'),array($this->date)));

	}

}
?>
