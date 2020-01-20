<?php
/**
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @version 1.9.0
 * @license MIT
 * @package epesi-tests
 * @subpackage tooltip
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_TooltipInstall extends ModuleInstall {
	public function install() {
		//code to install version 0
		return true;
	}
	
	public function uninstall() {
		//code to uninstall version 0
		return true;
	}
	
	public function upgrade_1() {
		//code to upgrade from version 0 to 1
		return true;
	}
	
	public function downgrade_1() {
		//code to downgrade from version 1 to 0
		return true;
	}
	
	public function requires($v) {
		return array(
			array('name'=>Utils_CatFileInstall::module_name(),'version'=>0),
			array('name'=>Utils_TooltipInstall::module_name(),'version'=>0)
		);
	}
	
	public function version() {
		return array(0=>'1.0',1=>'1.5'); //version names
	}
}

?>
