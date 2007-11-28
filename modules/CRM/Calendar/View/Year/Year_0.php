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

class CRM_Calendar_View_Year extends Module {
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
	private $func;
	public $parent_module;

	public function construct(&$par = null) {
		$this->parent_module = $par;
	}

	private function init() {
		$tmp;
		$this->activities = array();
		$this->max_per_day = 2;
		$this->nearest_delim = 8;
		$this->settings['start_day'] = 9;
		$this->settings['end_day'] = 17;
		$this->settings['grid_morning'] = 1;
		$this->settings['grid_day'] = 8;
		$this->settings['grid_evening'] = 1;
		$this->settings['display_type'] = 'per_day';
		//admin mode?
		$tmp = 'month';
		$this->lang = & $this->pack_module('Base/Lang');
		if(Base_AclCommon::i_am_user()) {
			$ret = 0;
			$this->logged = 1;
			if(!$this->logged)
				$this->logged = 1;
			$this->admin = true;

		} else {
			$this->logged = -1;
		}
		$this->view_style = $this->get_module_variable_or_unique_href_variable('view_style', $tmp);

		$this->date = array();
		$this->name_of_month_abbr = array(1=>'Jan', 2=>'Feb', 3=>'Mar', 4=>'Apr', 5=>'May', 6=>'Jun', 7=>'Jul', 8=>'Aug', 9=>'Sep', 10=>'Oct', 11=>'Nov', 12=>'Dec');
		$this->name_of_day_abbr = array(0=>'Sun', 1=>'Mon', 2=>'Tue', 3=>'Wed', 4=>'Thu', 5=>'Fri', 6=>'Sat');
		//
		$this->max_per_day = $this->get_module_variable_or_unique_href_variable('max_per_day', CRM_Calendar_Utils_FuncCommon::get_settings('max_per_day'));
		//
		$this->max_per_day = 3;
	}


	//ADD EVENT
	public function add_event($date) {
		if($this->is_back())
			return false;
		$this->lang = & $this->pack_module('Base/Lang');
		Base_ActionBarCommon::add('add',$this->lang->t('Back'), $this->create_back_href());
		$event = & $this->init_module('CRM/Calendar/Event');
		return $event->add_event($date);
		// TODO:
		//$ret = null;
		//$this->pack_module('CRM/Calendar/Event',array($date,& $ret));
		//return $ret;
	}
	public function edit_event($event_type, $event_id) {
		if($this->is_back())
			return false;
		$this->lang = & $this->pack_module('Base/Lang');
		Base_ActionBarCommon::add('add',$this->lang->t('Back'), $this->create_back_href());
		$event = & $this->init_module('CRM/Calendar/Event');
		return $event->edit_event($event_type, $event_id);
	}
	public function delete_event($event_type, $event_id) {
		if($this->is_back())
			return false;
		$this->lang = & $this->pack_module('Base/Lang');
		Base_ActionBarCommon::add('add',$this->lang->t('Back'), $this->create_back_href());
		$event = & $this->init_module('CRM/Calendar/Event');
		return $event->delete_event($event_type, $event_id);
	}
	//////////////////////////////////////////////////////////////////////////////
	// month view
	private function extract_day_events_from_month(&$event_groups, $day) {
		$event = array();
		$idx = sprintf("%02d", $day);
		$g = 0;
		foreach($event_groups as $module => $args) {
			$events = $args['events'];
			if(isset($events[$idx]) && is_array($events[$idx])) {
				if(CRM_Calendar_Utils_FuncCommon::get_settings('show_event_types') == 1)
					$event[] = array('brief'=>$args['title'], 'full'=>$args['description'], 'div_id'=>$module.$day);
				foreach($events[$idx] as $key=>$events_array) {
					foreach($events_array as $EV) {
						$event[] = call_user_func(array($module.'Common', 'get_text'), $EV, 'brief');
						/*
						$g++;
						$event[$g] = array();
						$event[$g]['full'] = call_user_func(array($module.'Common', 'get_full'), $EV);

						// special priviliges
						if($this->logged > 0) {
							$event[$g]['full'] .= '<br>';
							// edit
							if($EV['access'] == 0 || $EV['created_by'] == $this->logged || $EV['uid'] == $this->logged) {
								$event[$g]['full'] .= '<a '.$this->create_callback_href(array($this, 'edit_event'), array($module, $EV['id']))." class=icon><img src=".Base_ThemeCommon::get_template_file('CRM_Calendar_View_Month', "mini_edit.gif")."></a> ";
							}
							// details
							$event[$g]['full'] .= '<a '.$this->create_unique_href(array('type'=>$module, 'subject'=>$EV['id']))." class=icon><img src=".Base_ThemeCommon::get_template_file('CRM_Calendar_View_Month', "mini_details.gif")."></a>";
							// delete
							if($EV['access'] == 0 || $EV['created_by'] == $this->logged || $EV['uid'] == $this->logged) {
								$event[$g]['full'] .= '<a '.$this->create_confirm_callback_href('Are you sure, you want to delete this event?', array($this, 'delete_event'), array($module, $EV['id']))." class=icon><img src=".Base_ThemeCommon::get_template_file('CRM_Calendar_View_Month', "mini_done.gif")."></a> ";
							}
						}
						$event[$g]['brief'] = call_user_func(array($module.'Common', 'get_brief'), $EV);

						$div_id = generate_password(4);
						$div_id .= '_'.$g;
						$event[$g]['div_id'] = $div_id;
						*/
					}
				}
			}
		}
		return $event;
	}


