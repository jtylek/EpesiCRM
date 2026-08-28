<?php
/**
 * LangInstall class.
 * 
 * This class provides initialization data for Lang module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage lang
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_LangInstall extends ModuleInstall {
	public function install() {
        ModuleManager::include_common('Base_Lang', 0);
        Variable::set('installed_langs', implode(',', array_keys(Base_LangCommon::get_base_languages())));
		return Variable::set('default_lang','en');
	}
	
	public function uninstall() {
		return Variable::delete('default_lang');
	}
	
	public function version() {
		return array('1.0.0');
	}

	public function requires($v) {
		return array();
	}

	public static function simple_setup() {
		return __('Epesi Core');
	}
	
}
?>
