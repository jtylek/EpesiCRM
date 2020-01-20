<?php
/**
 *
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-crm
 * @subpackage followup
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_FollowupInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme(CRM_FollowupInstall::module_name());
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme(CRM_FollowupInstall::module_name());
		return true;
	}

	public function version() {
		return array("1.0");
	}

	public function requires($v) {
		return array(
			array('name'=>Base_ThemeInstall::module_name(),'version'=>0),
			array('name'=>Base_LangInstall::module_name(),'version'=>0),
			array('name'=>Base_User_SettingsInstall::module_name(),'version'=>0),
			array('name'=>CRM_ContactsInstall::module_name(),'version'=>0));
	}

	public static function info() {
		return array(
			'Description'=>'',
			'Author'=>'j@epe.si',
			'License'=>'MIT');
	}

	public static function simple_setup() {
		return 'CRM';
	}

}

?>
