<?php
/**
 * TestInstall class.
 * 
 * This class provides initialization data for Test module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides initialization data for Test module.
 * @package epesi-base-extra
 * @subpackage theme
 */
class Base_ThemeInstall extends ModuleInstall {
	public static function install() {
		mkdir('data/Base/Theme/templates');
		mkdir('data/Base/Theme/templates/default');
		mkdir('data/Base/Theme/compiled');
		mkdir('data/Base/Theme/cache');
		mkdir('data/Base/Theme/config');
		return Variable::set('default_theme','default');
	}
	
	public static function uninstall() {
		return Variable::delete('default_theme');
	}
	
	public static function version() {
		return array('1.0.0');
	}
}

?>
