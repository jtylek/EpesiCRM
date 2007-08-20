<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-tests
 * @subpackage lang
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_LangInstall extends ModuleInstall{
	public static function install(){
		Base_LangCommon::install_translations('Tests_Lang');
		return true;
	}

	public static function uninstall() {
		return true;
	}
	public static function requires($v) {
		return array(	array('name'=>'Utils/CatFile','version'=>0),
						array('name'=>'Base/Lang','version'=>0));
	}
} 
?>
