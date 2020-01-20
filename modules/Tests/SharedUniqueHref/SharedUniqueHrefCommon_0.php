<?php
/**
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @version 1.9.0
 * @license MIT
 * @package epesi-tests
 * @subpackage shareduniquehref
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_SharedUniqueHrefCommon extends ModuleCommon {
	public static function menu(){
		return array('Tests'=>array('__submenu__'=>1,'Shared Unique Href'=>array()));
	}
	
	public static function cron(){
		print("Cron test");
	}
}

?>
