<?php
/**
 * @author jtylek@telaxus.com
 * @copyright 2008 Telaxus LLC
 * @license MIT
 * @version 1.0
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
			array('name'=>'CRM/Calendar','version'=>0),
			array('name'=>'Base/Lang','version'=>0));
	}
	
	public static function info() {
		$html="Displays upcoming Birthdays of your favorite contacts.";
		$html.="<br>The contact has to be in your favorite list<br>and have a birthday date set.";
		return array(
			'Description'=>$html,
			'Author'=>'jtylek@telaxus.com',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
        return array('package'=>__('EPESI Core'), 'option'=>__('Additional applets'));
	}
	
}

?>