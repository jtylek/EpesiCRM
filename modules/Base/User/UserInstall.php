<?php
/**
 * UserInstall class.
 * 
 * This class provides initialization data for User module.
 * 
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-base
 * @subpackage user
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_UserInstall extends ModuleInstall {
	public function install() {
		$ret = DB::CreateTable('user_login',"id I AUTO KEY ,login C(32) NOTNULL, active I1 NOTNULL DEFAULT 1, admin I1 NOTNULL DEFAULT 0", array('constraints' => ', UNIQUE (login)'));
		if($ret===false) {
			print('Invalid SQL query - User module install');
			return false;
		}
		return true;
	}
	
	public function uninstall() {
		return DB::DropTable('user_login');
	}
	
	public function version() {
		return array("1.0");
	}
	
	public function requires($v) {
		return array(
			array('name'=>Base_AclInstall::module_name(),'version'=>0)
		);
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}
}
?>
