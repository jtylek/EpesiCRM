<?php
/**
 * Get Support landing page.
 *
 * @package epesi-base
 * @subpackage support
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_SupportInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return true;
	}

	public function requires($v) {
		return array(
			array('name' => Base_LangInstall::module_name(), 'version' => 0));
	}

	public function version() {
		return array('2.0');
	}

	public static function info() {
		return array(
			'Description' => 'Get Support landing page',
			'Author' => 'Janusz Tylek & Karina Tylek',
			'License' => 'MIT');
	}

	public static function simple_setup() {
		return __('Epesi Core');
	}

}
?>
