<?php
/**
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package apps-gallery
 * @licence SPL
 */
 defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_GalleryCommon {
	public static function menu() {
		return array('Apps'=>array('__submenu__'=>1,'Gallery'=>array()));
	}
}
?>