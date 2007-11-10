<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_Event_PersonalInstall extends ModuleInstall {
	public function install() {
		CRM_Calendar_EventCommon::add_event_type('CRM_Calendar_Event_Personal', 'Personal events', 'Personal events invisible to other user, shown as N/A to admins.');
		Base_ThemeCommon::install_default_theme('CRM/Calendar/Event/Personal');
		DB::CreateTable('calendar_event_personal_activity',
			'id I AUTO KEY,'.
			'name C(64) NOT NULL'
		);
		DB::Execute("INSERT INTO calendar_event_personal_activity(name) VALUES('Telephone')");
		DB::Execute("INSERT INTO calendar_event_personal_activity(name) VALUES('Meeting')");
		DB::Execute("INSERT INTO calendar_event_personal_activity(name) VALUES('Other')");
		
		DB::CreateTable('calendar_event_personal_gid_counter',
			'id I AUTO KEY,' .
			'something I'
		);
		
		DB::CreateTable('calendar_event_personal_group',
			'gid I,' .
			'uid I'
		);
		
		DB::CreateTable("calendar_event_personal",
			"id I AUTO KEY," .
			
			"title C(80) NO NULL, " .
			"act_id I REFERENCES activities(id)," .
			"emp_gid I REFERENCES calendar_groups(gid)," .
			"description C(250), " .
			
			"datetime_start T NOT NULL, " .
			"datetime_end T NOT NULL, " .
			"timeless I DEFAULT 0, " .
			
			"repeat_event I DEFAULT 0, " .
			"day_of_month_repeat I DEFAULT 0, " .
			"day_of_week_repeat I DEFAULT 0, " .
			"month_repeat I DEFAULT 0, " .
			"year_repeat I DEFAULT 0, " .
								
			"datetime_repeat_expires T DEFAULT 0, " .
			
			"priority I2 DEFAULT 0, " .
			"access I2 DEFAULT 0, " .
			"status I2 DEFAULT 0, " .
			
			"created_on T NOT NULL," .
			"created_by C(40) REFERENCES users(id)," .
			"edited_on T NOT NULL," .
			"edited_by C(40)REFERENCES users(id)"
		);
		
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('CRM/Calendar/Event/Personal');
		DB::DropTable('calendar_event_personal');
		DB::DropTable('calendar_event_personal_activity');
		DB::DropTable('calendar_event_personal_group');
		DB::DropTable('calendar_event_personal_gid_counter');
		CRM_Calendar_EventCommon::remove_event_type('CRM_Calendar_Event_Personal');
		return true;
	}
	public function requires($v) {
		return array();
	}
}

?>
