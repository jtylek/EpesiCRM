<?php
/**
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_GalleryInit_0 extends ModuleInit {
	public static function requires() {
		return array(
			array('name'=>'Libs/Lytebox', 'version'=>0),
			array('name'=>'Utils/Image', 'version'=>0)
		);
	}
	
	public static function provides() {
		return array();
	}
}

?>
