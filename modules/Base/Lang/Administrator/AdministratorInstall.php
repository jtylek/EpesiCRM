<?php
/**
 * Lang_AdministratorInstall class.
 *
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-base
 * @subpackage lang-administrator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Lang_AdministratorInstall extends ModuleInstall {
	public function version() {
		return array('1.0.0');
	}

	public function install() {
		DB::CreateTable('base_lang_trans_contrib',
			'id I4 AUTO KEY,'.
			'user_id I4,'.
			'allow I1,'.
			'first_name C(64),'.
			'last_name C(64),'.
			'credits I1,'.
			'credits_website C(128),'.
			'contact_email C(128)',
			array());
		Base_ThemeCommon::install_default_theme($this->get_type());
		return Variable::set('allow_lang_change',true);
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return Variable::delete('allow_lang_change');
	}
	public function requires($v) {
		return array(
			array('name'=>Base_AdminInstall::module_name(),'version'=>0),
			array('name'=>Base_AclInstall::module_name(),'version'=>0),
			array('name'=>Base_ThemeInstall::module_name(),'version'=>0),
			array('name'=>Libs_QuickFormInstall::module_name(),'version'=>0),
			array('name'=>Base_UserInstall::module_name(),'version'=>0),
			array('name'=>Utils_GenericBrowserInstall::module_name(),'version'=>0),
			array('name'=>Base_User_SettingsInstall::module_name(),'version'=>0), // TODO: not required directly but needed to make this module fully operational. Should we delete the requirement?
			array('name'=>Base_LangInstall::module_name(),'version'=>0));
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}
}

?>
