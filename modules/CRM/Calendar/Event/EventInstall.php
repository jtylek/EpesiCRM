<?php
/**
 * Example event module
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage calendar-event
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_EventInstall extends ModuleInstall {

	public function install() {
		$ret = true;
		$ret &= DB::CreateTable('crm_calendar_event_custom_fields',
					'id I AUTO KEY, '.
					'field C(64), '.
					'callback C(128)',
					array('constraints'=>''));
		$ret &= DB::CreateTable('crm_calendar_event',
			'id I AUTO KEY,'.

			'title C(128) NOT NULL, '.
			'description X, '.

			'starts I4 NOT NULL, '.
			'ends I4 NOT NULL, '.
			'timeless I1 DEFAULT 0, '.

			'access I1 DEFAULT 0, '.
			'priority I1 DEFAULT 0, '.
			'color I1 DEFAULT 0, '.
			'status I2 DEFAULT 0, '.

			'created_on T NOT NULL,'.
			'created_by I4,'.
			'edited_on T,'.
			'edited_by I4,'.
			
			'recurrence_type I2,'.
			'recurrence_end D,'.
			'recurrence_hash C(8),'.
			
			'deleted I1 DEFAULT 0',
			array('constraints'=>	' , FOREIGN KEY (edited_by) REFERENCES user_login(id), FOREIGN KEY (created_by) REFERENCES user_login(id)')
		);
		DB::CreateIndex('crm_calendar_event__start__idx', 'crm_calendar_event', 'starts');
		DB::CreateIndex('crm_calendar_event__end__idx', 'crm_calendar_event', 'ends');
		DB::CreateIndex('crm_calendar_event__deleted__idx', 'crm_calendar_event', 'deleted');
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
		Utils_AttachmentCommon::persistent_mass_delete('CRM/Calendar/Event');
		Utils_MessengerCommon::delete_by_parent_module($this->get_type());
		$ret = true;
		$ret &= DB::DropTable('crm_calendar_event_group_emp');
		$ret &= DB::DropTable('crm_calendar_event_group_cus');
		$ret &= DB::DropTable('crm_calendar_event');
		$ret &= DB::DropTable('crm_calendar_event_custom_fields');
		return $ret;
	}

	public function version() {
		return array('1.0');
	}

	public function requires($v) {
		return array(
				array('name'=>'CRM/Common', 'version'=>0),
				array('name'=>'Base/Lang', 'version'=>0),
				array('name'=>'Utils/Calendar/Event','version'=>0),
				array('name'=>'Utils/PopupCalendar','version'=>0),
				array('name'=>'Utils/Attachment','version'=>0),
				array('name'=>'Utils/Messenger','version'=>0),
				array('name'=>'CRM/Contacts','version'=>0),
				array('name'=>'Libs/QuickForm','version'=>0),
				array('name'=>'Libs/TCPDF','version'=>0));
	}

	public static function info() {
		return array(
			'Description'=>'CRM event module',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'MIT');
	}

	public static function simple_setup() {
		return false;
	}

}

?>
