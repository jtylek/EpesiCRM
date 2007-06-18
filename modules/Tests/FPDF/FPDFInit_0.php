<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-tests
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for CatFile module.
 * @package epesi-tests
 * @subpackage fpdf
 */
class Tests_FPDFInit_0 extends ModuleInit {
	public static function requires() {
		return array(array('name'=>'Utils/CatFile','version'=>0),
			array('name'=>'Libs/FPDF','version'=>0));
	}
	
	public static function provides() {
		return array();
	}
}

?>
