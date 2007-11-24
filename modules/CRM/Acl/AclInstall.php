<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package crm
 * @subpackage acl
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_AclInstall extends ModuleInstall {
	public function install() {
		$ret = true;
		if($ret) $ret = Acl::add_groups(array('Customer'=>array('Customer Manager'=>array('Customer Administrator'))));
		if($ret) $ret = Acl::add_groups(array('Employee'=>array('Employee Manager'=>array('Employee Administrator'))));
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
