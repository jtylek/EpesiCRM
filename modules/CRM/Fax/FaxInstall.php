<?php
/**
 * Fax abstraction layer module
 * @author pbukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-CRM
 * @subpackage Fax
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_FaxInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());
		Base_AclCommon::add_permission(_M('Fax - Browse'),array('ACCESS:employee'));
		Base_AclCommon::add_permission(_M('Fax - Send'),array('ACCESS:employee'));
		$this->create_data_dir();
		return true;
	}
	
	public function uninstall() {
		Base_AclCommon::delete_permission('Fax - Browse');
		Base_AclCommon::delete_permission('Fax - Send');
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		return true;
	}
	
	public function version() {
		return array("0.5");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'CRM/Contacts','version'=>0),
			array('name'=>'Libs/QuickForm','version'=>0));
	}
	
	public static function info() {
		return array(
			'Description'=>'Fax abstraction layer module',
			'Author'=>'pbukowski@telaxus.com',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
        return array('package'=>__('CRM'), 'option'=>__('Fax'));
	}
	
}

?>