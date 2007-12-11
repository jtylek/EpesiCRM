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

class Tests_Calendar_Event extends Utils_Calendar_Event {
	private $lang;

	public function view($id) {
		if($this->is_back()) $this->back_to_calendar();
		print('view...');
		$this->view_event('view', $id);
		Base_ActionBarCommon::add('back','Back',$this->create_back_href());
	}

	public function edit($id) {
		if($this->is_back()) $this->back_to_calendar();
		print('edit...');
		Base_ActionBarCommon::add('back','Back',$this->create_back_href());
	}

	public function add($def_date,$timeless=false) {
		if($this->is_back()) $this->back_to_calendar();
		
		$qf = $this->init_module('Libs/QuickForm',null,'addf');
		$qf->addElement('datepicker','start','Start date');
		$qf->addElement('datepicker','end','End date');
//		$qf->addElement('checkbox','timeless','Timeless'); //always
		$qf->addElement('text','title','Title');
		$qf->addElement('textarea','description','Description');
		$qf->setDefaults(array('start'=>$def_date,'end'=>$def_date));
		if($qf->validate()) {
			$d = $qf->exportValues();
			DB::Execute('INSERT INTO tests_calendar_event(start,duration,timeless,title,description,created_on,created_by) VALUES(%d,%d,%b,%s,%s,%T,%d)',
				array(strtotime($d['start']),strtotime($d['end'])-strtotime($d['start'])+86400,true,$d['title'],$d['description'],time(),Acl::get_user()));
			$this->back_to_calendar();
		} else {
			$qf->display();
			Base_ActionBarCommon::add('back','Cancel',$this->create_back_href());
			Base_ActionBarCommon::add('save','Save',$qf->get_submit_form_href());
		}
	}

	public function view_event($action, $id=null){
		if($this->is_back())
			return false;
			
		$subject = -1;
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
				'emp_id' => array(Base_UserCommon::get_my_user_id())
			);
		} else {
//			$event = DB::GetRow('SELECT * FROM tests_calendar_event WHERE id=%d', $id);
/*			$def = array(
				'date_s' => Base_RegionalSettingsCommon::server_date(substr($event['datetime_start'], 0, 10)),
				'date_e' => Base_RegionalSettingsCommon::server_date(substr($event['datetime_end'], 0, 10)),
				'time_s' => str_replace('-', '.', substr($event['datetime_start'], 11, 5)), 
				'time_e' => str_replace('-', '.', substr($event['datetime_end'], 11, 5)),
				'title'=>$event['title'],
				'description'=>$event['description'],
				'priority'=>$event['priority'],
				'timeless'=>$event['timeless'],
				'access'=>$event['access'],
				'act_id'=>array($event['act_id']),
				'emp_gid'=>$event['emp_gid'],
//				'created' => 'by '.Base_UserCommon::get_user_login($event['created_by']).' on '.$event['created_on'],
//				'edited' => 'by '.Base_UserCommon::get_user_login($event['edited_by']).' on '.$event['edited_on']
			);
*/			$def['emp_id'] = array();
/*			$set = DB::Execute('SELECT uid FROM tests_calendar_event WHERE gid=%d', $event['emp_gid']);
			while($row_grp = $set->FetchRow()) {
				$def['emp_id'][] = $row_grp['uid'];
			}*/
//			$timeless = $event['timeless'];
		}

		$this->lang = $this->pack_module('Base/Lang');
		$form = $this->init_module('Libs/QuickForm');

