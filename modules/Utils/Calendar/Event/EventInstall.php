<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_EventInstall extends ModuleInstall {

	public function install() {

		//Base_ThemeCommon::install_default_theme('CRM/Calendar/Event');
		DB::CreateTable('calendar_event_types',
			'id I AUTO KEY,'.
			'name C(64) NOT NULL'
		);
		DB::Execute("INSERT INTO calendar_event_types(name) VALUES('Telephone')");
		DB::Execute("INSERT INTO calendar_event_types(name) VALUES('Meeting')");
		DB::Execute("INSERT INTO calendar_event_types(name) VALUES('Other')");

		DB::CreateTable("calendar_events",
			"id I AUTO KEY," .

			"title C(80) NO NULL, " .
			"act_id I REFERENCES activities(id)," .
			"employees I REFERENCES calendar_groups(gid)," .
			"contacts I REFERENCES calendar_groups(gid)," .
			"description C(250), " .

			"datetime_start T NOT NULL, " .
			"datetime_end T NOT NULL, " .
			"timeless I DEFAULT 0, " .

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
		//Base_ThemeCommon::uninstall_default_theme('CRM/Calendar/Event');
		DB::DropTable('calendar_events');
		DB::DropTable('calendar_event_types');
		return true;
	}
	public function requires($v) {
		return array();
	}
}

?>
?>
