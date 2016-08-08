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

        $ids = array('contact/' . $contact['id']);

        if (ModuleManager::is_installed('CRM_Meeting')>=0) {
            $records = Utils_RecordBrowserCommon::get_records('crm_meeting', array('customers' => $ids), array());
            foreach ($records as $rec) {
                $attachment_groups[] = 'crm_meeting/' . $rec['id'];
            }
        }

        if (ModuleManager::is_installed('CRM_Tasks')>=0) {
            $records = Utils_RecordBrowserCommon::get_records('task', array('customers' => $ids), array());
            foreach ($records as $rec) {
                $attachment_groups[] = 'task/' . $rec['id'];
            }
        }

        if (ModuleManager::is_installed('CRM_PhoneCall')>=0) {
            $records = Utils_RecordBrowserCommon::get_records('phonecall', array('customer' => $ids), array());
            foreach ($records as $rec) {
                $attachment_groups[] = 'phonecall/' . $rec['id'];
            }
        }

        if (ModuleManager::is_installed('Premium_SalesOpportunity')>=0) {
            $records = Utils_RecordBrowserCommon::get_records('premium_salesopportunity', array('customers' => $ids), array());
            foreach ($records as $rec) {
                $attachment_groups[] = 'premium_salesopportunity/' . $rec['id'];
            }
        }
		
		if (Base_User_SettingsCommon::get('CRM/Contacts/NotesAggregate', 'show_all_notes'))
			$attachment_groups[] = 'contact/'.$contact['id'];

		$a = $this->init_module(Utils_Attachment::module_name(),array($attachment_groups));
        $a->set_multiple_group_mode();
		$this->display_module($a);
	}

	public function company_addon($company) {
		$attachment_groups = array();
		
		$ids = array('company/'.$company['id']);
        $crits = array('(company_name'      => $company['id'],
                       '|related_companies' => array($company['id']));
        $cont = CRM_ContactsCommon::get_contacts($crits);
		foreach ($cont as $k=>$v) {
			$ids[] = 'contact/'.$v['id'];
			$attachment_groups[] = 'contact/'.$v['id'];
		}

		if (ModuleManager::is_installed('CRM_Meeting')>=0) {
            $records = Utils_RecordBrowserCommon::get_records('crm_meeting', array('customers' => $ids), array());
            foreach ($records as $rec) {
                $attachment_groups[] = 'crm_meeting/' . $rec['id'];
            }
		}
		
		if (ModuleManager::is_installed('CRM_Tasks')>=0) {
            $records = Utils_RecordBrowserCommon::get_records('task', array('customers' => $ids), array());
            foreach ($records as $rec) {
                $attachment_groups[] = 'task/' . $rec['id'];
            }
		}
		
		if (ModuleManager::is_installed('CRM_PhoneCall')>=0) {
            $records = Utils_RecordBrowserCommon::get_records('phonecall', array('customer' => $ids), array());
            foreach ($records as $rec) {
                $attachment_groups[] = 'phonecall/' . $rec['id'];
            }
		}
		
		if (ModuleManager::is_installed('Premium_SalesOpportunity')>=0) {
            $records = Utils_RecordBrowserCommon::get_records('premium_salesopportunity', array('customers' => $ids), array());
            foreach ($records as $rec) {
                $attachment_groups[] = 'premium_salesopportunity/' . $rec['id'];
            }
		}
		
		if (Base_User_SettingsCommon::get('CRM/Contacts/NotesAggregate', 'show_all_notes'))
			$attachment_groups[] = 'company/'.$company['id'];

		$a = $this->init_module(Utils_Attachment::module_name(),array($attachment_groups));
        $a->set_multiple_group_mode();
		$this->display_module($a);
	}

	public function salesopportunity_addon($salesopportunity) {
		$attachment_groups = array();

        $related_crits = array('related' => 'premium_salesopportunity/' . $salesopportunity['id']);
		if (ModuleManager::is_installed('CRM_Meeting')>=0) {
            $records = Utils_RecordBrowserCommon::get_records('crm_meeting', $related_crits, array());
            foreach ($records as $rec) {
                $attachment_groups[] = 'crm_meeting/' . $rec['id'];
            }
		}
		
		if (ModuleManager::is_installed('CRM_Tasks')>=0) {
            $records = Utils_RecordBrowserCommon::get_records('task', $related_crits, array());
            foreach ($records as $rec) {
                $attachment_groups[] = 'task/' . $rec['id'];
            }
		}
		
		if (ModuleManager::is_installed('CRM_PhoneCall')>=0) {
            $records = Utils_RecordBrowserCommon::get_records('phonecall', $related_crits, array());
            foreach ($records as $rec) {
                $attachment_groups[] = 'phonecall/' . $rec['id'];
            }
		}
		
		if (Base_User_SettingsCommon::get('CRM/Contacts/NotesAggregate', 'show_all_notes'))
			$attachment_groups[] = 'premium_salesopportunity/'.$salesopportunity['id'];
		
		$a = $this->init_module(Utils_Attachment::module_name(),array($attachment_groups));
        $a->set_multiple_group_mode();
		$this->display_module($a);
	}
}

?>
