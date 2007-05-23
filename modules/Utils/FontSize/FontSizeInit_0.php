<?php
/**
 * Utils_FontSize class.
 * 
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_FontSizeInit_0 extends ModuleInit {
	
	public static function requires() {
		return array(
			array('name'=>'Base/Theme','version'=>0)
		);
	}
	
	public static function provides() {
		return array();
	}
}

?>
