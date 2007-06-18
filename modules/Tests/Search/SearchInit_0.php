<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-tests
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for Test module.
 * @package epesi-tests
 * @subpackage search
 */

class Tests_SearchInit_0 extends ModuleInit {
	public static function requires() {
		return array(
			array('name'=>'Base/Search','version'=>0));
	}
	
	public static function provides() {
		return array();
	}
}

?>
