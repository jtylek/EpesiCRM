<?php
/**
 * Notes Aggregate for companies, contacts and sales opportunities
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage contacts-notesaggregate
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Contacts_NotesAggregateInstall extends ModuleInstall {

	public function install() {
		Utils_RecordBrowserCommon::new_addon('contact', 'CRM/Contacts/NotesAggregate', 'contact_addon', 'Related Notes');
		Utils_RecordBrowserCommon::new_addon('company', 'CRM/Contacts/NotesAggregate', 'company_addon', 'Related Notes');
		Utils_RecordBrowserCommon::new_addon('premium_salesopportunity', 'CRM/Contacts/NotesAggregate', 'salesopportunity_addon', 'Related Notes');
		return true;
	}
	
	public function uninstall() {
		Utils_RecordBrowserCommon::delete_addon('contact', 'CRM/Contacts/NotesAggregate', 'contact_addon');
		Utils_RecordBrowserCommon::delete_addon('company', 'CRM/Contacts/NotesAggregate', 'company_addon');
		Utils_RecordBrowserCommon::delete_addon('premium_salesopportunity', 'CRM/Contacts/NotesAggregate', 'salesopportunity_addon');
		return true;
	}
	
	public function version() {
		return array("1.0");
	}
	
	public function requires($v) {
		return array(
			array('name'=>'Utils/RecordBrowser', 'version'=>0)
		);
	}
	
	public static function info() {
		return array(
			'Description'=>'Notes Aggregate for companies, contacts and sales opportunities',
			'Author'=>'Arkadiusz Bisaga <abisaga@telaxus.com>',
			'License'=>'MIT');
	}
	
	public static function simple_setup() {
		return true;
	}
	
}

?>