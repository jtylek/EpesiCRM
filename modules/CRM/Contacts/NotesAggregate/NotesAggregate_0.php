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
		
		$ret = DB::Execute('SELECT id FROM crm_meeting_data_1 WHERE f_customers LIKE '.DB::Concat(DB::qstr('%'), DB::qstr('\_\_P:'.$contact['id'].'\_\_'), DB::qstr('%')));
		while ($row = $ret->FetchRow()) $attachment_groups[] = 'crm_meeting/'.$row['id'];
		
		$ret = DB::Execute('SELECT id FROM task_data_1 WHERE f_customers LIKE '.DB::Concat(DB::qstr('%'), DB::qstr('\_\_P:'.$contact['id'].'\_\_'), DB::qstr('%')));
		while ($row = $ret->FetchRow()) $attachment_groups[] = 'task/'.$row['id'];
		
		$ret = DB::Execute('SELECT id FROM phonecall_data_1 WHERE f_customer = "P:'.$contact['id'].'"');
		while ($row = $ret->FetchRow()) $attachment_groups[] = 'phonecall/'.$row['id'];
		
		$a = $this->init_module('Utils/Attachment',array($attachment_groups));
		$this->display_module($a);
	}

	public function company_addon($company) {
		$attachment_groups = array();
		
		$ids = array('C:'.$company['id']);
		$cont = CRM_ContactsCommon::get_contacts(array('company_name'=>$company['id']));
		foreach ($cont as $k=>$v) $ids[] = 'P:'.$v['id'];
		$multi_ids = array();
		$single_ids = array();
		
		foreach ($ids as $id) $multi_ids[] = DB::Concat(DB::qstr('%'), DB::qstr('\_\_'.$id.'\_\_'), DB::qstr('%'));
		foreach ($ids as $id) $single_ids[] = DB::qstr($id);
		$multi_ids = implode(' OR f_customers LIKE ', $multi_ids);
		$single_ids = implode(' OR f_customer = ', $single_ids);
		
		$ret = DB::Execute('SELECT id FROM crm_meeting_data_1 WHERE f_customers LIKE '.$multi_ids);
		while ($row = $ret->FetchRow()) $attachment_groups[] = 'crm_meeting/'.$row['id'];
		
		$ret = DB::Execute('SELECT id FROM task_data_1 WHERE f_customers LIKE '.$multi_ids);
		while ($row = $ret->FetchRow()) $attachment_groups[] = 'task/'.$row['id'];
		
		$ret = DB::Execute('SELECT id FROM phonecall_data_1 WHERE f_customer = '.$single_ids);
		while ($row = $ret->FetchRow()) $attachment_groups[] = 'phonecall/'.$row['id'];
		
		$a = $this->init_module('Utils/Attachment',array($attachment_groups));
		$this->display_module($a);
	}

	public function salesopportunity_addon($salesopportunity) {
		$attachment_groups = array();
		
		$ret = DB::Execute('SELECT id FROM crm_meeting_data_1 WHERE f_opportunity = '.DB::qstr($salesopportunity['id']));
		while ($row = $ret->FetchRow()) $attachment_groups[] = 'crm_meeting/'.$row['id'];
		
		$ret = DB::Execute('SELECT id FROM task_data_1 WHERE f_opportunity = '.DB::qstr($salesopportunity['id']));
		while ($row = $ret->FetchRow()) $attachment_groups[] = 'task/'.$row['id'];
		
		$ret = DB::Execute('SELECT id FROM phonecall_data_1 WHERE f_opportunity = '.DB::qstr($salesopportunity['id']));
		while ($row = $ret->FetchRow()) $attachment_groups[] = 'phonecall/'.$row['id'];
		
		$a = $this->init_module('Utils/Attachment',array($attachment_groups));
		$this->display_module($a);
	}
}

?>
