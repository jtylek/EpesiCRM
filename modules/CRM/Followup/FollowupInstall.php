<?php
/**
 *
 * @author abisaga@telaxus.com
 * @copyright abisaga@telaxus.com
 * @license SPL
 * @version 0.9
 * @package crm-Followup
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_FollowupInstall extends ModuleInstall {

	public function install() {
		Base_LangCommon::install_translations($this->get_type());
		Base_ThemeCommon::install_default_theme('CRM/Followup');
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('CRM/Followup');
		return true;
	}

	public function version() {
		return array("0.9");
	}

	public function requires($v) {
		return array(
			array('name'=>'Base/Theme','version'=>0),
			array('name'=>'Base/Lang','version'=>0),
			array('name'=>'Base/User/Settings','version'=>0),
			array('name'=>'CRM/Contacts','version'=>0));
	}

	public static function info() {
		return array(
			'Description'=>'',
			'Author'=>'abisaga@telaxus.com',
			'License'=>'SPL');
	}

	public static function simple_setup() {
		return true;
	}

}

?>
