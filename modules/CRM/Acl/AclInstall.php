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
			$uid = Base_AclCommon::get_acl_user_id(Acl::get_user());
			if($uid !== false) {
				$groups_old = Base_AclCommon::get_user_groups($uid);
				Base_AclCommon::change_privileges(Acl::get_user(), array_merge($groups_old,array(Base_AclCommon::get_group_id('Employee Administrator'),Base_AclCommon::get_group_id('Customer Administrator'))));
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
		return array(array('name'=>'Base/Acl','version'=>0));
	}
}
?>
