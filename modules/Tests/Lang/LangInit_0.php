<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-tests
 * @subpackage lang
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_LangInit_0 extends ModuleInit{
	public static function requires() {
		return array(	array('name'=>'Utils/CatFile','version'=>0),
						array('name'=>'Base/Lang','version'=>0));
	}
	
	public static function provides() {
		return array();
	}
} 
?>
