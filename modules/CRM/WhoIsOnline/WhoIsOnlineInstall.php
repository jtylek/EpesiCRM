<?php
/**
 * Shows who is logged to epesi.
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package CRM-whoisonline
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_WhoIsOnlineInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme($this -> get_type());
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this -> get_type());
		return true;
	}

	public function version() {
		return array("0.9.9");
	}

	public function requires($v) {
		return array(
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'CRM/Contacts','version'=>0),
			array('name'=>'Tools/WhoIsOnline','version'=>0));
	}

	public static function info() {
		return array(
			'Description'=>'Shows who is logged to epesi.',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'SPL');
	}

	public static function simple_setup() {
		return true;
	}

}

?>
