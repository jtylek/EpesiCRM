<?php
/**
 * 
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-CRM
 * @subpackage Assets
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_AssetsInstall extends ModuleInstall {

	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version() {
		return array("0.1");
	}
	
	public function requires($v) {
		return array(
					array('name'=>'Base/Lang','version'=>0),
					array('name'=>'Utils/RecordBrowser','version'=>0),
                                        array('name'=>'CRM/Contacts','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'',
			'Author'=>'Adam Bukowski <abukowski@telaxus.com>',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return true;
	}
	
}

?>