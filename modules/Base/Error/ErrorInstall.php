<?php
/**
 * Provides error to mail handling.
 *
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-base
 * @subpackage error
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ErrorInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());
		Variable::set('error_mail','');
		$this->create_data_dir();
		return true;
	}
	
	public function uninstall() {
		Variable::delete('error_mail');
		return true;
	}
	
	public function version() {
		return array('1.0.0');
	}

	// ************************************
	public static function info() {
		return array(
			'Description'=>'Error Reporting',
			'Author'=>'j@epe.si',
			'License'=>'MIT');
	}

	public function requires($v) {
		return array(
			array('name'=>Base_MailInstall::module_name(), 'version'=>0),
			array('name'=>Base_LangInstall::module_name(), 'version'=>0),
			array('name'=>Libs_QuickFormInstall::module_name(), 'version'=>0),
			array('name'=>Base_AclInstall::module_name(), 'version'=>0));
	}
	
	public function simple_setup() {
        return array('package'=>__('EPESI Core'), 'option'=>__('Error reporting'));
	}
}	

?>
