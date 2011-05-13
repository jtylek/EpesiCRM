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
		$this->display_module($rb, array(array('parent_company'=>$arg['id']), array(), array('company_name'=>'ASC')), 'show_data');
	}
	
}

?>