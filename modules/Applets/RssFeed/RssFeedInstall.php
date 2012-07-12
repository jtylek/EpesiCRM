<?php
/**
 * Simple RSS Feed applet
 * @author jtylek@telaxus.com
 * @copyright 2008 Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-applets
 * @subpackage rssfeed
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_RssFeedInstall extends ModuleInstall {

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
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Utils/BBCode','version'=>0),
			array('name'=>'Base/Dashboard','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'RSS Feed',
			'Author'=>'jtylek@telaxus.com',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
        return array('package'=>__('EPESI Core'), 'option'=>__('Additional applets'));
	}
	
}

?>