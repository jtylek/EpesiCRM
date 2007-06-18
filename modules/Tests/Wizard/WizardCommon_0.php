<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-tests
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_WizardCommon {
	public static function menu(){
		return array('Tests'=>array('__submenu__'=>1,'Wizard'=>array()));
	}
	
	public static function tool_menu() {
		return array('WizardTest'=>array());
	}
	

}

?>
