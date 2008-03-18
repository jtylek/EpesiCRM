<?php
/**
 * Example event module
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-calendar-event
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_EventInstall extends ModuleInstall {

	public function install() {
		$ret = true;
		$ret &= DB::CreateTable('crm_calendar_event',
			'id I AUTO KEY,'.

			'title C(64) NOT NULL, '.
			'description X, '.

			'start I4 NOT NULL, '.
			'end I4 NOT NULL, '.
			'timeless I1 DEFAULT 0, '.

			'access I1 DEFAULT 0, '.
			'priority I1 DEFAULT 0, '.
			'color I1 DEFAULT 0, '.

			'created_on T NOT NULL,'.
			'created_by I4,'.
			'edited_on T,'.
			'edited_by I4',
			array('constraints'=>	' , INDEX(start), INDEX(end), FOREIGN KEY (edited_by) REFERENCES user_login(id), FOREIGN KEY (created_by) REFERENCES user_login(id)')
		);
		$ret &= DB::CreateTable('crm_calendar_event_group_emp',
			'id I,'.
			'contact I4 NOT NULL',
			array('constraints'=>' , FOREIGN KEY (id) REFERENCES crm_calendar_event(id)')
			);
		$ret &= DB::CreateTable('crm_calendar_event_group_cus',
			'id I,'.
			'contact I4 NOT NULL',
			array('constraints'=>' , FOREIGN KEY (id) REFERENCES crm_calendar_event(id)')
			);
		if(!$ret) {
			print('Unable to create crm_calendar_event table');
			return false;
		}
		Base_ThemeCommon::install_default_theme('CRM/Calendar/Event');

		$this->add_aco('view protected notes','Employee');
		$this->add_aco('view public notes','Employee');
		$this->add_aco('edit protected notes','Employee Administrator');
		$this->add_aco('edit public notes','Employee');

		return $ret;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('CRM/Calendar/Event');
		Utils_AttachmentCommon::persistent_mass_delete(null,'CRM/Calendar/Event');
		Utils_MessengerCommon::delete_by_parent_module($this->get_type());
		$ret = true;
		$ret &= DB::DropTable('crm_calendar_event_group_emp');
		$ret &= DB::DropTable('crm_calendar_event_group_cus');
		$ret &= DB::DropTable('crm_calendar_event');
		return $ret;
	}

	public function version() {
		return array('0.1');
	}

	public function requires($v) {
		return array(
				array('name'=>'Utils/Calendar/Event','version'=>0),
				array('name'=>'Utils/PopupCalendar','version'=>0),
				array('name'=>'Utils/Attachment','version'=>0),
				array('name'=>'Utils/Messenger','version'=>0),
				array('name'=>'CRM/Contacts','version'=>0),
				array('name'=>'Libs/QuickForm','version'=>0));
	}

	public static function info() {
		return array(
			'Description'=>'Example event module',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'SPL');
	}

	public static function simple_setup() {
		return false;
	}

}

?>
