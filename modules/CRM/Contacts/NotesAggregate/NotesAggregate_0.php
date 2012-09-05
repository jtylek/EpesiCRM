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

class CRM_Contacts_NotesAggregate extends Module {
	public function contact_addon($contact) {
		$attachment_groups = array();
		
		if (ModuleManager::is_installed('CRM_Meeting')>=0) {
			$ret = DB::Execute('SELECT id FROM crm_meeting_data_1 WHERE f_customers '.DB::like().' '.DB::Concat(DB::qstr('%'), DB::qstr('\_\_P:'.$contact['id'].'\_\_'), DB::qstr('%')));
			while ($row = $ret->FetchRow()) $attachment_groups[] = 'crm_meeting/'.$row['id'];
		}
		
		if (ModuleManager::is_installed('CRM_Tasks')>=0) {
			$ret = DB::Execute('SELECT id FROM task_data_1 WHERE f_customers '.DB::like().' '.DB::Concat(DB::qstr('%'), DB::qstr('\_\_P:'.$contact['id'].'\_\_'), DB::qstr('%')));
			while ($row = $ret->FetchRow()) $attachment_groups[] = 'task/'.$row['id'];
		}
		
		if (ModuleManager::is_installed('CRM_PhoneCall')>=0) {
			$ret = DB::Execute('SELECT id FROM phonecall_data_1 WHERE f_customer = %s', array('P:'.$contact['id']));
			while ($row = $ret->FetchRow()) $attachment_groups[] = 'phonecall/'.$row['id'];
		}
		
		if (ModuleManager::is_installed('Premium_SalesOpportunity')>=0) {
			$ret = DB::Execute('SELECT id FROM premium_salesopportunity_data_1 WHERE f_customers '.DB::like().' '.DB::Concat(DB::qstr('%'), DB::qstr('\_\_P:'.$contact['id'].'\_\_'), DB::qstr('%')));
			while ($row = $ret->FetchRow()) $attachment_groups[] = 'premium_salesopportunity/'.$row['id'];
		}
		
		if (Base_User_SettingsCommon::get('CRM/Contacts/NotesAggregate', 'show_all_notes'))
			$attachment_groups[] = 'contact/'.$contact['id'];

		$a = $this->init_module('Utils/Attachment',array($attachment_groups));
		$this->display_module($a);
	}

	public function company_addon($company) {
		$attachment_groups = array();
		
		$ids = array('C:'.$company['id']);
		$cont = CRM_ContactsCommon::get_contacts(array('(company_name'=>$company['id'],'|related_companies'=>array(CRM_ContactsCommon::get_main_company())));
		foreach ($cont as $k=>$v) {
			$ids[] = 'P:'.$v['id'];
			$attachment_groups[] = 'contact/'.$v['id'];
		}
		$multi_ids = array();
		$single_ids = array();
		
		foreach ($ids as $id) $multi_ids[] = DB::Concat(DB::qstr('%'), DB::qstr('\_\_'.$id.'\_\_'), DB::qstr('%'));
		foreach ($ids as $id) $single_ids[] = DB::qstr($id);
		$multi_ids = implode(' OR f_customers '.DB::like().' ', $multi_ids);
		$single_ids = implode(' OR f_customer = ', $single_ids);
		
		if (ModuleManager::is_installed('CRM_Meeting')>=0) {
			$ret = DB::Execute('SELECT id FROM crm_meeting_data_1 WHERE f_customers '.DB::like().' '.$multi_ids);
			while ($row = $ret->FetchRow()) $attachment_groups[] = 'crm_meeting/'.$row['id'];
		}
		
		if (ModuleManager::is_installed('CRM_Tasks')>=0) {
			$ret = DB::Execute('SELECT id FROM task_data_1 WHERE f_customers '.DB::like().' '.$multi_ids);
			while ($row = $ret->FetchRow()) $attachment_groups[] = 'task/'.$row['id'];
		}
		
		if (ModuleManager::is_installed('CRM_PhoneCall')>=0) {
			$ret = DB::Execute('SELECT id FROM phonecall_data_1 WHERE f_customer = '.$single_ids);
			while ($row = $ret->FetchRow()) $attachment_groups[] = 'phonecall/'.$row['id'];
		}
		
		if (ModuleManager::is_installed('Premium_SalesOpportunity')>=0) {
			$ret = DB::Execute('SELECT id FROM premium_salesopportunity_data_1 WHERE f_customers '.DB::like().' '.$multi_ids);
			while ($row = $ret->FetchRow()) $attachment_groups[] = 'premium_salesopportunity/'.$row['id'];
		}
		
		if (Base_User_SettingsCommon::get('CRM/Contacts/NotesAggregate', 'show_all_notes'))
			$attachment_groups[] = 'company/'.$company['id'];
		
		$a = $this->init_module('Utils/Attachment',array($attachment_groups));
		$this->display_module($a);
	}

	public function salesopportunity_addon($salesopportunity) {
		$attachment_groups = array();
		
		if (ModuleManager::is_installed('CRM_Meeting')>=0) {
			$ret = @DB::Execute('SELECT id FROM crm_meeting_data_1 WHERE f_opportunity = '.DB::qstr($salesopportunity['id']));
			if ($ret) while ($row = $ret->FetchRow()) $attachment_groups[] = 'crm_meeting/'.$row['id'];
		}
		
		if (ModuleManager::is_installed('CRM_Tasks')>=0) {
			$ret = @DB::Execute('SELECT id FROM task_data_1 WHERE f_opportunity = '.DB::qstr($salesopportunity['id']));
			if ($ret) while ($row = $ret->FetchRow()) $attachment_groups[] = 'task/'.$row['id'];
		}
		
		if (ModuleManager::is_installed('CRM_PhoneCall')>=0) {
			$ret = @DB::Execute('SELECT id FROM phonecall_data_1 WHERE f_opportunity = '.DB::qstr($salesopportunity['id']));
			if ($ret) while ($row = $ret->FetchRow()) $attachment_groups[] = 'phonecall/'.$row['id'];
		}
		
		if (Base_User_SettingsCommon::get('CRM/Contacts/NotesAggregate', 'show_all_notes'))
			$attachment_groups[] = 'premium_salesopportunity/'.$salesopportunity['id'];
		
		$a = $this->init_module('Utils/Attachment',array($attachment_groups));
		$this->display_module($a);
	}
}

?>
