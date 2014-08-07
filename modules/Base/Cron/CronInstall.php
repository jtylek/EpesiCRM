<?php
/**
 * Cron Epesi
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage about
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_CronInstall extends ModuleInstall {

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
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/Admin','version'=>0),
			array('name'=>'Base/RegionalSettings','version'=>0),
			array('name'=>'Utils/GenericBrowser','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Cron Epesi',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return __('EPESI Core');
	}
	
}

?>