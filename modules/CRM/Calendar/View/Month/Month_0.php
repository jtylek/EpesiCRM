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

class CRM_Calendar_View_Month extends Module {
	private $date;
	private $first_day;
	private $view_style;
	private $logged;
	private $event_access;
	private $lang;
	public $parent_module;
		
	public function construct(&$par) {
		$this->parent_module = $par;
	}
	
	private function init() {
		//CRM_Calendar_Utils_FuncCommon::first_day = Base_User_SettingsCommon::get_user_settings('CRM/Calendar','first_day');
		$this->max_per_day = 2;
		
		$this->lang = & $this->pack_module('Base/Lang');
		//admin mode?
		$tmp = 'month';
		$this->logged = (Base_AclCommon::i_am_user() ? Base_UserCommon::get_my_user_id() : -1);
		$this->view_style = $this->get_module_variable_or_unique_href_variable('view_style', $tmp);	
		
		$this->date = array();
		$this->max_per_day = 3;
		//$this->parent->set_module_variable('view_style', 'month');
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
						$g++;
						$div_id = generate_password(4);
						$div_id = sprintf('%s_%4d%2d%2d0000X%d', $div_id, $this->date['year'], $this->date['month'], $this->date['day'], $EV['id']);
						
						$event[$g] = array();
						$event[$g]['full'] = call_user_func(array($module.'Common', 'get_text'), $EV, 'full');
						$more = call_user_func(array($module.'Common', 'get_text'), $EV, 'edit');
						$event[$g]['more'] = '<img id="'.$div_id.'_more" border="0" width="16" height="16" src="'.Base_ThemeCommon::get_template_file('CRM_Calendar', "icon-view.gif").'">';
									
						// special priviliges
						if($this->logged > 0) {
							$event[$g]['full'] .= '<br>'; 
							// edit 
							if($EV['access'] == 0 || $EV['created_by'] == $this->logged)
								$event[$g]['full'] .= '<a '.$this->parent->create_callback_href(array($this, 'edit_event'), array($module, $EV['id'])).' class=icon><img  border="0" width="32" height="32" src="'.Base_ThemeCommon::get_template_file('CRM_Calendar', 'icon-edit.png').'"></a> ';
							// details
							if($EV['access'] <= 1 || $EV['created_by'] == $this->logged)
								$event[$g]['full'] .= '<a '.$this->parent->create_callback_href(array($this, 'details_event'), array($module, $EV['id'])).' class=icon><img  border="0" width="32" height="32" src="'.Base_ThemeCommon::get_template_file('CRM_Calendar', "icon-view.png").'"></a> ';
							// delete
							if($EV['access'] == 0 || $EV['created_by'] == $this->logged)
								$event[$g]['full'] .= '<a '.$this->parent->create_confirm_callback_href('Are you sure, you want to delete this event?', array($this, 'delete_event'), array($module, $EV['id'])).' class=icon><img  border="0" width="32" height="32" src="'.Base_ThemeCommon::get_template_file('CRM_Calendar', 'icon-delete.png').'"></a> ';
						
						}
						$event[$g]['brief'] = call_user_func(array($module.'Common', 'get_text'), $EV, 'brief');
						$event[$g]['move'] = '<img  border="0" width="16" height="16" src="'.Base_ThemeCommon::get_template_file('CRM_Calendar', 'grab.png').'">';
						//print "<xmp>".$event[$g]['move']."</xmp>";
						$event[$g]['div_id'] = $div_id;
						CRM_Calendar_Utils_SidetipCommon::create($div_id.'_brief', $div_id, $event[$g]['full']);
						CRM_Calendar_Utils_SidetipCommon::create($div_id.'_more', $div_id, $more);
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
			//eval_js('mini_calendar_week_visibleDetails = new Array();');
				
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
					'cnt'=>CRM_Calendar_Utils_FuncCommon::name_of_day($a, 2)
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
				$txt = "<table width=100%><tr><td width=50% align=left><span class=day_number>".
					'<a '.$this->parent->create_unique_href( array('action'=>'show', 'view_style'=>'day', 'date'=>array('year'=>$date['year'], 'month'=>$date['month'], 'day'=>$current_day)) ).'>'.
					$current_day.
					"</a></span></td>". 
					"<td width=50% align=right><a class=new_event ".$this->create_callback_href(array($this,'add_event'),array('date'=>array('year'=>$date['year'], 'month'=>$date['month'], 'day'=>$current_day))).">add</a></td></tr></table>";
					
				// getting events for that day... in an optimized way now!
				$t = $date['year']*10000 + $date['month']*100 + $current_day; //array('year'=>$date['year'], 'month'=>$date['month'], 'day'=>$current_day)
				$event = $this->extract_day_events_from_month($events, $current_day);
				$counter++;
				//$days[] = array('class'=>$class, 'info'=>$txt, 'event_num'=>count($event), 'event'=>$event);
				$days[] = array('class'=>$class, 'info'=>'as', 'event_num'=>0, 'event'=>array());
					
			}
			
