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

class CRM_Contacts_AccountManager extends Module {
	public function browse_mode_details($form, $filters, $vals, $crits, $dont_hide, $rb_obj) {
		$me = CRM_ContactsCommon::get_my_record();
		$rb_obj->custom_defaults['account_manager'] = $me['id'];
		if(!$this->isset_module_variable('def_filter') && Base_User_SettingsCommon::get('CRM_Contacts_AccountManager','set_default')) {
			$form->setDefaults(array('filter__account_manager'=>'__PERSPECTIVE__'));
			$this->set_module_variable('def_filter', true);
		}
		$dont_hide = true;
	}

}

?>