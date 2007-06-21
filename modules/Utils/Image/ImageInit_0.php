<?php
/**
 * Utils_ImageInit_0 class.
 * 
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_ImageInit_0 extends ModuleInit {
	public static function requires() {
		if(!function_exists('imagecreatefromjpeg')) return array(array('name'=>'php5-gd','version'=>0));
		return array();
	}
	
	public static function provides() {
		return array();
	}
}

?>
