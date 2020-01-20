<?php
/**
 * Activities history for Company and Contacts
 *
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-crm
 * @subpackage contacts-activities
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Contacts_ActivitiesInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme('CRM/Contacts/Activities');
		Utils_RecordBrowserCommon::new_addon('company', 'CRM/Contacts/Activities', 'company_activities', _M('Activities'));
		Utils_RecordBrowserCommon::new_addon('contact', 'CRM/Contacts/Activities', 'contact_activities', _M('Activities'));
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('CRM/Contacts/Activities');
		Utils_RecordBrowserCommon::delete_addon('company', 'CRM/Contacts/Activities', 'company_activities');
		Utils_RecordBrowserCommon::delete_addon('contact', 'CRM/Contacts/Actitivies', 'contact_activities');
		return true;
	}
	
	public function version() {
		return array("1.0");
	}
	
	public function requires($v) {
		return array(
			array('name'=>Utils_RecordBrowserInstall::module_name(), 'version'=>0),
			array('name'=>Utils_AttachmentInstall::module_name(), 'version'=>0),
			array('name'=>Base_LangInstall::module_name(), 'version'=>0),
			array('name'=>Base_AclInstall::module_name(), 'version'=>0),
			array('name'=>Data_CountriesInstall::module_name(), 'version'=>0)
		);
	}
	
	public static function info() {
		return array(
			'Description'=>'Activities history for Company and Contacts',
			'Author'=>'Arkadiusz Bisaga, Janusz Tylek',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return 'CRM';
	}
	
}

?>