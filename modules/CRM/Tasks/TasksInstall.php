<?php
/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-tasks
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_TasksInstall extends ModuleInstall {

	public function install() {
//		$this->add_aco('manage others','Employee Manager');
		$this->add_aco('access','Employee');
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
			array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Utils/Tasks','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'SPL');
	}
	
	public static function simple_setup() {
		return true;
	}
	
}

?>