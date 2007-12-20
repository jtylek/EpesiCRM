<?php
/**
 * Example event module
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package tests-calendar-event
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_Event extends Utils_Calendar_Event {
	private $lang;

	public function view($id) {
		if($this->is_back()) $this->back_to_calendar();
		$this->view_event('view', $id);
		Base_ActionBarCommon::add('back','Back',$this->create_back_href());
	}

	public function edit($id) {
		if($this->is_back()) $this->back_to_calendar();
		$this->view_event('edit',$id);
		Base_ActionBarCommon::add('back','Back',$this->create_back_href());
	}

	public function add($def_date,$timeless=false) {
		if($this->is_back()) $this->back_to_calendar();
		$this->view_event('new', $def_date, $timeless);
		Base_ActionBarCommon::add('back','Back',$this->create_back_href());
	}

	public function view_event($action, $id=null){
		if($this->is_back())
			return false;
		
		$timeless = 0;
		if($action == 'new') {
			$def = array(
				'date_s' => date('Y-m-d'), 
				'date_e' => time(), 
				'time_s' => date('H:i'), 
				'time_e' => date('H:i'),
				'repeatable'=>0, 
				'repeat_forever'=>1, 
				'access'=>0,
				'priority'=>0,
				'emp_id' => array(Acl::get_user())
			);
		} else {
			$event = DB::GetRow('SELECT * FROM crm_calendar_event WHERE id=%d', $id);
			if (Base_RegionalSettingsCommon::time_12h()) {
				$dtime_s = array('h'=>date('h',$event['start']),
									'i'=>date('i',$event['start']),
									'a'=>date('a',$event['start']));
				$dtime_e = array('h'=>date('h',$event['end']),
									'i'=>date('i',$event['end']),
									'a'=>date('a',$event['end']));
			} else {
				$dtime_s = array('H'=>date('H',$event['start']),
									'i'=>date('i',$event['start']));
				$dtime_e = array('H'=>date('H',$event['end']),
									'i'=>date('i',$event['end']));
			}
			$def = array(
				'date_s' => Base_RegionalSettingsCommon::server_date(substr($event['start'], 0, 10)),
				'date_e' => Base_RegionalSettingsCommon::server_date(substr($event['end'], 0, 10)),
				'time_s' => $dtime_s, 
				'time_e' => $dtime_e,
				'title'=>$event['title'],
				'description'=>$event['description'],
				'priority'=>$event['priority'],
				'timeless'=>$event['timeless'],
				'access'=>$event['access'],
				'created by' => Base_UserCommon::get_user_login($event['created_by']),
				'created on' => $event['created_on'],
				'edited by' => Base_UserCommon::get_user_login($event['edited_by']),
				'edited on' => $event['edited_on']
			);
			$def['emp_id'] = array();
			$timeless = $event['timeless'];
		}

		$this->lang = $this->pack_module('Base/Lang');
		$form = $this->init_module('Libs/QuickForm');

		$com = array();
		$ret = CRM_ContactsCommon::get_companies();
		foreach($ret as $id=>$data) {
			$com[$id] = $data['Company Name'];
		}
		$emp = array();
		$ret = CRM_ContactsCommon::get_contacts(array('Company Name'=>CRM_ContactsCommon::get_main_company()));
		foreach($ret as $id=>$data)
			$emp[$id] = $data['Last Name'].' '.$data['First Name'];
		$cus = array();
		$ret = CRM_ContactsCommon::get_contacts(array('!Company Name'=>CRM_ContactsCommon::get_main_company()));
		foreach($ret as $id=>$data)
			$cus[$id] = $data['Last Name'].' '.$data['First Name'];
		
		$act = array();

		$access = array(0=>'public', 1=>'public, read-only', 2=>'private');
		$priority = array(0=>'low', 1=>'medium', 2=>'high');
		
		$form->addElement('text', 'title', $this->lang->t('Title'), array('style'=>'width: 100%;'));
		$form->addRule('title', 'Field is required!', 'required');
		
		$time_format = Base_RegionalSettingsCommon::time_12h()?'h:i:a':'H:i';

		$form->addElement('datepicker', 'date_s', $this->lang->t('Event start'));
		$form->addRule('date_s', 'Field is required!', 'required');
		$form->addElement('date', 'time_s', $this->lang->t('Time'), array('format'=>$time_format));

		$form->addElement('datepicker', 'date_e', $this->lang->t('Event end'));
		$form->addRule('date_e', 'Field is required!', 'required');
		$form->addElement('date', 'time_e', $this->lang->t('Time'), array('format'=>$time_format));

		$form->addElement('checkbox', 'timeless', $this->lang->t('Timeless'), null,'onClick="time_e = getElementById(\'time_e\'); time_s = getElementById(\'time_s\'); if (this.checked) cal_style = \'none\'; else cal_style = \'block\'; time_e.style.display=cal_style; time_s.style.display=cal_style;"');
		if ($action=='view') $condition = $timeless;  
		else $condition = 'document.getElementsByName(\'timeless\')[0].checked';
		eval_js('time_e = document.getElementById(\'time_e\'); time_s = document.getElementById(\'time_s\'); if ('.$condition.') cal_style = \'none\'; else cal_style = \'block\'; time_e.style.display=cal_style; time_s.style.display=cal_style;');

		$form->registerRule('check_dates', 'callback', 'check_dates', $this);
		$form->addRule(array('date_e', 'time_e', 'date_s', 'time_s', 'timeless'), 'End date must be after begin date...', 'check_dates');
		

		$form->addElement('header', null, $this->lang->t('Event itself'));
		
		$form->addElement('select', 'rel_com_id', $this->lang->t('Company'), $com, array('style'=>'width: 100%;'));
		$form->addElement('select', 'access', $this->lang->t('Access'), $access, array('style'=>'width: 100%;'));
		$form->addElement('select', 'priority', $this->lang->t('Priority'), $priority, array('style'=>'width: 100%;'));
		
		$form->addElement('multiselect', 'emp_id', $this->lang->t('Employees'), $emp);
		$form->addRule('emp_id', $this->lang->t('At least one employee must be assigned to an event.'), 'required');
		
		$form->addElement('multiselect', 'cus_id', $this->lang->t('Customers'), $cus);
			
		if($action != 'view') {
			$rb1 = $this->init_module('Utils/RecordBrowser/RecordPicker');
			$this->display_module($rb1, array('contact', 'emp_id', array('CRM_Calendar_EventCommon','decode_contact'), array('Company Name'=>CRM_ContactsCommon::get_main_company())));
			$emp_click = $rb1->create_open_link($this->lang->t('Advanced'));
			
			$rb2 = $this->init_module('Utils/RecordBrowser/RecordPicker');
			$this->display_module($rb2, array('contact', 'cus_id', array('CRM_Calendar_EventCommon','decode_contact'), array('!Company Name'=>CRM_ContactsCommon::get_main_company())));
			$cus_click = $rb2->create_open_link($this->lang->t('Advanced'));
		} else {
			$emp_click = ''; $cus_click = '';
		}
		$form->addElement('text', 'rel_emp', $this->lang->t('Related Person'), array('style'=>'width: 100%;'));
		
		$form->addElement('textarea', 'description',  $this->lang->t('Description'), array('rows'=>6, 'style'=>'width: 100%;'));
			
		$form->addElement('static', 'created',  $this->lang->t('Created'));
		$form->addElement('static', 'edited',  $this->lang->t('Edited'));
				
		$form->setDefaults($def);
		
		if ($form->validate()) {
			$values = $form->exportValues();
			if (!isset($values['timeless'])) $values['timeless'] = false;
			if($action == 'new')
				$this->add_event($values);
			else 
				$this->update_event($id, $values);
			return false;
		}
			
		if($action == 'view') $form->freeze();
		
		$theme =  & $this->pack_module('Base/Theme');

		$theme->assign('repeatable', 0); 
		$theme->assign('repeat_forever', 0); 
		$theme->assign('edit_mode', 0); 

		$theme->assign('view_style', 'new_event');
		$theme->assign('timeless', 0);
		$theme->assign('emp_click', $emp_click);
		$theme->assign('cus_click', $cus_click);
		$theme->assign('tag', md5($this->get_path().microtime()));
		$form->assign_theme('form', $theme);
		$theme->display();
		
		if($action == 'view') {
			Base_ActionBarCommon::add('edit',$this->lang->t('Edit'), $this->create_callback_href(array($this, 'view_event'), array('edit', $id)));
		} else {
			Base_ActionBarCommon::add('save','Save',' href="javascript:void(0)" onClick="'.addcslashes($form->get_submit_form_js(true),'"').'"');
		}
		return true;
	}

	public function check_dates($arg) {
		$start = strtotime($arg[2]) + ($arg[4]==true?0:1)*$this->recalculate_time($arg[3]);
		$end = strtotime($arg[0]) + ($arg[4]==true?0:1)*$this->recalculate_time($arg[1]);
		return $end >= $start;
	}

	private function recalculate_time($time) {
		if (isset($time['a'])) {
			$result = 60*($time['i']+60*($time['h']));
			if ($time['a']=='pm') $result += 43200;
			if ($time['h']==12) {
				if ($time['a']=='pm') $result -= 43200; else $result -= 43200;
			}
		} else $result = 60*($time['i']+60*($time['H']));
		return $result;
	}

	public function add_event($vals = array()){
		$start = strtotime($vals['date_s']) + $this->recalculate_time($vals['time_s']);
		$end = strtotime($vals['date_e']) + $this->recalculate_time($vals['time_e']);
		DB::Execute('INSERT INTO crm_calendar_event (title,'.
													'description,'.
													'start,'.
													'end,'.
													'timeless,'.
													'access,'.
													'priority,'.
													'created_by,'.
													'created_on) VALUES ('.
													'%s,'.
													'%s,'.
													'%d,'.
													'%d,'.
													'%d,'.
													'%d,'.
													'%d,'.
													'%d,'.
													'%T)',array(
													$vals['title'],
													$vals['description'],
													$start,
													$end,
													($vals['timeless']?1:0),
													$vals['access'],
													$vals['priority'],
													Acl::get_user(),
													date('Y-m-d H:i:s')
													));
	}

	public function update_event($id, $vals = array()){
		$start = strtotime($vals['date_s']) + $this->recalculate_time($vals['time_s']);
		$end = strtotime($vals['date_e']) + $this->recalculate_time($vals['time_e']);
		DB::Execute('UPDATE crm_calendar_event SET title=%s,'.
													'description=%s,'.
													'start=%d,'.
													'end=%d,'.
													'timeless=%d,'.
													'access=%d,'.
													'priority=%d,'.
													'edited_by=%d,'.
													'edited_on=%T WHERE id=%d',
													array(
													$vals['title'],
													$vals['description'],
													$start,
													$end,
													($vals['timeless']?1:0),
													$vals['access'],
													$vals['priority'],
													Acl::get_user(),
													date('Y-m-d H:i:s'),
													$id
													));
	}

}

?>