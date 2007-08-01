<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-tests
 * @subpackage wizard
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_WizardInit_0 extends ModuleInit {
	public static function requires() {
		return array(array('name'=>'Utils/CatFile','version'=>0),
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Utils/Wizard','version'=>0)
		);
	}
	
	public static function provides() {
		return array();
	}
}

?>
