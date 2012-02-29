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
		Base_ThemeCommon::install_default_theme('CRM/Calendar/Event');
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('CRM/Calendar/Event');
		Utils_MessengerCommon::delete_by_parent_module($this->get_type());
		return true;
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
		return 'CRM';
	}

}

?>
