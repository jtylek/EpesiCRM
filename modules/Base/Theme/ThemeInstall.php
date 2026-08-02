<?php
/**
 * TestInstall class.
 * 
 * This class provides initialization data for Theme module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage theme
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ThemeInstall extends ModuleInstall {
	public function install() {
		Variable::set('default_theme','adminltedark');
		return true;
	}

	public function uninstall() {
		Variable::delete('default_theme');
		return true;
	}

	public function version() {
		return array('1.0.0');
	}

	public function requires($v) {
		return array();
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}
}

?>
