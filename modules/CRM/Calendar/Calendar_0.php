<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar extends Module {

	public function body() {
		CRM_Calendar_EventCommon::$filter = CRM_FiltersCommon::get();
		CRM_FiltersCommon::add_action_bar_icon();
		
		if(isset($_REQUEST['search_date']) && is_numeric($_REQUEST['search_date']) && isset($_REQUEST['ev_id']) && is_numeric($_REQUEST['ev_id'])) {
			$default_date = intval($_REQUEST['search_date']);
			$this->view_event(intval($_REQUEST['ev_id']));
		} else
			$default_date = null;
		
		$theme = $this->init_module('Base/Theme');
		$c = $this->init_module('Utils/Calendar',array('CRM/Calendar/Event',array('default_view'=>Base_User_SettingsCommon::get('CRM_Calendar','default_view'),
			'first_day_of_week'=>Utils_PopupCalendarCommon::get_first_day_of_week(),
			'start_day'=>Base_User_SettingsCommon::get('CRM_Calendar','start_day'),
			'end_day'=>Base_User_SettingsCommon::get('CRM_Calendar','end_day'),
			'interval'=>Base_User_SettingsCommon::get('CRM_Calendar','interval'),
			'default_date'=>$default_date,
			'custom_agenda_cols'=>array('Description','Assigned to','Related with')
			)));
		$theme->assign('calendar',$this->get_html_of_module($c));
		$theme->display();
	}
	
	public function applet($conf,$opts) {
		$opts['go'] = true;

		$l = $this->init_module('Base/Lang');

		$gb = $this->init_module('Utils/GenericBrowser', null, 'agenda');
		$columns = array(
			array('name'=>$l->t('Start'), 'order'=>'e.start', 'width'=>50),
			array('name'=>$l->t('Title'), 'order'=>'e.title','width'=>50),
		);
		$gb->set_table_columns($columns);

		$start = time();
		$end = $start + ($conf['days'] * 24 * 60 * 60);

		$gb->set_default_order(array($l->t('Start')=>'ASC'));
		CRM_Calendar_EventCommon::$filter = '('.CRM_FiltersCommon::get_my_profile().')';
		$ret = CRM_Calendar_EventCommon::get_all($start,$end,$gb->get_query_order());
		$data = array();
		foreach($ret as $row) {
			$ex = Utils_CalendarCommon::process_event($row);
			$view_action = '<a '.$this->create_callback_href(array($this,'view_event'),$row['id']).'>';
			if($row['description'])
				$title = Utils_TooltipCommon::create($row['title'],$row['description']);
			else
				$title = $row['title'];
			$gb->add_row($view_action.Utils_TooltipCommon::create($ex['start'],$l->t('Duration: %s<br>End: %s',array($ex['duration'],$ex['end']))).'</a>',$view_action.$title.'</a>');
		}

		$this->display_module($gb);
	}
	
	public function view_event($id) {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('CRM_Calendar_Event','view',$id);
	}

	public function caption() {
		return "Calendar";
	}
}
?>
