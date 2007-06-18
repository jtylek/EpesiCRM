<?php
/**
 * WizardInit_0 class.
 * 
 * This class provides initialization data for Wizard module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com> and Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_WizardInit_0 extends ModuleInit {
	public static function requires() {
		return array(
			array('name'=>'Base/Lang','version'=>0)
		);
	}
	
	public static function provides() {
		return array();
	}
}

?>
