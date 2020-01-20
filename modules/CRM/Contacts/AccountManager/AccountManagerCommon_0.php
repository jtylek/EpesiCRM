<?php
/**
 * Activities history for Company and Contacts
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-crm
 * @subpackage contacts-accountmanager
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Contacts_AccountManagerCommon extends ModuleCommon {

	public static function crits_accountmanager() {
		return array('(company_name'=>CRM_ContactsCommon::get_main_company(),'|related_companies'=>array(CRM_ContactsCommon::get_main_company()));
	}

	public static function user_settings() {
		return array(__('Browsing records')=>array(
				array('name'=>'contact_header', 'label'=>__('Filtering Companies'), 'type'=>'header'),
				array('name'=>'set_default','label'=>__('Account Manager - default set to Perspective'),'type'=>'checkbox','default'=>0)
					));
	}

}

?>