/*		if($action == 'edit' || $action == 'view')
			$form->addElement('hidden', 'id', $subject);
*/		

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
/*		$ret = DB::Execute('SELECT id, name FROM test_calendar_event_personal_activity ORDER BY name');
		while($row = $ret->FetchRow())
			$act[$row['id']] = $row['name'];*/

		$access = array(0=>'public', 1=>'public, read-only', 2=>'private');
		$priority = array(0=>'low', 1=>'medium', 2=>'high');
		
		$form->addElement('header', null, $this->lang->t('Beginning of event'));
		$form->addElement('text', 'title', $this->lang->t('Title'), array('style'=>'width: 100%;'));
		$form->addRule('title', 'Field is required!', 'required');
		
		$form->addElement('datepicker', 'date_s', $this->lang->t('Event start'));
		$form->addRule('date_s', 'Field is required!', 'required');
			//$form->registerRule('proper_date','regex','/^\d{4}\.\d{2}\.\d{2}$/'); 
			//$form->addRule('date_e', 'Invalid date format, must be yyyy.mm.dd', 'proper_date');
		$time_format = Base_RegionalSettingsCommon::time_12h()?'h:i:a':'H:i';
		$form->addElement('date', 'time_s', $this->lang->t('Time'), array('format'=>$time_format));

		$form->addElement('header', null, $this->lang->t('Ending of event'));
		$form->addElement('datepicker', 'date_e', $this->lang->t('Event end'));
		$form->addRule('date_e', 'Field is required!', 'required');
			//$form->addRule('date_e', 'Invalid date format, must be yyyy.mm.dd', 'proper_date');
			//$form->addRule(array('date_e', 'date_s'), 'End date must be after begin date...', 'compare', 'gte');
		$form->addElement('date', 'time_e', $this->lang->t('Time'), array('format'=>$time_format));
		
		$form->addElement('checkbox', 'timeless', $this->lang->t('Lasts whole day?'), null,'onClick="'.$form->get_submit_form_js(false).'"');

		$form->addElement('header', null, $this->lang->t('Event itself'));
		
		$form->addElement('select', 'rel_com_id', $this->lang->t('Company'), $com, array('style'=>'width: 100%;'));
		$form->addElement('select', 'act_id', $this->lang->t('Activity'), $act, array('style'=>'width: 100%;'));
		$form->addElement('select', 'access', $this->lang->t('Access'), $access, array('style'=>'width: 100%;'));
		$form->addElement('select', 'priority', $this->lang->t('Priority'), $priority, array('style'=>'width: 100%;'));
		
		$mls1 = $form->addElement('multiselect', 'emp_id', $this->lang->t('Employees'), $emp);
		$mls2 = $form->addElement('multiselect', 'cus_id', $this->lang->t('Customers'), $cus);
			
		if($action != 'view') {
			$rb1 = $this->init_module('Utils/RecordBrowser/RecordPicker');
			$this->display_module($rb1, array('contact', 'emp_id', array('CRM_Calendar_Event_PersonalCommon','decode_contact'), array('Company Name'=>CRM_ContactsCommon::get_main_company())));
			$emp_click = $rb1->open_link('emp_id', 'Add from table');
			
			$rb2 = $this->init_module('Utils/RecordBrowser/RecordPicker');
			$this->display_module($rb2, array('contact', 'cus_id', array('CRM_Calendar_Event_PersonalCommon','decode_contact'), array('!Company Name'=>CRM_ContactsCommon::get_main_company())));
			$cus_click = $rb2->open_link('cus_id', 'Add from table');
		} else {
			$emp_click = ''; $cus_click = '';
		}
		$form->addElement('text', 'rel_emp', $this->lang->t('Related Person'), array('style'=>'width: 100%;'));
		
		$form->addElement('textarea', 'description',  $this->lang->t('Description'), array('rows'=>6, 'style'=>'width: 100%;'));
			
		$form->addElement('static', 'created',  $this->lang->t('Created'));
		$form->addElement('static', 'edited',  $this->lang->t('Edited'));
				
		$form->setDefaults($def);
		
		if($form->validate()) {
			if($action == 'view' && ($event['created_by'] == Base_UserCommon::get_my_user_id() || $event['status'] == 0)) {
				return $this->manage_event($subject, 'edit');	
			} else {
				if($form->validate()) {
					if($action == 'new' && $form->process(array(&$this, 'add_event_submit'))) {
						return false;
					} else if($action == 'edit' && $form->process(array(&$this, 'edit_event_submit'))) {
						return false;
					}
				}
			}
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

}

?>