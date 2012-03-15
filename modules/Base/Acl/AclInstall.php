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
		DB::CreateTable('base_acl_clearance',
			'id I4 AUTO KEY,'.
			'callback C(128)',
			array('constraints' => ''));
		DB::Execute('INSERT INTO base_acl_clearance (callback) VALUES (%s)', array('Base_AclCommon::basic_clearance'));
		Base_LangCommon::install_translations($this->get_type());
		return true;
	}
	
	public function uninstall() {
		DB::DropTable('base_acl_clearance');
		return true;
	}
	
	public function version() {
		return array('1.0.0');
	}

	public function requires($v) {
		return array(array('name'=>'Base/Lang', 'version'=>0));
	}

	public static function simple_setup() {
		return 'epesi Core';
	}
}
?>
