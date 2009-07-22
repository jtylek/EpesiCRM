<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage acl
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_AclInstall extends ModuleInstall {
	public function install() {
		$ret = true;
		if($ret) $ret = Acl::add_groups(array('Customer'=>array('Customer Manager'=>array('Customer Administrator'))));
		if($ret) $ret = Acl::add_groups(array('Employee'=>array('Employee Manager'=>array('Employee Administrator'))));

		if($ret) {
			$count = DB::GetOne('SELECT count(ul.id) FROM user_login ul');
			if($count==1) {
				$user = DB::GetRow('SELECT ul.id,up.mail,ul.login FROM user_login ul INNER JOIN user_password up ON up.user_login_id=ul.id');
				$uid = Base_AclCommon::get_acl_user_id($user['id']);
				if($uid !== false) {
					$groups_old = Base_AclCommon::get_user_groups($uid);
					Base_AclCommon::change_privileges($user['id'], array_merge($groups_old,array(Base_AclCommon::get_group_id('Employee Administrator'),Base_AclCommon::get_group_id('Customer Administrator'))));
				}
			}
		}

		return $ret;
	}

	public function uninstall() {
		Acl::del_group('Customer');
		Acl::del_group('Customer Manager');
		Acl::del_group('Customer Administrator');
		Acl::del_group('Employee');
		Acl::del_group('Employee Manager');
		Acl::del_group('Employee Administrator');
		return true;
	}

	public function version() {
		return array('1.0.0');
	}

	public function requires($v) {
		return array();
	}
}
?>
