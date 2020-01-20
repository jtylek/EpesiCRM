<?php
/**
 * @author j@epe.si
 * @copyright 2008 Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-applets
 * @subpackage birthdays
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_BirthdaysInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return true;
	}
	
	public function version() {
		return array("1.0");
	}
	
	public function requires($v) {
		return array(
			array('name'=>CRM_CalendarInstall::module_name(),'version'=>0),
			array('name'=>Base_LangInstall::module_name(),'version'=>0));
	}
	
	public static function info() {
		$html="Displays upcoming Birthdays of your favorite contacts.";
		$html.="<br>The contact has to be in your favorite list<br>and have a birthday date set.";
		return array(
			'Description'=>$html,
			'Author'=>'j@epe.si',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
        return array('package'=>__('EPESI Core'), 'option'=>__('Additional applets'));
	}
	
}

?>