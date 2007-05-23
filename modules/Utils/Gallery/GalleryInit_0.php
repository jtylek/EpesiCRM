<?php
/**
 * Base_ImageInit_0 class.
 * 
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_GalleryInit_0 extends ModuleInit {
	public static function requires() {
		return array(
			array('name'=>'Utils/Image', 'version'=>0)
		);
	}
	
	public static function provides() {
		return array();
	}
}

?>
