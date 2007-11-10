<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_CalendarCommon extends ModuleCommon {
	public static function menu() {
		return array('CRM'=>array('__submenu__'=>1,'Calendar'=>array()));
	}
	public static function user_settings() {
		if(Base_AclCommon::i_am_user()) 
			$ret = array(
				'Calendar'=>array(
					array('name'=>'first_day','label'=>'First day of week', 'type'=>'select', 'values'=>array(0=>'Sun', 1=>'Mon', 2=>'Tue', 3=>'Wed', 4=>'Thu', 5=>'Fri', 6=>'Sat'), 'default'=>0),
					array('name'=>'view_style','label'=>'Default view', 'type'=>'select', 'values'=>array(0=>'Agenda', 1=>'Day', 2=>'Week', 3=>'Month', 4=>'Year'), 'default'=>2),
					array('name'=>'show_event_types','label'=>'Show Event Types', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>0),
					
					array('name'=>'start_day','label'=>'Start day at', 'type'=>'select', 'values'=>range(0, 23), 'default'=>9),
					array('name'=>'end_day','label'=>'End day at', 'type'=>'select', 'values'=>range(0, 23), 'default'=>17),
					
					array('name'=>'grid_morning','label'=>'grid: morning', 'type'=>'select', 'values'=>range(1, 23), 'default'=>1),
					array('name'=>'grid_day','label'=>'grid: day', 'type'=>'select', 'values'=>range(0, 23), 'default'=>8),
					array('name'=>'grid_evening','label'=>'grid: evening', 'type'=>'select', 'values'=>range(0, 23), 'default'=>1),
					
					array('name'=>'defautl_today','label'=>'Start by default with today\'s date', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>0),
					array('name'=>'details_fields_header','label'=>'Display in detailed tooltip', 'type'=>'header'),
					array('name'=>'show_detail_activity','label'=>'Action', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>1),
					array('name'=>'show_detail_participants','label'=>'Participants', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>1),
					array('name'=>'show_detail_description','label'=>'Description', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>1),
					array('name'=>'show_detail_access','label'=>'Access', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>0),
					array('name'=>'show_detail_priority','label'=>'Priority', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>0),
					array('name'=>'show_detail_created_by','label'=>'Crated by', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>1),
					array('name'=>'show_detail_created_on','label'=>'Created on', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>1),
					array('name'=>'show_detail_edited_by','label'=>'Edited by', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>1),
					array('name'=>'show_detail_edited_on','label'=>'Edited on', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>1)
				)
			);// +	CRM_Calendar_Utils_FuncCommon::get_common_settings();
			if(Base_AclCommon::i_am_admin()) {
				$ret['Calendar'][] = array('name'=>'show_private_header','label'=>'Admin options', 'type'=>'header');
				$ret['Calendar'][] = array('name'=>'show_private','label'=>'Show other\'s private Events', 'type'=>'select', 'values'=>array(0=>'No', 1=>'Yes'), 'default'=>0);
			}
			return $ret;
	} 
		
	public static function caption() {
		return 'Calendar';
	}
}
?>