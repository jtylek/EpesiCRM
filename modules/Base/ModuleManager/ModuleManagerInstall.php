<?php
/**
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage ModuleManager
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ModuleManagerInstall extends ModuleInstall {
	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	public function requires($v) {
		return array(
		array('name'=>'Libs/QuickForm','version'=>0));
	}
}

?>
