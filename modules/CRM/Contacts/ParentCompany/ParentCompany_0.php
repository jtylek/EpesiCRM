<?php
/**
 * Adds parent company field to companies.
 * @author shacky@poczta.fm
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-CRM/Contacts
 * @subpackage ParentCompany
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Contacts_ParentCompany extends Module {

	public function body() {
	
	}

	public function parent_company_addon($arg) {
		$rb = $this->init_module('Utils/RecordBrowser','company','parent_company_addon');
		$rb->set_defaults(array(	'country'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_country'),
									'zone'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_state'),
									'permission'=>Base_User_SettingsCommon::get('CRM_Common','default_record_permission'),
									'parent_company'=>$arg['id']));
		$this->display_module($rb, array(array('parent_company'=>$arg['id']), array(), array('company_name'=>'ASC')), 'show_data');
	}
	
}

?>