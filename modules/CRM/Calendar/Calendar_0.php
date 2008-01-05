<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar extends Module {

	public function body() {
		if($this->acl_check('filters'))
			CRM_Calendar_MeetingCommon::$filter = $this->pack_module('CRM/Filters')->get();
		else
			CRM_Calendar_MeetingCommon::$filter = Acl::get_user();
		$c = $this->init_module('Utils/Calendar',array('CRM/Calendar/Meeting',array('default_view'=>Base_User_SettingsCommon::get('CRM_Calendar','default_view'),
			'first_day_of_week'=>Utils_PopupCalendarCommon::get_first_day_of_week(),
			'start_day'=>Base_User_SettingsCommon::get('CRM_Calendar','start_day'),
			'end_day'=>Base_User_SettingsCommon::get('CRM_Calendar','end_day'),
			'interval'=>Base_User_SettingsCommon::get('CRM_Calendar','interval'),
			)));
		$this->display_module($c);
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
		$ret = CRM_Calendar_MeetingCommon::get_all($start,$end,$gb->get_query_order());
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
		$x->push_main('CRM_Calendar_Meeting','view',$id);
	}

	public function caption() {
		return "Calendar";
	}
}
?>
