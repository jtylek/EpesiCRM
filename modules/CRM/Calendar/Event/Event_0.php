<?php
/**
 * Calendar event module
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-calendar-event
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_Event extends Utils_Calendar_Event {
	private $lang;

	public function view($id) {
		if($this->is_back()) $this->back_to_calendar();
		$this->view_event('view', $id);
	}

	public function edit($id) {
		if($this->is_back()) $this->back_to_calendar();
		$this->view_event('edit',$id);
	}

	public function add($def_date,$timeless=false) {
		if($this->is_back()) $this->back_to_calendar();
		$this->view_event('new', $def_date, $timeless);
	}

	public function view_event($action, $id=null, $timeless=false){
		if($this->is_back()) return false;

		$emp = array();
		$ret = CRM_ContactsCommon::get_contacts(array('company_name'=>array(CRM_ContactsCommon::get_main_company())));
		foreach($ret as $c_id=>$data)
			$emp[$c_id] = $data['last_name'].' '.$data['first_name'];
		$cus = array();
		$ret = CRM_ContactsCommon::get_contacts(array('!company_name'=>array(CRM_ContactsCommon::get_main_company()), ':Fav'=>true));
		foreach($ret as $c_id=>$data)
			$cus[$c_id] = $data['last_name'].' '.$data['first_name'];

		if($action == 'new') {
			$tt = $id-$id%300;
			$def = array(
				'date_s' => date('Y-m-d',$id),
				'date_e' => date('Y-m-d',$id+3600),
				'time_s' => date('H:i',$tt),
				'time_e' => date('H:i',$tt+3600),
				'access'=>0,
				'priority'=>0,
				'emp_id' => array(Acl::get_user()),
				'timeless'=>($timeless?1:0)
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
				'color'=>$event['color'],
				'created_by' => Base_UserCommon::get_user_login($event['created_by']),
				'created_on' => $event['created_on'],
				'edited_by' => $event['edited_by']?Base_UserCommon::get_user_login($event['edited_by']):'--',
				'edited_on' => $event['edited_by']?$event['edited_on']:'--'
			);
			$def['cus_id'] = array();
			$ret = DB::Execute('SELECT contact FROM crm_calendar_event_group_cus WHERE id=%d', $id);
			while ($row=$ret->FetchRow()) {
				$def['cus_id'][] = $row['contact'];
				if (!isset($cus[$row['contact']])) $cus[$row['contact']] = CRM_Calendar_EventCommon::decode_contact($row['contact']);
			}
			$def['emp_id'] = array();
			$ret = DB::Execute('SELECT contact FROM crm_calendar_event_group_emp WHERE id=%d', $id);
			while ($row=$ret->FetchRow())
				$def['emp_id'][] = $row['contact'];
			$timeless = $event['timeless'];
		}

		$this->lang = $this->pack_module('Base/Lang');
		$form = $this->init_module('Libs/QuickForm');
		$theme =  $this->pack_module('Base/Theme');

		$act = array();

		$form->addElement('text', 'title', $this->lang->t('Title'), array('style'=>'width: 100%;', 'id'=>'event_title'));
		$form->addRule('title', 'Field is required!', 'required');

		$time_format = Base_RegionalSettingsCommon::time_12h()?'h:i:a':'H:i';

		$form->addElement('datepicker', 'date_s', $this->lang->t('Event start'));
		$form->addRule('date_s', 'Field is required!', 'required');
		$lang_code = Base_LangCommon::get_lang_code();
		$form->addElement('date', 'time_s', $this->lang->t('Time'), array('format'=>$time_format, 'optionIncrement'  => array('i' => 5),'language'=>$lang_code));

		$dur = array(300=>$this->lang->ht('5 minutes'),
			900=>$this->lang->ht('15 minutes'),
			1800=>$this->lang->ht('30 minutes'),
			3600=>$this->lang->ht('1 hour'),
			7200=>$this->lang->ht('2 hours'),
			14400=>$this->lang->ht('4 hours'),
			'all'=>$this->lang->ht('All day'),
			'adv'=>$this->lang->ht('Advanced selection'));
		$form->addElement('select', 'duration', $this->lang->t('Duration'),$dur);
		
		$form->addElement('datepicker', 'date_e', $this->lang->t('Event end'));
		$form->addRule('date_e', 'Field is required!', 'required');
		$form->addElement('date', 'time_e', $this->lang->t('Time'), array('format'=>$time_format, 'optionIncrement'  => array('i' => 5), 'language'=>$lang_code));

		$theme->assign('time_block_id','crm_calendar_time_block');
		eval_js_once('crm_calendar_event_timeless = function(val) {'.
				'var cal_style;'.
				'if(val) cal_style = \'none\'; else cal_style = \'block\';'.
				'$(\'crm_calendar_time_block\').style.display = cal_style;'.
			'}');
		$form->addElement('checkbox', 'timeless', $this->lang->t('Timeless'), null,array('onClick'=>'crm_calendar_event_timeless(this.checked)'));
		if ($action=='view') $condition = $timeless;
		else $condition = 'document.getElementsByName(\'timeless\')[0].checked';
		eval_js('crm_calendar_event_timeless('.$timeless.')');

		$form->registerRule('check_dates', 'callback', 'check_dates', $this);
		$form->addRule(array('date_e', 'time_e', 'date_s', 'time_s', 'timeless'), 'End date must be after begin date...', 'check_dates');


		$form->addElement('header', null, $this->lang->t('Event itself'));

		$access = array(0=>$this->lang->t('Public'), 1=>$this->lang->t('Public, read-only'), 2=>$this->lang->t('Private'));
		$priority = array(0 => $this->lang->t('None'), 1 => $this->lang->t('Low'), 2 => $this->lang->t('Medium'), 3 => $this->lang->t('High'));
		$color = array(0 => '', 1 => $this->lang->t('Green'), 2 => $this->lang->t('Yellow'), 3 => $this->lang->t('Red'), 4 => $this->lang->t('Blue'), 5=> $this->lang->t('Gray'));
		$color[0] = $this->lang->t('Default').': '.$this->lang->t($color[Base_User_SettingsCommon::get('CRM_Calendar','default_color')]);

		$form->addElement('select', 'access', $this->lang->t('Access'), $access, array('style'=>'width: 100%;'));
		$form->addElement('select', 'priority', $this->lang->t('Priority'), $priority, array('style'=>'width: 100%;'));
		$form->addElement('select', 'color', $this->lang->t('Color'), $color, array('style'=>'width: 100%;'));

		$form->addElement('multiselect', 'emp_id', $this->lang->t('Employees'), $emp);
		$form->addRule('emp_id', $this->lang->t('At least one employee must be assigned to an event.'), 'required');

		$form->addElement('multiselect', 'cus_id', $this->lang->t('Customers'), $cus);

		if($action != 'view') {
			$rb2 = $this->init_module('Utils/RecordBrowser/RecordPicker');
			$this->display_module($rb2, array('contact', 'cus_id', array('CRM_Calendar_EventCommon','decode_contact'), array('!company_name'=>CRM_ContactsCommon::get_main_company()), array('work_phone'=>false, 'mobile_phone'=>false, 'zone'=>false, 'Actions'=>false)));
			$cus_click = $rb2->create_open_link($this->lang->t('Advanced'));
		} else {
			$cus_click = '';
		}
		$form->addElement('text', 'rel_emp', $this->lang->t('Related Person'), array('style'=>'width: 100%;'));

		$form->addElement('textarea', 'description',  $this->lang->t('Description'), array('rows'=>6, 'style'=>'width: 100%;'));

		if($action === 'view') {
			$form->addElement('static', 'created_by',  $this->lang->t('Created by'));
			$form->addElement('static', 'created_on',  $this->lang->t('Created on'));
			$form->addElement('static', 'edited_by',  $this->lang->t('Edited by'));
			$form->addElement('static', 'edited_on',  $this->lang->t('Edited on'));
		}

		$form->setDefaults($def);

		if ($form->validate()) {
			$values = $form->exportValues();
			print_r($values);
			if (!isset($values['timeless'])) $values['timeless'] = false;
			if($action == 'new')
				$this->add_event($values);
			else
				$this->update_event($id, $values);
			$this->back_to_calendar();
		}

		if($action == 'view') $form->freeze();

		$theme->assign('view_style', 'new_event');
		$theme->assign('cus_click', $cus_click);
		$form->assign_theme('form', $theme);
		$theme->display();

		if($action == 'view') {
			Base_ActionBarCommon::add('edit',$this->lang->t('Edit'), $this->create_callback_href(array($this, 'view_event'), array('edit', $id)));
		} else {
			Base_ActionBarCommon::add('save','Save',' href="javascript:void(0)" onClick="'.addcslashes($form->get_submit_form_js(true),'"').'"');
		}
		Base_ActionBarCommon::add('back','Back',$this->create_back_href());
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
													'color,'.
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
													'%d,'.
													'%T)',array(
													$vals['title'],
													$vals['description'],
													$start,
													$end,
													($vals['timeless']?1:0),
													$vals['access'],
													$vals['priority'],
													$vals['color'],
													Acl::get_user(),
													date('Y-m-d H:i:s')
													));
		$id = DB::Insert_ID('crm_calendar_event', 'id');
		foreach($vals['emp_id'] as $v) {
			DB::Execute('INSERT INTO crm_calendar_event_group_emp (id,contact) VALUES (%d, %d)', array($id, $v));
		}
		foreach($vals['cus_id'] as $v) {
			DB::Execute('INSERT INTO crm_calendar_event_group_cus (id,contact) VALUES (%d, %d)', array($id, $v));
		}
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
													'color=%d,'.
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
													$vals['color'],
													Acl::get_user(),
													date('Y-m-d H:i:s'),
													$id
													));
		DB::Execute('DELETE FROM crm_calendar_event_group_emp WHERE id=%d', array($id));
		DB::Execute('DELETE FROM crm_calendar_event_group_cus WHERE id=%d', array($id));
		foreach($vals['emp_id'] as $v) {
			DB::Execute('INSERT INTO crm_calendar_event_group_emp (id,contact) VALUES (%d, %d)', array($id, $v));
		}
		foreach($vals['cus_id'] as $v) {
			DB::Execute('INSERT INTO crm_calendar_event_group_cus (id,contact) VALUES (%d, %d)', array($id, $v));
		}
	}

}

?>
