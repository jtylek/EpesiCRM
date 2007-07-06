<?php
/**
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-tests
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_TooltipInit_0 extends ModuleInit {
	public static function requires() {
		return array(
			array('name'=>'Utils/CatFile','version'=>0),
			array('name'=>'Utils/Tooltip','version'=>0)
		);
	}
	
	public static function provides() {
		return array();
	}
}

?>
