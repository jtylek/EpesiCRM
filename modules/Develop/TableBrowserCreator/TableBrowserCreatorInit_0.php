<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.5
 * @license MIT
 * @package epesi-develop
 * @subpackage tablebrowsercreator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Develop_TableBrowserCreatorInit_0 extends ModuleInit {
	public static function requires() {
		return array(	array('name'=>'Base/Lang','version'=>0),
						array('name'=>'Utils/GenericBrowser','version'=>0));
	}
	
	public static function provides() {
		return array();
	}
}

?>
