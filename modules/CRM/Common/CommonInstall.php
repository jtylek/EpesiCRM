<?php
/**
 * common data used by CRM modules
 * @author pbukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-CRM
 * @subpackage Common
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_CommonInstall extends ModuleInstall {

	public function install() {
		Utils_CommonDataCommon::new_array('CRM',array(),true,true);
		Utils_CommonDataCommon::new_array('CRM/Priority',array(0=>'Low',1=>'Medium',2=>'High'), true,true);
		Utils_CommonDataCommon::new_array('CRM/Access',array(0=>'Public', 1=>'Public, Read-Only', 2=>'Private'), true,true);
		Utils_CommonDataCommon::new_array('CRM/Status',array('Open','In Progress','Closed','Canceled'), true,true);
		return true;
	}
	
	public function uninstall() {
		Utils_CommonDataCommon::remove('CRM');
		return true;
	}
	
	public function version() {
		return array("0.9");
	}
	
	public function requires($v) {
		return array();
	}
	
	public static function info() {
		return array(
			'Description'=>'common data used by CRM modules',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>