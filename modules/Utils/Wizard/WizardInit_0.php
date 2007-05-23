<?php
/**
 * WizardInit_0 class.
 * 
 * This class provides initialization data for Wizard module.
 * 
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-utils
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
