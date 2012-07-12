<?php
/**
 * Activities history for Company and Contacts
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage contacts-photo
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Contacts_PhotoInstall extends ModuleInstall {

	public function install() {
		Base_ThemeCommon::install_default_theme('CRM/Contacts/Photo');
		Utils_RecordBrowserCommon::set_tpl('contact', Base_ThemeCommon::get_template_filename('CRM/Contacts/Photo', 'Contact'));
		Utils_RecordBrowserCommon::register_processing_callback('contact', array('CRM_Contacts_PhotoCommon', 'submit_contact'));
		$this->create_data_dir();

        ModuleManager::include_common('CRM_Contacts_Photo', 0);
        DB::CreateTable(CRM_Contacts_PhotoCommon::table_name,
            'contact_id I4 KEY,'.
            'filename C(48) NOTNULL');
		return true;
	}
	
	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme('CRM/Contacts/Activities');
		Utils_RecordBrowserCommon::set_tpl('contact', Base_ThemeCommon::get_template_filename('CRM/Contacts', 'Contact'));
		Utils_RecordBrowserCommon::unregister_processing_callback('contact', array('CRM_Contacts_PhotoCommon', 'submit_contact'));

        $this->remove_data_dir();
        DB::DropTable(CRM_Contacts_PhotoCommon::table_name);
		return true;
	}
	
	public function version() {
		return array("1.0");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'CRM/Contacts', 'version'=>0),
			array('name'=>'Utils/Image', 'version'=>0)
		);
	}
	
	public static function info() {
		return array(
			'Description'=>'Photo module for Contacts',
			'Author'=>'Arkadiusz Bisaga <abisaga@telaxus.com>',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
        return array('package'=>__('CRM'), 'option'=>__('Contact Photo'));
	}
	
}

?>