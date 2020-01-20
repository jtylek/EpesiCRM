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

class CRM_Contacts_AccountManager extends Module {
	public function browse_mode_details($form, $filters, $vals, $crits, $rb_obj) {
		$me = CRM_ContactsCommon::get_my_record();
		$rb_obj->custom_defaults['account_manager'] = $me['id'];
		if(!$this->isset_module_variable('def_filter') && Base_User_SettingsCommon::get('CRM_Contacts_AccountManager','set_default')) {
			$form->setDefaults(array('filter__account_manager'=>'__PERSPECTIVE__'));
			$this->set_module_variable('def_filter', true);
		}
	}

}

?>