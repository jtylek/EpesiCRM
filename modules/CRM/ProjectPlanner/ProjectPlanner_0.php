<?php
/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-projectplanner
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_ProjectPlanner extends Module {

	public function body() {
		$tb = $this->init_module('Utils/TabbedBrowser');
		$tb->set_tab('Overview',array($this,'overview'));
		$tb->set_tab('Project',array($this,'project_view'));
		$tb->set_tab('Employee',array($this,'employee_view'));
		
		$this->display_module($tb);
		$tb->tag();
	}
	
	public function overview() {
	
	}
	
	public function project_view() {
	
	}
	
	public function employee_view() {
		//wybor pracownika tutaj
		
		$c = $this->init_module('Utils/Calendar',array('CRM/ProjectPlanner/EmployeeEvent',array('default_view'=>'week',
			'first_day_of_week'=>Utils_PopupCalendarCommon::get_first_day_of_week(),
//			'start_day'=>Base_User_SettingsCommon::get('CRM_Calendar','start_day'),
//			'end_day'=>Base_User_SettingsCommon::get('CRM_Calendar','end_day'),
//			'interval'=>Base_User_SettingsCommon::get('CRM_Calendar','interval'),
			'default_date'=>time()
			)));
		$this->display_module($c);
	}
	
	public static function caption() {
		return "Project planner";
	}

}

?>