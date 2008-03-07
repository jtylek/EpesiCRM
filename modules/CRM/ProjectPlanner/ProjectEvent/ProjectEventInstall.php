<?php
/**
 * Example event module
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package custom-projects-planner-projectevent
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Custom_Projects_Planner_ProjectEventInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return true;
	}

	public function version() {
		return array('0.1');
	}

	public function requires($v) {
		return array(
//				array('name'=>'Utils/PopupCalendar','version'=>0),
//				array('name'=>'Utils/Attachment','version'=>0),
//				array('name'=>'Utils/Messenger','version'=>0),
				array('name'=>'Base/Lang','version'=>0),
				array('name'=>'CRM/Contacts','version'=>0),
				array('name'=>'Libs/QuickForm','version'=>0));
	}

	public static function info() {
		return array(
			'Description'=>'Employee event module',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'SPL');
	}

	public static function simple_setup() {
		return false;
	}

}

?>
