<?php
/**
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-apps
 * @subpackage shoutbox
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_ShoutboxInstall extends ModuleInstall {

	public function install() {
		$ret = true;
		$ret &= DB::CreateTable('apps_shoutbox_messages','
			id I4 AUTO KEY,
			base_user_login_id I4 NOTNULL,
			to_user_login_id I4,
			message X,
			posted_on T DEFTIMESTAMP,
			deleted I1',
			array('constraints'=>', FOREIGN KEY (base_user_login_id) REFERENCES user_login(ID)'));
		if(!$ret){
			print('Unable to create table apps_shoutbox_messages.<br>');
			return false;
		}
		Base_ThemeCommon::install_default_theme($this -> get_type());
		Base_AclCommon::add_permission(_M('Shoutbox'),array('ACCESS:employee'));
		Base_AclCommon::add_permission(_M('Shoutbox Admin'), array('SUPERADMIN'));
		return $ret;
	}

	public function uninstall() {
		$ret = true;
		$ret &= DB::DropTable('apps_shoutbox_messages');
		Base_AclCommon::delete_permission('Shoutbox');
		Base_AclCommon::delete_permission('Shoutbox Admin');
		Base_ThemeCommon::uninstall_default_theme($this -> get_type());
		return $ret;
	}
	public function version() {
		return array("1.0");
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}

	public function requires($v) {
		return array(
			array('name'=>Base_AclInstall::module_name(),'version'=>0),
			array('name'=>Base_UserInstall::module_name(),'version'=>0),
			array('name'=>Utils_BBCodeInstall::module_name(), 'version'=>0),
			array('name'=>Base_LangInstall::module_name(),'version'=>0),
			array('name'=>Libs_QuickFormInstall::module_name(),'version'=>0));
	}

}

?>
