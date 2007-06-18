<?php
/**
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package apps-gallery
 * @licence SPL
 */
 defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_GalleryInit_0 extends ModuleInit {
	public static function requires() {
		return array(
			array('name'=>'Utils/TabbedBrowser', 'version'=>0), 
			array('name'=>'Utils/Path', 'version'=>0), 
			array('name'=>'Utils/Tree', 'version'=>0), 
			array('name'=>'Utils/Gallery', 'version'=>0)
		);
	}
	
	public static function provides() {
		return array();
	}
}

?>
