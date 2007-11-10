<?php

/**
 *  Calendar
 * 
 * Author: Kuba Slawinski
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar extends Module {
	private $calendar;
	private $logged;
	private $month_calendar;
	private $week_calendar;
	private $default_module;
	public $modules;
	private $options = array();
	public $menu_main_tb;
	public $menu_main_show_tb;
	/////////////////////////////////////////////////////////////////////////////
	// DATE-COUNTING RELATED FUNCTIONS 
	/////////////////////////////////////////////////////////////////////////////
	/**
	 * mode: 
	 * 		0 - number (0 being Sunday, 1 - Monday)
	 * 		1 - day
	 * 		2 - abbr. day	
	 */
	public function init() {
		$this->lang = & $this->init_module('Base/Lang');
		
		$this->menu_main_show_tb = & $this->init_module('Utils/TabbedBrowser');
		
		// tabbed browsers:
		
		// TODO: perhaps better to load modules when needed, not every time.
		$this->modules['month'] 	= & $this->init_module('CRM/Calendar/View/Month', array($this));
		$this->modules['week'] 		= & $this->init_module('CRM/Calendar/View/Week');
		$this->modules['year'] 		= & $this->init_module('CRM/Calendar/View/Year');
		$this->modules['day'] 		= & $this->init_module('CRM/Calendar/View/Day');
		$this->modules['agenda'] 	= & $this->init_module('CRM/Calendar/View/Agenda');
		
		$this->default_module = Base_User_SettingsCommon::get('CRM_Calendar', 'view_style');
		$this->logged = 0;
		if(Base_AclCommon::i_am_user())
			$this->logged = Base_UserCommon::get_my_user_id();
	}
	
	
	///////////////////////////////////////////////////////////////////////////////
	//ADD EVENT
	public function add_event($date, $time = null) {
		$event = & $this->init_module('CRM/Calendar/Event');
		return $event->add_event($date, $time);
	}
	
	public function edit_event($event_type, $event_id) {
		$event = & $this->init_module($event_type);
		return $event->edit_event($event_id);
		/*$this->display_module($event, array(array('subject'=>$event_id, 'action'=>'edit')));
		$tere = $this->last_returned();
		$tere ? print 'ev true' : print 'ev false';
		return $tere;*/
	}
	public function details_event($event_type, $event_id) {
		//$event = & $this->init_module($event_type);
		$this->pack_module($event_type, array(array('subject'=>$event_id, 'action'=>'details')));
		if($this->is_back()) return false;
		return true;
	}
	public function delete_event($event_type, $event_id) {
		$event = & $this->init_module('CRM/Calendar/Event');
		return $event->delete_event($event_type, $event_id);
	}
	/////////////////////////////////////////////////////////////////////////////////////////
	// SHOW CALENDARS
	public function show_calendar_month() {
		$this->set_module_variable('view_style', 'month');
		$date = $this->get_unique_href_variable('date', '');
		$this->display_module($this->modules['month'], array(array('date'=>$date)) );
	}
	public function show_calendar_week() {
		$this->set_module_variable('view_style', 'week');
		$date = $this->get_unique_href_variable('date', '');
		$this->display_module($this->modules['week'], array(array('date'=>$date)) );
	}
	public function show_calendar_year() {
		$this->set_module_variable('view_style', 'year');
		$date = $this->get_unique_href_variable('date', '');
		$this->display_module($this->modules['year'], array(array('date'=>$date)) );
	}
	public function show_calendar_day() {
		$this->set_module_variable('view_style', 'day');
		$date = $this->get_unique_href_variable('date', '');
		$this->display_module($this->modules['day'], array(array('date'=>$date)) );
	}
	public function show_calendar_agenda() {
		$this->set_module_variable('view_style', 'agenda');
		$date = $this->get_unique_href_variable('date', '');
		$this->display_module($this->modules['agenda'], array(array('date'=>$date)) );
	}
	
	
	// MENUS
	public function menu_main_show() {
		$this->menu_main_show_tb->set_tab( $this->lang->t('Agenda'),array($this, 'show_calendar_agenda') );
		$this->menu_main_show_tb->set_tab( $this->lang->t('Day'),array($this, 'show_calendar_day') );
		$this->menu_main_show_tb->set_tab( $this->lang->t('Week'),array($this, 'show_calendar_week') );
		$this->menu_main_show_tb->set_tab( $this->lang->t('Month'),array($this, 'show_calendar_month') );
		$this->menu_main_show_tb->set_tab( $this->lang->t('Year'),array($this, 'show_calendar_year') );
		$this->menu_main_show_tb->set_default_tab($this->default_module);
	}
	
	public function parse_links() {
		$action = $this->get_unique_href_variable('action', 'show');
		switch($action) {
			case 'add':
				print 'adding ';
				$this->menu_main_tb->switch_tab(1);
				//location(array());
				break;
			case 'show':
			default:
				// it has a set of subtabs
				$view_style = $this->get_module_variable_or_unique_href_variable('view_style', $this->default_module);
				switch($view_style) {
					case 'agenda':
						$this->menu_main_show_tb->switch_tab(0);
						break;
					case 'day':
						$this->menu_main_show_tb->switch_tab(1);
						break;
					case 'week':
						$this->menu_main_show_tb->switch_tab(2);
						break;
					case 'month':
						$this->menu_main_show_tb->switch_tab(3);
						break;
					case 'year':
						$this->menu_main_show_tb->switch_tab(4);
						break;
				}
				break;
		}
	}
	///////////////////////////////////////////////////////////////////////////////
	public function body($arg = null) {
		$this->init();
		$this->menu_main_show();
		$this->parse_links();
		
		// quick fix
		//$this->menu_main_show_tb->get_module_variable_or_unique_href_variable('page', 0);
		
		$this->display_module($this->menu_main_show_tb);
		$this->menu_main_show_tb->tag();
	}
}
?>