			// adding remaining empty slots
			for(;$i % 7 != 0; $i++) {
				$days[] = array('class'=>'empty', 'info'=>'&nbsp;', 'event_num'=>0, 'event'=>'');	
			}
			$weeks[] = $days;
			
			$theme->assign('header', $header);
			$theme->assign('weeks', $weeks);
			
			$theme->display();
			CRM_Calendar_Utils_SidetipCommon::create_all();
			
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
		// CALENDAR --------------------------------------------------
		$link_text = $this->create_unique_href_js(array( 'date'=>array('year'=>'__YEAR__', 'month'=>'__MONTH__', 'day'=>'__DAY__') ));
		
		print Utils_CalendarCommon::show('week_selector', $link_text);	
		
		// DROPDOWNS -------------------------------------------------
		$today = CRM_Calendar_Utils_FuncCommon::today();
		$this->date = $date;
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
		//TODO: Minicalendar with select levels
		//$this->display_module($dr);
		
		$curr = '';
		$pre = array();
		$post = array();
				
		for($i = 1; $i < $this->date['month']; $i++) {
			if($i == $today['month'] && $this->date['year'] == $today['year']) {
				array_push($pre, '<a '.$this->create_unique_href(array( 'date'=>array('year'=>$this->date['year'] ,'month'=>$i, 'day'=>1) )).'><font color=red>'.CRM_Calendar_Utils_FuncCommon::name_of_month($i, 2)."</font></a>");
			} else {
				array_push($pre, '<a '.$this->create_unique_href(array( 'date'=>array('year'=>$this->date['year'] ,'month'=>$i, 'day'=>1) )).'>'.CRM_Calendar_Utils_FuncCommon::name_of_month($i,2)."</a>");
			}
		}	
		$curr = '<b>'.CRM_Calendar_Utils_FuncCommon::name_of_month($i,2)."</b>";
		for($i = $this->date['month'] + 1; $i <= 12; $i++) {
			if($i == $today['month'] && $this->date['year'] == $today['year']) {
				array_push($post, '<a '.$this->create_unique_href(array( 'date'=>array('year'=>$this->date['year'] ,'month'=>$i, 'day'=>1) )).'><font color=red>'.CRM_Calendar_Utils_FuncCommon::name_of_month($i,2)."</font></a>");
			} else {
				array_push($post, '<a '.$this->create_unique_href(array( 'date'=>array('year'=>$this->date['year'] ,'month'=>$i, 'day'=>1) )).'>'.CRM_Calendar_Utils_FuncCommon::name_of_month($i,2)."</a>");
			}
		}
		$dr_m = & $this->init_module('CRM/Calendar/Utils/Dropdown');
		$dr_m->set_current($curr);
		$dr_m->set_values($post);
		$dr_m->set_pre_values($pre);
		$this->display_module($dr_m);
		
		$next = '<a '.$this->create_unique_href(array( 'date'=>CRM_Calendar_Utils_FuncCommon::next_month($date) )).'>Next >></a>';
		$today = '<a '.$this->create_unique_href(array( 'date'=>CRM_Calendar_Utils_FuncCommon::today() )).'>Today</a>';
		$prev = '<a '.$this->create_unique_href(array( 'date'=>CRM_Calendar_Utils_FuncCommon::prev_month($date) )).'><< Prevoius</a>';
		
		print $prev.' ';
		
		print $today;
		
		print ' '.$next;
		
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
				$this->show_calendar_month($date);
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
		Base_ActionBarCommon::add('add',$this->lang->t('Add Event'), $this->create_callback_href(array($this,'add_event'),array($this->date)));
		
	}
	
}
?>
