<?php
/**
 * Gets host ip or domain
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package applets-host
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_HostInstall extends ModuleInstall {

	public function install() {
		return true;
	}
	
	public function uninstall() {
		Apps_ActiveBoardCommon::delete($this->get_type());
		return true;
	}
	
	public function version() {
		return array("1.0");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Apps/ActiveBoard','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Gets host ip or domain',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'SPL');
	}
	
	public static function simple_setup() {
		return true;
	}
	
}

?>