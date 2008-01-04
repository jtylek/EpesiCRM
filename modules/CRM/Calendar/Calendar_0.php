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
	
	public function applet() {
		$start = time();
		$end = $start + (7 * 24 * 60 * 60);

		//////////////// data ////////////////////////
//		$gb = $this->init_module('Utils/GenericBrowser', null, 'agenda');
		$l = $this->init_module('Base/Lang');
		$columns = array(
			array('name'=>$l->t('Start'), 'order'=>'start', 'width'=>15),
			array('name'=>$l->t('Title'), 'order'=>'title','width'=>15),
		);
//		$gb->set_table_columns( $columns );

		//add data
		$ret = CRM_Calendar_MeetingCommon::get_all($start,$end);
		$data = array();
		foreach($ret as $row) {
//			$r = $gb->get_new_row();

			$ex = Utils_CalendarCommon::process_event($row);
			
			//TODO: on click view event
//			$r->add_data(Utils_TooltipCommon::create($ex['start'],$l->t('Duration: %s<br>End: %s',array($ex['duration'],$ex['end']))),Utils_TooltipCommon::create($row['title'],$row['description']));
			$data[] = array(Utils_TooltipCommon::create($ex['start'],$l->t('Duration: %s<br>End: %s',array($ex['duration'],$ex['end']))),Utils_TooltipCommon::create($row['title'],$row['description']));
		}
		//TODO: default order by start
		//no paging

//		$this->display_module($gb,array(false),'automatic_display');
		$gb = $this->init_module('Utils/GenericBrowser', null, 'agenda');
		$this->display_module($gb,array($columns,$data,false,null,array($l->t('Start')=>'DESC')),'simple_table');
	}

	public function caption() {
		return "Calendar";
	}
}
?>
