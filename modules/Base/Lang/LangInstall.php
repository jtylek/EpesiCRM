<?php
/**
 * LangInstall class.
 * 
 * This class provides initialization data for Lang module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage lang
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_LangInstall extends ModuleInstall {
	public function install() {
		$this->create_data_dir();
		if (!is_dir(DATA_DIR.'/Base_Lang/base'))
			mkdir(DATA_DIR.'/Base_Lang/base');
		if (!is_dir(DATA_DIR.'/Base_Lang/custom'))
			mkdir(DATA_DIR.'/Base_Lang/custom');
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
		return __('EPESI Core');
	}
	
}
?>