	private function new_week_grid($date, $week) {
		return array(
					'class'=>'week_number',
					'info'=>
						'<a '.$this->parent->create_unique_href( array('action'=>'show', 'view_style'=>'week', 'date'=>array('year'=>$date['year'], 'month'=>$date['month'], 'day'=>$date['day'], 'week'=>$week), 'direct'=>'yes') ).'>'.
						$week
						."</a>",
					'event_num'=>0, 'event'=>'', 'div_id'=>''
				);
	}

	public function show_calendar_month($date) {

			$theme = & $this->pack_module('Base/Theme');

			//load_js('modules/CRM/Calendar/View/Month/js/Month.js');

			$days = array();
			$weeks = array();
			$header = array();

			// get events list for this month
			$events = CRM_Calendar_EventCommon::get_month($date);
			$i = CRM_Calendar_Utils_FuncCommon::starting_day_r($date);

			// header with day names
			$header[] = array('class'=>'none', 'cnt'=>'');
			for($a = 0; $a <= 6; $a++) {
				$header[] = array(
					'class'=>'header',
					'cnt'=>CRM_Calendar_Utils_FuncCommon::name_of_day($a, 1)
					);
			}

			$current_week = CRM_Calendar_Utils_FuncCommon::week_of_year($date['year'], $date['month'], 1);
			if(CRM_Calendar_Utils_FuncCommon::translate($i) != 0)
				$days[] = $this->new_week_grid($date, $current_week);

			// empty slots before month begins...
			$counter = 0;
			for(; $counter < CRM_Calendar_Utils_FuncCommon::translate($i); $counter++) {
				$days[] = array('class'=>'empty', 'info'=>'&nbsp;', 'event_num'=>0, 'event'=>'', 'div_id'=>'');
			}


			$b = CRM_Calendar_Utils_FuncCommon::translate($i) - 1;
			$limit = CRM_Calendar_Utils_FuncCommon::days_in_month_r($date);
			for($i = $b + 1; $i <= $limit + $b; $i++) {
				$current_day = $i - $b;
				if($counter % 7 == 0) {
					$current_week++;
					$weeks[] = $days;
					$days = array( $this->new_week_grid(array('year'=>$date['year'], 'month'=>$date['month'], 'day'=>$current_day), $current_week) );
				}

				// check if day to display is today's day
				$tmp = CRM_Calendar_Utils_FuncCommon::today();
				$class = ''; $txt = '';
				if($date['year'] == $tmp['year'] && $date['month'] == $tmp['month'] && $current_day == $tmp['day'])
					$class = "today";
				else
					$class = "day";

				// day number and regular stuff
				$txt = "<a class=new_event ".$this->parent->create_unique_href(array('date'=>array('year'=>$date['year'], 'month'=>$date['month'], 'day'=>$current_day), 'action'=>'show', 'view_style'=>'day' )).">$current_day.</a>";

				// getting events for that day... in an optimized way now!
				$t = $date['year']*10000 + $date['month']*100 + $current_day; //array('year'=>$date['year'], 'month'=>$date['month'], 'day'=>$current_day)
				$g = 0;
				$event = $this->extract_day_events_from_month($events, $current_day);

				$counter++;
				$div_id = generate_password(4);
				if(count( $event ) > 0 )
					$txt = Utils_TooltipCommon::create('<b>'.$txt.'</b>', join('<br>', $event));
				$days[] = array('class'=>$class, 'info'=>$txt);

			}

			// epmty grid after month
			for(;$i % 7 != 0; $i++)
				$days[] = array('class'=>'empty', 'info'=>'&nbsp;');
			$weeks[] = $days;

			$name = '<a '.$this->parent->create_unique_href( array('action'=>'show', 'view_style'=>'month', 'date'=>array('year'=>$date['year'], 'month'=>$date['month'], 'day'=>1), 'direct'=>'yes') ).'>'.
					CRM_Calendar_Utils_FuncCommon::name_of_month( $date['month'], 2 )
					.'</a>';

			$theme->assign('header', $header);
			$theme->assign('weeks', $weeks);
			$theme->assign('name', $name);

			$theme->display();



	} // show calendar month
	///////////////////////////////////////////////////////////////////////////
	// nearest events

