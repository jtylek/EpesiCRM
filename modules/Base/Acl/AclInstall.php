<?php
/**
 * AclInit class.
 * 
 * This class provides initialization data for Acl module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage acl
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_AclInstall extends ModuleInstall {
	public function install() {
		DB::CreateTable('base_acl_permission',
			'id I4 AUTO KEY,'.
			'name C(255)',
			array('constraints' => ''));
		DB::CreateTable('base_acl_rules',
			'id I4 AUTO KEY,'.
			'permission_id I',
			array('constraints' => ', FOREIGN KEY (permission_id) REFERENCES base_acl_permission(id)'));
		DB::CreateTable('base_acl_rules_clearance',
			'id I4 AUTO KEY,'.
			'rule_id I,'.
			'clearance C(64)',
			array('constraints' => ', FOREIGN KEY (rule_id) REFERENCES base_acl_rules(id)'));
		DB::CreateTable('base_acl_clearance',
			'id I4 AUTO KEY,'.
			'callback C(128)',
			array('constraints' => ''));
		DB::Execute('INSERT INTO base_acl_clearance (callback) VALUES (%s)', array('Base_AclCommon::basic_clearance'));
		Base_ThemeCommon::install_default_theme($this->get_type());
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		DB::DropTable('base_acl_clearance');
		return true;
	}
	
	public function version() {
		return array('1.0.0');
	}

	public function requires($v) {
		return array(
				array('name'=>'Base/Lang', 'version'=>0),
				array('name'=>'Base/Theme', 'version'=>0)
			);
	}

	public static function simple_setup() {
		return __('EPESI Core');
	}
}
?>
