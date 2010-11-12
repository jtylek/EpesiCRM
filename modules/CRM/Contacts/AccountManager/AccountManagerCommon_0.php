<?php
/**
 * Activities history for Company and Contacts
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage contacts-accountmanager
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Contacts_AccountManagerCommon extends ModuleCommon {

	public static function crits_accountmanager() {
		return array('(company_name'=>CRM_ContactsCommon::get_main_company(),'|additional_work'=>array(CRM_ContactsCommon::get_main_company()));
	}

}

?>