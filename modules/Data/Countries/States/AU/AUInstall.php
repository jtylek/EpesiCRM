<?php
/**
 * @author abisaga@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.9
 * @package epesi-Countries-States
 * @subpackage countries-states
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Data_Countries_States_AUInstall extends ModuleInstall {

	public function install() {
		$australian_states = array('ACT'=>"Australian Capital Territory",  
			'NSW'=>"New South Wales",
			'NT'=>"Northern Territory",
			'QLD'=>"Queensland",  
			'SA'=>"South Australia",  
			'TAS'=>"Tasmania",  
			'VIC'=>"Victoria",  
			'WA'=>"Western Australia");  
		Utils_CommonDataCommon::new_array('Countries/AU',$australian_states);
		return true;
	}

	public function uninstall() {
		Utils_CommonDataCommon::remove('Countries/AU');
		Utils_CommonDataCommon::extend_array('Countries',array('AU'=>'Australia'));
		return true;
	}
	
	public function version() {
		return array("2009");
	}
	
	public function requires($v) {
		return array(
				array('name'=>'Data/Countries','version'=>0)
				);
	}
	
	public static function info() {
		return array(
			'Description'=>'Australian States',
			'Author'=>'abisaga@telaxus.com',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return false;
	}
	
}

?>