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
	private $theme;

	public function company_activities($me) {
		$this->theme = $this->pack_module('Base/Theme');
		$this->filters();
		$this->theme->display();
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
		if ($this->display['events']) $events = DB::GetAll('SELECT * FROM crm_calendar_event AS cce WHERE EXISTS (SELECT contact FROM crm_calendar_event_group_emp AS ccegp WHERE ccegp.id=cce.id AND (false'.$db_string.')) OR EXISTS (SELECT contact FROM crm_calendar_event_group_cus AS ccegc WHERE ccegc.id=cce.id AND (false'.$db_string.')) ORDER BY start DESC', array_merge($ids, $ids));
		$crits = array('(employees'=>$ids, '|customers'=>$ids);
		if (!$this->display['closed']) $crits['!status'] = 2;
		if ($this->display['tasks']) $tasks = CRM_TasksCommon::get_tasks($crits, array(), array('deadline'=>'DESC'));
		$crits = array('(employees'=>$ids, '|contact'=>$ids);
		if (!$this->display['closed']) $crits['!status'] = 2;
		if ($this->display['phonecalls']) $phonecalls = CRM_PhoneCallCommon::get_phonecalls($crits, array(), array('date_and_time'=>'DESC'));
		$this->display_activities($events, $tasks, $phonecalls);
	}

	public function contact_activities($me) {
		$is_employee = false;
		if (is_array($me['company_name']) && in_array(CRM_ContactsCommon::get_main_company(), $me['company_name'])) $is_employee = true;
		$this->theme = $this->pack_module('Base/Theme');
		$this->filters();
		$this->theme->assign('new_event', '<a '.Utils_TooltipCommon::open_tag_attrs($this->lang->t('New Event')).' '.CRM_CalendarCommon::get_new_event_href(array(($is_employee?'emp_id':'cus_id')=>$me['id'])).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Calendar','icon-small.png').'"></a>');
		$this->theme->assign('new_task', '<a '.Utils_TooltipCommon::open_tag_attrs($this->lang->t('New Task')).' '.Utils_RecordBrowserCommon::create_new_record_href('task', array('page_id'=>md5('crm_tasks'),'deadline'=>date('Y-m-d H:i:s', strtotime('+1 day')),($is_employee?'employees':'customers')=>$me['id'])).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Tasks','icon-small.png').'"></a>');
		$this->theme->assign('new_phonecall', '<a '.Utils_TooltipCommon::open_tag_attrs($this->lang->t('New Phonecall')).' '.Utils_RecordBrowserCommon::create_new_record_href('phonecall', array('date_and_time'=>date('Y-m-d H:i:s'),'contact'=>$me['id'],'company_name'=>(isset($me['company_name'][0])?$me['company_name'][0]:''))).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_PhoneCall','icon-small.png').'"></a>');
//		print('<a '.Utils_RecordBrowserCommon::create_new_record_href('phonecall', array('date_and_time'=>date('Y-m-d H:i:s'),'contact'=>$me['id'],'company_name'=>(isset($me['company_name'][0])?$me['company_name'][0]:''))).'>New Phonecall</a>');
//		print('<a '.Utils_RecordBrowserCommon::create_new_record_href('task', array('page_id'=>md5('crm_tasks'),'deadline'=>date('Y-m-d H:i:s', strtotime('+1 day')),($is_employee?'employees':'customers')=>$me['id'])).'>New Task</a>');
//		print('<a '.CRM_CalendarCommon::get_new_event_href(array(($is_employee?'emp_id':'cus_id')=>$me['id'])).'>New Event</a>');
		$this->theme->display();
		$events = null;
		$tasks = null;
		$phonecalls = null;
		if ($this->display['events']) $events = DB::GetAll('SELECT * FROM crm_calendar_event AS cce WHERE EXISTS (SELECT contact FROM crm_calendar_event_group_emp AS ccegp WHERE ccegp.id=cce.id AND contact=%d) OR EXISTS (SELECT contact FROM crm_calendar_event_group_cus AS ccegc WHERE ccegc.id=cce.id AND contact=%d) ORDER BY start DESC', array($me['id'], $me['id']));
		$crits = array('(employees'=>$me['id'], '|customers'=>$me['id']);
		if (!$this->display['closed']) $crits['!status'] = 2;
		if ($this->display['tasks']) $tasks = CRM_TasksCommon::get_tasks($crits, array(), array('deadline'=>'DESC'));
		$crits = array('(employees'=>$me['id'], '|contact'=>$me['id']);
		if (!$this->display['closed']) $crits['!status'] = 2;
		if ($this->display['phonecalls']) $phonecalls = CRM_PhoneCallCommon::get_phonecalls($crits, array(), array('date_and_time'=>'DESC'));
		$this->display_activities($events, $tasks, $phonecalls);
	}
	
	public function filters() {	
		$this->lang = $this->init_module('Base/Lang');
		$form = $this->init_module('Libs/QuickForm');
		$form->addElement('header', 'display', $this->lang->t('Display'));
		$form->addElement('checkbox', 'events', $this->lang->t('Events'), null, array('onchange'=>$form->get_submit_form_js()));
		$form->addElement('checkbox', 'tasks', $this->lang->t('Tasks'), null, array('onchange'=>$form->get_submit_form_js()));
		$form->addElement('checkbox', 'phonecalls', $this->lang->t('Phone Calls'), null, array('onchange'=>$form->get_submit_form_js()));
		$form->addElement('checkbox', 'closed', $this->lang->t('Closed'), null, array('onchange'=>$form->get_submit_form_js()));
		$old_display = $this->get_module_variable('display_options', array('events'=>1, 'tasks'=>1, 'phonecalls'=>1, 'closed'=>0));
		$form->setDefaults($old_display);
		$form->assign_theme('form',$this->theme);
		$this->display = $form->exportValues();
		foreach(array('events', 'tasks', 'phonecalls', 'closed') as $v) if (!isset($this->display[$v])) $this->display[$v] = false;
		$this->set_module_variable('display_options', $this->display);
	}
	
	public function display_activities($events, $tasks, $phonecalls){
		$gb = $this->init_module('Utils/GenericBrowser','activities','activities');
		$gb->set_table_columns(array(	array('name'=>$this->lang->t('Type'), 'wrapmode'=>'nowrap', 'width'=>1),
										array('name'=>$this->lang->t('Subject'), 'width'=>20),
										array('name'=>$this->lang->t('Date/Deadline'), 'wrapmode'=>'nowrap', 'width'=>1),
										array('name'=>$this->lang->t('Employees'), 'width'=>11),
										array('name'=>$this->lang->t('Customers'), 'width'=>11)
										));
		$amount = 0;
		if ($this->display['events']) $amount += count($events);
		if ($this->display['tasks']) $amount += count($tasks);
		if ($this->display['phonecalls']) $amount += count($phonecalls);
		$limit = $gb->get_limit($amount);
		$counter = 0;
		if ($this->display['events']) foreach($events as $v) {
			$counter++;
			if (!($counter>$limit['offset'] && $counter<=$limit['offset']+$limit['numrows'])) continue; 
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
		if ($this->display['tasks']) foreach($tasks as $v) {
			$counter++;
			if (!($counter>$limit['offset'] && $counter<=$limit['offset']+$limit['numrows'])) continue; 
			$gb_row = $gb->get_new_row();
			$gb_row->add_info(Utils_RecordBrowserCommon::get_html_record_info('task', isset($info)?$info:$v['id']));
			$gb_row->add_data(	$this->lang->t('Task'), 
								Utils_TasksCommon::display_title($v, false), 
								(!isset($v['is_deadline']) || !$v['is_deadline'])?$this->lang->t('No deadline'):Base_RegionalSettingsCommon::time2reg($v['deadline']), 
								CRM_ContactsCommon::display_contact($v, false, array('id'=>'employees', 'param'=>';CRM_ContactsCommon::contact_format_no_company')), 
								CRM_ContactsCommon::display_contact($v, false, array('id'=>'customers', 'param'=>';::')) 
							);
		}
		if ($this->display['phonecalls']) foreach($phonecalls as $v) {
			$counter++;
			if (!($counter>$limit['offset'] && $counter<=$limit['offset']+$limit['numrows'])) continue; 
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