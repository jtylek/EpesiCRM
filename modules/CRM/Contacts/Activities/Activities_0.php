<?php
/**
 * Activities history for Company and Contacts
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Arkadiusz Bisaga <abisaga@telaxus.com>
 * @license SPL
 * @version 0.1
 * @package crm-contacts--activities
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Contacts_Activities extends Module {
	private $lang;
	private $display;

	public function company_activities($me) {
		$this->filters();
		$cont = CRM_ContactsCommon::get_contacts(array('company_name'=>$me['id']), array('id'));
		$ids = array();
		$db_string = '';
		foreach($cont as $v) {
			$db_string .= ' OR contact=%d';
			$ids[] = $v['id'];
		}
		$events = null;
		$tasks = null;
		$phonecalls = null;
		if (isset($this->display['events'])) $events = DB::GetAll('SELECT * FROM crm_calendar_event AS cce WHERE EXISTS (SELECT contact FROM crm_calendar_event_group_emp AS ccegp WHERE ccegp.id=cce.id AND (false'.$db_string.')) OR EXISTS (SELECT contact FROM crm_calendar_event_group_cus AS ccegc WHERE ccegc.id=cce.id AND (false'.$db_string.')) ORDER BY start', array_merge($ids, $ids));
		if (isset($this->display['tasks'])) $tasks = CRM_TasksCommon::get_tasks(array('(employees'=>$ids, '|customers'=>$ids));
		if (isset($this->display['phonecalls'])) $phonecalls = CRM_PhoneCallCommon::get_phonecalls(array('(employees'=>$ids, '|customers'=>$ids));
		$this->display_activities($events, $tasks, $phonecalls);
	}

	public function contact_activities($me) {
		$this->filters();
		$events = null;
		$tasks = null;
		$phonecalls = null;
		if (isset($this->display['events'])) $events = DB::GetAll('SELECT * FROM crm_calendar_event AS cce WHERE EXISTS (SELECT contact FROM crm_calendar_event_group_emp AS ccegp WHERE ccegp.id=cce.id AND contact=%d) OR EXISTS (SELECT contact FROM crm_calendar_event_group_cus AS ccegc WHERE ccegc.id=cce.id AND contact=%d) ORDER BY start', array($me['id'], $me['id']));
		if (isset($this->display['tasks'])) $tasks = CRM_TasksCommon::get_tasks(array('(employees'=>$me['id'], '|customers'=>$me['id']));
		if (isset($this->display['phonecalls'])) $phonecalls = CRM_PhoneCallCommon::get_phonecalls(array('(employees'=>$me['id'], '|customers'=>$me['id']));
		$this->display_activities($events, $tasks, $phonecalls);
	}
	
	public function filters() {	
		$this->lang = $this->init_module('Base/Lang');
		$form = $this->init_module('Libs/QuickForm');
		$theme = $this->pack_module('Base/Theme');
		$form->addElement('header', 'display', $this->lang->t('Display'));
		$form->addElement('checkbox', 'events', $this->lang->t('Events'), null, array('onchange'=>$form->get_submit_form_js()));
		$form->addElement('checkbox', 'tasks', $this->lang->t('Tasks'), null, array('onchange'=>$form->get_submit_form_js()));
		$form->addElement('checkbox', 'phonecalls', $this->lang->t('Phone Calls'), null, array('onchange'=>$form->get_submit_form_js()));
		$form->setDefaults(array('events'=>1, 'tasks'=>1, 'phonecalls'=>1));
		$form->assign_theme('form',$theme);
		$theme->display();
		$this->display = $form->exportValues();
	}
	
	public function display_activities($events, $tasks, $phonecalls){
		$gb = $this->init_module('Utils/GenericBrowser','activities','activities');
		$gb->set_table_columns(array(	array('name'=>$this->lang->t('Type'), 'wrapmode'=>'nowrap', 'width'=>1),
										array('name'=>$this->lang->t('Subject'), 'width'=>20),
										array('name'=>$this->lang->t('Date/Deadline'), 'wrapmode'=>'nowrap', 'width'=>1),
										array('name'=>$this->lang->t('Employees'), 'width'=>11),
										array('name'=>$this->lang->t('Customers'), 'width'=>11)
										));
		if (isset($this->display['events'])) foreach($events as $v) {
			$employees = DB::GetAssoc('SELECT contact, contact FROM crm_calendar_event_group_emp AS ccegp WHERE ccegp.id=%d', array($v['id']));
			$customers = DB::GetAssoc('SELECT contact, contact FROM crm_calendar_event_group_cus AS ccegc WHERE ccegc.id=%d', array($v['id']));

			$title = '<a '.$this->create_callback_href(array($this, 'view_event'), array($v['id'])).'>'.$v['title'].'</a>';
			if (isset($v['description']) && $v['description']!='') $title = '<span '.Utils_TooltipCommon::open_tag_attrs($v['description'], false).'>'.$title.'</span>';
			$gb_row = $gb->get_new_row();
			$gb_row->add_data(	$this->lang->t('Event'),
								$title, 
								Base_RegionalSettingsCommon::time2reg($v['start']), 
								CRM_ContactsCommon::display_contact(array('employees'=>$employees), false, array('id'=>'employees', 'param'=>';CRM_ContactsCommon::contact_format_no_company')), 
								CRM_ContactsCommon::display_contact(array('customers'=>$customers), false, array('id'=>'customers', 'param'=>';::')) 
							);
		}
		if (isset($this->display['tasks'])) foreach($tasks as $v) {
			$gb_row = $gb->get_new_row();
			$gb_row->add_info(Utils_RecordBrowserCommon::get_html_record_info('task', isset($info)?$info:$v['id']));
			$gb_row->add_data(	$this->lang->t('Task'), 
								Utils_TasksCommon::display_title($v, false), 
								(!$v['is_deadline'])?$this->lang->t('No deadline'):Base_RegionalSettingsCommon::time2reg($v['deadline']), 
								CRM_ContactsCommon::display_contact($v, false, array('id'=>'employees', 'param'=>';CRM_ContactsCommon::contact_format_no_company')), 
								CRM_ContactsCommon::display_contact($v, false, array('id'=>'customers', 'param'=>';::')) 
							);
		}
		if (isset($this->display['phonecalls'])) foreach($phonecalls as $v) {
			$gb_row = $gb->get_new_row();
			$gb_row->add_info(Utils_RecordBrowserCommon::get_html_record_info('phonecall', isset($info)?$info:$v['id']));
			$gb_row->add_data(	$this->lang->t('Phone Call'), 
								CRM_PhoneCallCommon::display_subject($v), 
								Base_RegionalSettingsCommon::time2reg($v['date_and_time']), 
								CRM_ContactsCommon::display_contact($v, false, array('id'=>'employees', 'param'=>';CRM_ContactsCommon::contact_format_no_company')), 
								CRM_PhoneCallCommon::display_contact_name($v, false) 
							);
		}
		$this->display_module($gb);
	}
	
	public function view_event($id) {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('CRM_Calendar_Event','view',$id);
	}

	
}

?>