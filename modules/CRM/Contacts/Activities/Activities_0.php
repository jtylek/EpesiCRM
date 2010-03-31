<?php
/**
 * Activities history for Company and Contacts
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage contacts-activities
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Contacts_Activities extends Module {
	private $lang;
	private $display;
	private $theme;
	private $activities_date = 0;

	public function company_activities($me) {
		$this->theme = $this->pack_module('Base/Theme');
		$this->filters();
		$this->theme->display();
		$cont = CRM_ContactsCommon::get_contacts(array('company_name'=>$me['id']), array('id'));
		$ids = array();
		$db_string = '';
		foreach($cont as $v) {
//			$db_string .= ' OR contact=%d';
			$ids[] = $v['id'];
		}
		$events = null;
		$tasks = null;
		$phonecalls = null;
		$date_filter = '';
//		if ($this->activities_date==0) $date_filter = ' cce.starts>'.Base_RegionalSettingsCommon::reg2time(date('Y-m-d 0:00:00')).' AND';
//		if ($this->activities_date==1) $date_filter = ' cce.ends<'.Base_RegionalSettingsCommon::reg2time(date('Y-m-d 0:00:00')).' AND';
		// TODO: recurring events
		// TODO: check if statsu<2 if fine for closed
		if ($this->display['events'] && $ids) {
			if ($this->activities_date==0)
				$events = CRM_Calendar_EventCommon::get_all(time(),time()+365*24*3600,'('.implode(',',$ids).')');
			elseif ($this->activities_date==1)
				$events = CRM_Calendar_EventCommon::get_all(0,time(),'('.implode(',',$ids).')');
			else
				$events = CRM_Calendar_EventCommon::get_all(0,time()+365*24*3600,'('.implode(',',$ids).')');
		}
		//$events = DB::GetAll('SELECT * FROM crm_calendar_event AS cce WHERE cce.deleted=0 AND '.$date_filter.(!$this->display['closed']?' cce.status<2 AND':'').' (EXISTS (SELECT contact FROM crm_calendar_event_group_emp AS ccegp WHERE ccegp.id=cce.id AND (false'.$db_string.')) OR EXISTS (SELECT contact FROM crm_calendar_event_group_cus AS ccegc WHERE ccegc.id=cce.id AND (false'.$db_string.'))) ORDER BY starts DESC', array_merge($ids, $ids));
		$crits = array('(employees'=>$ids, '|customers'=>'P:'.$ids);
		if ($this->activities_date==0) {
			$crits['(>=deadline'] = date('Y-m-d');
			$crits['|deadline'] = '';
		}
		if ($this->activities_date==1) {
			$crits['(<deadline'] = date('Y-m-d');
			$crits['|deadline'] = '';
		}
		if (!$this->display['closed']) $crits['!status'] = array(2,3);
		if ($this->display['tasks']) $tasks = CRM_TasksCommon::get_tasks($crits, array(), array('deadline'=>'DESC'));
		$crits = array('(employees'=>$ids, '|customer'=>'P:'.$ids);
		if ($this->activities_date==0) $crits['>=date_and_time'] = date('Y-m-d H:i:s',Base_RegionalSettingsCommon::reg2time(date('Y-m-d 0:00:00')));
		if ($this->activities_date==1) $crits['<date_and_time'] = date('Y-m-d H:i:s',Base_RegionalSettingsCommon::reg2time(date('Y-m-d 0:00:00')));
		if (!$this->display['closed']) $crits['!status'] = array(2,3);
		if ($this->display['phonecalls']) $phonecalls = CRM_PhoneCallCommon::get_phonecalls($crits, array(), array('date_and_time'=>'DESC'));
		$this->display_activities($events, $tasks, $phonecalls);
	}

	public function contact_activities($me) {
		$this->theme = $this->pack_module('Base/Theme');
		$this->filters();
		$this->theme->display();
		$events = null;
		$tasks = null;
		$phonecalls = null;
		$date_filter = '';
//		if ($this->activities_date==0) $date_filter = ' cce.starts>'.Base_RegionalSettingsCommon::reg2time(date('Y-m-d 0:00:00')).' AND';
//		if ($this->activities_date==1) $date_filter = ' cce.ends<'.Base_RegionalSettingsCommon::reg2time(date('Y-m-d 0:00:00')).' AND';
		if ($this->display['events']) {
			//$events = DB::GetAll('SELECT * FROM crm_calendar_event AS cce WHERE cce.deleted=0 AND'.$date_filter.(!$this->display['closed']?' cce.status<2 AND':'').' (EXISTS (SELECT contact FROM crm_calendar_event_group_emp AS ccegp WHERE ccegp.id=cce.id AND contact=%d) OR EXISTS (SELECT contact FROM crm_calendar_event_group_cus AS ccegc WHERE ccegc.id=cce.id AND contact=%d)) ORDER BY starts DESC', array($me['id'], $me['id']));
			if ($this->activities_date==0)
				$events = CRM_Calendar_EventCommon::get_all(time(),time()+365*24*3600,'('.$me['id'].')');
			elseif ($this->activities_date==1)
				$events = CRM_Calendar_EventCommon::get_all(0,time(),'('.$me['id'].')');
			else
				$events = CRM_Calendar_EventCommon::get_all(date('Y-m-d H:i:s',0),date('Y-m-d H:i:s',time()+365*24*3600),'('.$me['id'].')');
		}
		$crits = array('(employees'=>$me['id'], '|customers'=>'P:'.$me['id']);
		if ($this->activities_date==0) {
			$crits['(>=deadline'] = date('Y-m-d');
			$crits['|deadline'] = '';
		}
		if ($this->activities_date==1) {
			$crits['(<deadline'] = date('Y-m-d');
			$crits['|deadline'] = '';
		}
		if (!$this->display['closed']) $crits['!status'] = array(2,3);
		if ($this->display['tasks']) $tasks = CRM_TasksCommon::get_tasks($crits, array(), array('deadline'=>'DESC'));
		$crits = array('(employees'=>$me['id'], '|customer'=>'P:'.$me['id']);
		if ($this->activities_date==0) $crits['>=date_and_time'] = date('Y-m-d H:i:s',Base_RegionalSettingsCommon::reg2time(date('Y-m-d 0:00:00')));
		if ($this->activities_date==1) $crits['<date_and_time'] = date('Y-m-d H:i:s',Base_RegionalSettingsCommon::reg2time(date('Y-m-d 0:00:00')));
		if (!$this->display['closed']) $crits['!status'] = array(2,3);
		if ($this->display['phonecalls']) $phonecalls = CRM_PhoneCallCommon::get_phonecalls($crits, array(), array('date_and_time'=>'DESC'));
		$this->display_activities($events, $tasks, $phonecalls);
	}
	
	public function filters() {	
		$form = $this->init_module('Libs/QuickForm');
		$form->addElement('header', 'display', $this->t('Show'));
		$form->addElement('checkbox', 'events', $this->t('Events'), null, array('onchange'=>$form->get_submit_form_js()));
		$form->addElement('checkbox', 'tasks', $this->t('Tasks'), null, array('onchange'=>$form->get_submit_form_js()));
		$form->addElement('checkbox', 'phonecalls', $this->t('Phone Calls'), null, array('onchange'=>$form->get_submit_form_js()));
		$form->addElement('select', 'activities_date', $this->t('Activities date'), array(0=>$this->t('Future'), 1=>$this->t('Past'), 2=>$this->t('All time')), array('onchange'=>$form->get_submit_form_js()));
		$form->addElement('checkbox', 'closed', $this->t('Closed'), null, array('onchange'=>$form->get_submit_form_js()));
		$old_display = $this->get_module_variable('display_options', array('events'=>1, 'tasks'=>1, 'phonecalls'=>1, 'closed'=>1, 'activities_date'=>2));
		$form->setDefaults($old_display);
		$form->assign_theme('form',$this->theme);
		if ($form->validate()) {
			$this->display = $form->exportValues();
			foreach(array('events', 'tasks', 'phonecalls', 'closed', 'activities_date') as $v) if (!isset($this->display[$v])) $this->display[$v] = false;
		} else $this->display = $old_display;
		$this->activities_date = isset($this->display['activities_date'])?$this->display['activities_date']:0;
		$this->set_module_variable('display_options', $this->display);
	}
	
	public function display_activities($events, $tasks, $phonecalls){
		$gb = $this->init_module('Utils/GenericBrowser','activities','activities');
		$gb->set_table_columns(array(	array('name'=>$this->t('Type'), 'wrapmode'=>'nowrap', 'width'=>1),
										array('name'=>$this->t('Subject'), 'width'=>20),
										array('name'=>$this->t('Date/Deadline'), 'wrapmode'=>'nowrap', 'width'=>1),
										array('name'=>$this->t('Employees'), 'width'=>11),
										array('name'=>$this->t('Customers'), 'width'=>11),
										array('name'=>$this->t('Attachments'), 'width'=>3)
										));
		$amount = 0;
		if ($this->display['events']) $amount += count($events);
		if ($this->display['tasks']) $amount += count($tasks);
		if ($this->display['phonecalls']) $amount += count($phonecalls);
		$limit = $gb->get_limit($amount);
		
		for($i=0; $i<$limit['offset']+$limit['numrows'] && $i<$amount; $i++) {
			if ($this->display['events'] && count($events)) {
				$ev = current($events);
			} else {
				$ev = array('start' => -1);
			}
			if ($this->display['tasks'] && count($tasks)) {
				$t = current($tasks);
				if(!$t['deadline']) $t['deadline'] = 0;
				else $t['deadline'] = strtotime($t['deadline']);
			} else {
				$t = array('deadline' => -1);
			}
			if ($this->display['phonecalls'] && count($phonecalls)) {
				$ph = current($phonecalls);
				$ph['date_and_time'] = strtotime($ph['date_and_time']);
			} else {
				$ph = array('date_and_time' => -1);
			}
			$maxt = max($ev['start'],$t['deadline'],$ph['date_and_time']);
			$gb_row = $gb->get_new_row();
			if($ev['start'] == $maxt) {
				$v = array_shift($events);
				if($i>=$limit['offset']) {
//					$employees = DB::GetAssoc('SELECT contact, contact FROM crm_calendar_event_group_emp AS ccegp WHERE ccegp.id=%d', array($v['id']));
//					$customers = DB::GetAssoc('SELECT contact, contact FROM crm_calendar_event_group_cus AS ccegc WHERE ccegc.id=%d', array($v['id']));
					$event = CRM_Calendar_EventCommon::get($v['id']);
					$customers = array();
					foreach($event['customers'] as $cust)
						if(preg_match('/^P:([0-9]+)$/',$cust,$reqs)) $customers[$reqs[1]] = $reqs[1];

					$title = '<a '.$this->create_callback_href(array($this, 'view_event'), array($v['id'])).'>'.$v['title'].'</a>';
					if (isset($v['description']) && $v['description']!='') $title = '<span '.Utils_TooltipCommon::open_tag_attrs($v['description'], false).'>'.$title.'</span>';
					$gb_row->add_data(	$this->t('Event'),
								$title, 
								Base_RegionalSettingsCommon::time2reg($v['start'],$v['duration']==-1?false:2), 
								CRM_ContactsCommon::display_contact(array('employees'=>$event['employees']), false, array('id'=>'employees', 'param'=>';CRM_ContactsCommon::contact_format_no_company')), 
								CRM_ContactsCommon::display_contact(array('customers'=>$customers), false, array('id'=>'customers', 'param'=>';::')), 
								Utils_AttachmentCommon::count('CRM/Calendar/Event/'.$v['id'])
							);
				}
			} elseif($t['deadline'] == $maxt) {
				$v = array_shift($tasks);
				if($i>=$limit['offset']) {
					$gb_row->add_info(Utils_RecordBrowserCommon::get_html_record_info('task', isset($info)?$info:$v['id']));
					$gb_row->add_data(	$this->t('Task'), 
								CRM_TasksCommon::display_title($v, false), 
								(!isset($v['deadline']) || !$v['deadline'])?$this->t('No deadline'):Base_RegionalSettingsCommon::time2reg($v['deadline'],false,true,false), 
								CRM_ContactsCommon::display_contact($v, false, array('id'=>'employees', 'param'=>';CRM_ContactsCommon::contact_format_no_company')), 
								CRM_ContactsCommon::display_company_contact($v, false, array('id'=>'customers')), 
								Utils_AttachmentCommon::count('CRM/Tasks/'.$v['id'])
							);
				}
			} else {
				$v = array_shift($phonecalls);
				if($i>=$limit['offset']) {
					$gb_row->add_info(Utils_RecordBrowserCommon::get_html_record_info('phonecall', isset($info)?$info:$v['id']));
					$gb_row->add_data(	$this->t('Phone Call'), 
								CRM_PhoneCallCommon::display_subject($v), 
								Base_RegionalSettingsCommon::time2reg($v['date_and_time'],2), 
								CRM_ContactsCommon::display_contact($v, false, array('id'=>'employees', 'param'=>';CRM_ContactsCommon::contact_format_no_company')), 
								CRM_PhoneCallCommon::display_contact_name($v, false), 
								Utils_AttachmentCommon::count('CRM/PhoneCall/'.$v['id'])
							);
				}
			}
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