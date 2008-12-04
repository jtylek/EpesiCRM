<?php
/**
 * UserInstall class.
 * 
 * This class provides initialization data for User module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage user
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_UserInstall extends ModuleInstall {
	public function install() {
		$ret = DB::CreateTable('user_login',"id I AUTO KEY ,login C(32) NOTNULL, active I1 NOTNULL DEFAULT 1", array('constraints' => ', UNIQUE (login)'));
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
			array('name'=>'Base/Acl','version'=>0));
	}
	
	public function backup($v) {
		return array('user_login');
	}
}
?>
