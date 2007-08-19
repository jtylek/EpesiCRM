<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-tests
 * @subpackage lightbox
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_LightboxInstall extends ModuleInstall{
	public static function install(){
		return true;
	}

	public static function uninstall() {
		return true;
	}
	public static function requires_0() {
		return array(array('name'=>'Utils/CatFile','version'=>0),
			array('name'=>'Libs/Leightbox','version'=>0),
			array('name'=>'Libs/Lytebox','version'=>0));
	}
} 
?>