	///////////////////////////////////////////////////////////////////////////
	public function show_calendar() {
		print $this->get_path() . "<br>";
		$this->menu_calendar($this->view_style);
		$this->unset_module_variable('week');
		$options = $this->get_module_variable_or_unique_href_variable('options', array('date'=>CRM_Calendar_Utils_FuncCommon::today()));
		$this->show_calendar_month($this->date);
		$this->set_module_variable('old_vs', 'month');
	}
	/////////////////////////////////////////////////////////////////////////////
	public function menu($date) {
		$menu = array();
		$menu_y = array();
		$today = CRM_Calendar_Utils_FuncCommon::today();
		$this->date = $date;
		if($this->date['year'] < $today['year'])
			$start = $this->date['year']-2;
		else
			$start = $today['year']-2;

		if($this->date['year'] > $today['year'])
			$end = $this->date['year']+2;
		else
			$end = $today['year']+2;
		for($i = $start; $i <= $end; $i++) {
			if($i-3 > $start && $i + 3 < $end) {
				if(end($menu_y) != "...")
					array_push($menu_y, "...");
			} else
				if($this->date['year'] != $i) {
					if($i == $today['year'])
						array_push($menu_y, '<a '.$this->create_unique_href(array( 'date'=>array('year'=>$i, 'month'=>$this->date['month']) )).'><font color=red>'.$i."</font></a>");
					else
						array_push($menu_y, '<a '.$this->create_unique_href(array( 'date'=>array('year'=>$i, 'month'=>$this->date['month']) )).'>'.$i."</a>");
				} else
					array_push($menu_y, '<b>'.$i."</b>");

		}
		//print join(" | ", $menu_y);
		$curr = '';
		$pre = array();
		$post = array();
		if($this->date['year'] < $today['year'])
			$start = $this->date['year']-4;
		else
			$start = $today['year']-4;

		if($this->date['year'] > $today['year'])
			$end = $this->date['year']+4;
		else
			$end = $today['year']+4;
		for($i = $start; $i < $this->date['year']; $i++) {
			if($i == $today['year'])
				array_push($pre, '<a '.$this->create_unique_href(array( 'date'=>array('year'=>$i, 'month'=>$this->date['month'], 'day'=>1) )).'><font color=red>'.$i."</font></a>");
			else
				array_push($pre, '<a '.$this->create_unique_href(array( 'date'=>array('year'=>$i, 'month'=>$this->date['month'], 'day'=>1) )).'>'.$i."</a>");
		}
		$curr = '<b>'.$i."</b>";
		for($i = $this->date['year'] + 1; $i <= $end; $i++) {
			if($i == $today['year'])
				array_push($post, '<a '.$this->create_unique_href(array( 'date'=>array('year'=>$i, 'month'=>$this->date['month'], 'day'=>1) )).'><font color=red>'.$i."</font></a>");
			else
				array_push($post, '<a '.$this->create_unique_href(array( 'date'=>array('year'=>$i, 'month'=>$this->date['month'], 'day'=>1) )).'>'.$i."</a>");
		}
		$dr = & $this->init_module('CRM/Calendar/Utils/Dropdown');
		$dr->set_current($curr);
		$dr->set_values($post);
		$dr->set_pre_values($pre);
		$this->display_module($dr);
	} // calendar menu
	// Settings ////////////////////////////////////////////////////////////////////////////////////////
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

				print '
					<!-- SHADIW BEGIN -->
					<div class="layer" style="padding: 9px; width: 740px;">
					<div class="content_shadow">
					<!-- -->
				';

				print '<table border="0" cellpadding="0" cellspacing="5" style="vertical-align: top; background-color: #FFFFFF;">';
				for($x = 1; $x <= 12; $x+=3 ) {
					print '<tr>';
					for($y = $x; $y < $x+3; $y++ ) {
						print '<td style="vertical-align: top">';
						$date['month'] = $y;
						$this->show_calendar_month($date);
						print '</td>';
					}
					print '</tr>';
				}
				print '</table>';

				print '
				<!-- SHADOW END -->
		 		</div>
				<div class="shadow-top">
					<div class="left"></div>
					<div class="center"></div>
					<div class="right"></div>
				</div>
				<div class="shadow-middle">
					<div class="left"></div>
					<div class="right"></div>
				</div>
				<div class="shadow-bottom">
					<div class="left"></div>
					<div class="center"></div>
					<div class="right"></div>
				</div>
				</div>
				<!-- -->
				';
		}
	}
	// BODY //////////////////////////////////////////////////////////////////////////////////////////////////////
	public function body($arg) {
		$this->init();
		if(!isset($arg['date'])) {
			$this->date = $this->get_unique_href_variable('date', CRM_Calendar_Utils_FuncCommon::today());
			if(!is_array($this->date))
				$this->date = CRM_Calendar_Utils_FuncCommon::today();
		} else {
			$this->date = $arg['date'];
		}

		$this->menu($this->date);
		$this->parse_links($this->date);
		Base_ActionBarCommon::add('add',$this->lang->t('Add Event'), $this->parent->create_callback_href(array($this,'add_event'),array(CRM_Calendar_Utils_FuncCommon::today())));
	}

}
?>
