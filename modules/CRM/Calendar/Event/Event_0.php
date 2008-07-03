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
	private $custom_defaults = array();
	private static $access;
	private static $priority;
	
	public function construct() {
		$this->lang = $this->pack_module('Base/Lang');
		self::$access = array(0=>$this->lang->t('Public'), 1=>$this->lang->t('Public, read-only'), 2=>$this->lang->t('Private'));
		self::$priority = array(0 => $this->lang->t('Low'), 1 => $this->lang->t('Medium'), 2 => $this->lang->t('High'));
	}

	public function view($id) {
		if($this->is_back()) $this->back_to_calendar();
		$this->view_event('view', $id);
	}

	public function edit($id) {
		if($this->is_back()) $this->back_to_calendar();
		$this->view_event('edit',$id);
	}

	public function add($def_date,$timeless=false,$def=array()) {
		if($this->is_back()) $this->back_to_calendar();
		$this->custom_defaults = $def;
		$this->view_event('new', $def_date, $timeless);
	}
	
	public function make_event_PDF($pdf, $id, $no_details = false,$type='Event'){
		$ev = DB::GetRow('SELECT * FROM crm_calendar_event WHERE id=%d', array($id));
//		Base_ThemeCommon::install_default_theme($this->get_type()); // TODO: delete this, just develop tool
		$pdf_theme = $this->pack_module('Base/Theme');
		$pdf_theme->assign('description', array('label'=>$this->lang->t('Description'), 'value'=>str_replace("\n",'<br>',htmlspecialchars($ev['description']))));
		if (!$no_details) {
			$ev['status'] = Utils_CommonDataCommon::get_value('Ticket_Status/'.$ev['status']);
			$ev['access'] = self::$access[$ev['access']];
			$ev['priority'] = self::$priority[$ev['priority']];
			foreach (array('access', 'priority', 'status') as $v)
				$pdf_theme->assign($v, array('label'=>$this->lang->t(ucfirst($v)), 'value'=>$ev[$v]));
			$created_by = CRM_ContactsCommon::get_contact_by_user_id($ev['created_by']);
			if ($created_by!==null) $created_by = $created_by['last_name'].' '.$created_by['first_name'];
			else $created_by = Base_UserCommon::get_user_login($ev['created_by']);
			$created_on = Base_RegionalSettingsCommon::time2reg($ev['created_on'],false);
			$pdf_theme->assign('created_on', array('label'=>$this->lang->t('Created on'), 'value'=>$created_on));
			$pdf_theme->assign('created_by', array('label'=>$this->lang->t('Created by'), 'value'=>$created_by));
			if ($ev['edited_by']!=null) {
				$edited_by = CRM_ContactsCommon::get_contact_by_user_id($ev['edited_by']);
				if ($edited_by!==null) $edited_by = $edited_by['last_name'].' '.$edited_by['first_name'];
				else $edited_by = Base_UserCommon::get_user_login($ev['edited_by']);
				$edited_on = Base_RegionalSettingsCommon::time2reg($ev['edited_on'],false);
			} else {
				$edited_by = '--';
				$edited_on = '--';
			}
			$pdf_theme->assign('edited_on', array('label'=>$this->lang->t('Edited on'), 'value'=>$edited_on));
			$pdf_theme->assign('edited_by', array('label'=>$this->lang->t('Edited by'), 'value'=>$edited_by));
			$pdf_theme->assign('printed_on', array(	'label'=>$this->lang->t('Printed on'),
													'value'=>Base_RegionalSettingsCommon::time2reg(time())));
		}
		$defec = $this->get_emp_and_cus($id);
		$emps = array();
		foreach ($defec['emp_id'] as $v) {
			$c = CRM_ContactsCommon::get_contact($v);
			$emps[] = array('name'=>$c['last_name'].' '.$c['first_name'],
							'mphone'=>$c['mobile_phone'],
							'wphone'=>$c['work_phone'],
							'hphone'=>$c['home_phone']);
		}
		$cuss = array();
		$cus_cmps = array();
		foreach ($defec['cus_id'] as $v) {
			$c = CRM_ContactsCommon::get_contact($v);
			$company_name = array();
			if (is_array($c['company_name']))
				foreach ($c['company_name'] as $vv)
					$company_name[] = Utils_RecordBrowserCommon::get_value('company', $vv, 'Company Name');
			$cuss[] = array('name'=>$c['last_name'].' '.$c['first_name'],
							'mphone'=>$c['mobile_phone'],
							'wphone'=>$c['work_phone'],
							'hphone'=>$c['home_phone'],
							'company_name'=>$company_name);
			if (is_array($c['company_name'])) 
				foreach ($c['company_name'] as $v2)
					if (!isset($cus_cmps[$v2]))
						$cus_cmps[$v2] = CRM_ContactsCommon::get_company($v2);
		}
		$pdf_theme->assign('employees', array(	'main_label'=>$this->lang->t('Employees'),
												'name_label'=>$this->lang->t('Name'),
												'mphone_label'=>$this->lang->t('Mobile Phone'),
												'wphone_label'=>$this->lang->t('Work Phone'),
												'hphone_label'=>$this->lang->t('Home Phone'),
												'lp_label'=>$this->lang->t('Lp'),
												'data'=>$emps
												));
		$pdf_theme->assign('customers', array(	'main_label'=>$this->lang->t('Customers'),
												'name_label'=>$this->lang->t('Name'),
												'mphone_label'=>$this->lang->t('Mobile Phone'),
												'wphone_label'=>$this->lang->t('Work Phone'),
												'hphone_label'=>$this->lang->t('Home Phone'),
												'company_name'=>$this->lang->t('Company Name'),
												'lp_label'=>$this->lang->t('Lp'),
												'data'=>$cuss
												));
		$pdf_theme->assign('customers_companies', array(	'main_label'=>$this->lang->t('Customers Companies'),
															'name_label'=>$this->lang->t('Company Name'),
															'phone_label'=>$this->lang->t('Phone'),
															'fax_label'=>$this->lang->t('Fax'),
															'address_label'=>$this->lang->t('Address'),
															'city_label'=>$this->lang->t('City'),
															'lp_label'=>$this->lang->t('Lp'),
															'data'=>$cus_cmps
															));
		$pdf_theme->assign('title', array(	'label'=>$this->lang->t('Title'),
											'value'=>$ev['title']));
		$start = Base_RegionalSettingsCommon::time2reg($ev['start'],false);
		$pdf_theme->assign('start_date', array(	'label'=>$this->lang->t('Start date'),
												'value'=>$start,
												'details'=>array('weekday'=>date('l', strtotime($start)))));
		if (!$ev['timeless']) {
			$pdf_theme->assign('start_time', array(	'label'=>$this->lang->t('Start time'),
													'value'=>Base_RegionalSettingsCommon::time2reg($ev['start'],true,false)));
			$pdf_theme->assign('end_time', array(	'label'=>$this->lang->t('End time'),
													'value'=>Base_RegionalSettingsCommon::time2reg($ev['end'],true,false)));
			$duration = array(floor(($ev['end']-$ev['start'])/3600));
			$format = '%d hours';
			$minutes = ($ev['end']-$ev['start'])%3600;
			if ($minutes!=0) {
				if ($duration[0]==0) {
					$duration = array();
					$format = '';
				} else $format.= ', ';
				$duration[] = $minutes/60; 
				$format .= '%d minutes';
			}
			$pdf_theme->assign('duration', array(	'label'=>$this->lang->t('Duration'),
													'value'=>$this->lang->t($format,$duration)));
			if (date('Y-m-d',$ev['start'])!=date('Y-m-d',$ev['end']))
				$pdf_theme->assign('end_date', array(	'label'=>$this->lang->t('End date'),
														'value'=>Base_RegionalSettingsCommon::time2reg($ev['end'],false)));
		} else $pdf_theme->assign('timeless', array(	'label'=>$this->lang->t('Timeless'),
														'value'=>$this->lang->t('Yes')));
		$pdf_theme->assign('type',$type);
		ob_start();
		$pdf_theme->display('pdf_version');
		$cont = ob_get_clean();
		$pdf->writeHTML($cont);
	}

	public function get_emp_and_cus($id){
		$def = array();
		$def['cus_id'] = array();
		$ret = DB::Execute('SELECT contact FROM crm_calendar_event_group_cus WHERE id=%d', $id);
		while ($row=$ret->FetchRow())
			$def['cus_id'][] = $row['contact'];
		$def['emp_id'] = array();
		$ret = DB::Execute('SELECT contact FROM crm_calendar_event_group_emp WHERE id=%d', $id);
		while ($row=$ret->FetchRow())
			$def['emp_id'][] = $row['contact'];
		return $def;
	}

	public function view_event($action, $id=null, $timeless=false){
		if($this->is_back()) return false;
		
		$form = $this->init_module('Libs/QuickForm');
		$theme =  $this->pack_module('Base/Theme');
		$theme->assign('action',$action);

		$def = array();

		if($action == 'new') {
			$duration_switch = '1';
			$id = strtotime(Base_RegionalSettingsCommon::time2reg($id,true,true,true,false));
			$tt = $id-$id%300;
			$me = CRM_ContactsCommon::get_contacts(array('login'=>Acl::get_user()),array('id'));
			$my_emp = array();
			foreach($me as $v)
				$my_emp[] = $v['id'];
			$def = array(
				'date_s' => $id,
//				'date_e' => $id+3600,
				'time_s' => $tt,
				'time_e' => $tt+3600,
				'duration'=>3600,
				'access'=>0,
				'priority'=>0,
				'emp_id' => $my_emp,
				'timeless'=>($timeless?1:0),
				'cus_id'=>array()
			);
			foreach($this->custom_defaults as $k=>$v) $def[$k] = $v;
		} else {
			$event = DB::GetRow('SELECT *,end-start as duration FROM crm_calendar_event WHERE id=%d', $id);
			if ($event['priority']==2) $event['priority']=1;
			$x = $event['end']-$event['start'];
			if(in_array($x,array(300,900,1800,3600,7200,14400,28800)))
				$duration_switch='1';
			else {
				$duration_switch='0';
				$x = '-1';
			}
			$evx = Utils_CalendarCommon::process_event($event);
			$event['start'] = strtotime(Base_RegionalSettingsCommon::time2reg($event['start'],true,true,true,false));
			$event['end'] = strtotime(Base_RegionalSettingsCommon::time2reg($event['end'],true,true,true,false));
			$theme->assign('event_info',$evx);
			$theme->assign('day_details',array('start'=>	array(	'day'=>date('j',$event['start']),
																	'weekday'=>date('l',$event['start']),
																	'month'=>date('F',$event['start']),
																	'year'=>date('Y',$event['start']),
																	),
												'end'=>		array(	'day'=>date('j',$event['end']),
																	'weekday'=>date('l',$event['end']),
																	'month'=>date('F',$event['end']),
																	'year'=>date('Y',$event['end']),
																	)));
			$def = array(
				'date_s' => $event['start'],
//				'date_e' => $event['end'],
				'time_s' => $event['start'],
				'time_e' => $event['end'],
				'status' => $event['status'],
				'duration' => $x,
				'title'=>$event['title'],
				'description'=>$event['description'],
				'priority'=>$event['priority'],
				'timeless'=>$event['timeless'],
				'access'=>$event['access'],
				'color'=>$event['color'],
				'created_by' => Base_UserCommon::get_user_login($event['created_by']),
				'created_on' => $event['created_on'],
				'edited_by' => $event['edited_by']?Base_UserCommon::get_user_login($event['edited_by']):'---',
				'edited_on' => $event['edited_by']?$event['edited_on']:'---'
			);
			$defec = $this->get_emp_and_cus($id);
			$def['cus_id'] = $defec['cus_id'];
			$def['emp_id'] = $defec['emp_id'];
/*			$def['cus_id'] = array();
			$ret = DB::Execute('SELECT contact FROM crm_calendar_event_group_cus WHERE id=%d', $id);
			while ($row=$ret->FetchRow())
				$def['cus_id'][] = $row['contact'];
			$def['emp_id'] = array();
			$ret = DB::Execute('SELECT contact FROM crm_calendar_event_group_emp WHERE id=%d', $id);
			while ($row=$ret->FetchRow())
				$def['emp_id'][] = $row['contact'];*/
			$def_emp_id = $def['emp_id'];
			$timeless = $event['timeless'];
			$tmp = $def['title'];
			$def['title'] = Base_LangCommon::ts('CRM/Calendar/Event','Follow up: ').$def['title'];
			$theme->assign('new_event','<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM/Calendar/Event','New Event')).' '.CRM_CalendarCommon::create_new_event_href($def).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Calendar','icon-small.png').'"></a>');
			if (ModuleManager::is_installed('CRM/Tasks')>=0) $theme->assign('new_task','<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM/Calendar/Event','New Task')).' '.Utils_RecordBrowserCommon::create_new_record_href('task', array('page_id'=>md5('crm_tasks'),'title'=>$def['title'],'permission'=>$def['access'],'priority'=>$def['priority'],'description'=>$def['description'],'deadline'=>date('Y-m-d H:i:s', strtotime('+1 day')),'employees'=>$def['emp_id'], 'customers'=>$def['cus_id'])).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Tasks','icon-small.png').'"></a>');
			if (ModuleManager::is_installed('CRM/PhoneCall')>=0) $theme->assign('new_phonecall','<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM/Calendar/Event','New Phonecall')).' '.Utils_RecordBrowserCommon::create_new_record_href('phonecall', array('subject'=>$def['title'],'permission'=>$def['access'],'priority'=>$def['priority'],'description'=>$def['description'],'date_and_time'=>date('Y-m-d H:i:s'),'employees'=>$def['emp_id'], 'contact'=>isset($def['cus_id'])?$def['cus_id']:'')).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_PhoneCall','icon-small.png').'"></a>');
			$def['title'] = $tmp;
		}


		$act = array();

		$form->addElement('text', 'title', $this->lang->t('Title'), array('style'=>'width: 100%;', 'id'=>'event_title'));
		$form->addRule('title', 'Field is required!', 'required');

		if ($action=='view') {
			$form->addElement('static', 'status', $this->lang->t('Status'));
			$status = Utils_CommonDataCommon::get_array('Ticket_Status');
			$prefix = 'crm_event_leightbox';

			$lgb = CRM_Calendar_EventCommon::get_followup_leightbox_href($id, $def);
			if ($def['status']>=2) {
				$def['status'] = $status[$def['status']];
			} elseif ($def['status']==0) {
				$def['status'] = '<a href="javascript:void(0)" onclick="'.$prefix.'_set_action(\'set_in_progress\');'.$prefix.'_set_id(\''.$id.'\');'.$prefix.'_submit_form();">'.$status[$def['status']].'</a>';
			} else {
				$def['status'] = '<a '.$lgb.'>'.$status[$def['status']].'</a>';
			}
		} else {
			$form->addElement('commondata', 'status', $this->lang->t('Status'),'Ticket_Status',array('order_by_key'=>true));
		}

		$time_format = Base_RegionalSettingsCommon::time_12h()?'h:i a':'H:i';

		$form->addElement('datepicker', 'date_s', $this->lang->t('Event start'));
		$form->addRule('date_s', 'Field is required!', 'required');
		$lang_code = Base_LangCommon::get_lang_code();
		$form->addElement('date', 'time_s', $this->lang->t('Time'), array('format'=>$time_format, 'optionIncrement'  => array('i' => 5),'language'=>$lang_code));

		$dur = array(
			-1=>$this->lang->ht('---'),
			300=>$this->lang->ht('5 minutes'),
			900=>$this->lang->ht('15 minutes'),
			1800=>$this->lang->ht('30 minutes'),
			3600=>$this->lang->ht('1 hour'),
			7200=>$this->lang->ht('2 hours'),
			14400=>$this->lang->ht('4 hours'),
			28800=>$this->lang->ht('8 hours'));
		eval_js_once('crm_calendar_duration_switcher = function(x) {'.
			'var sw = $(\'duration_switch\');'.
			'if((!x && sw.value==\'0\') || (x && sw.value==\'1\')) {'.
			'var end_b=$(\'crm_calendar_event_end_block\');if(end_b)end_b.hide();'.
			'var dur_b=$(\'crm_calendar_duration_block\');if(dur_b)dur_b.show();'.
			'sw.value=\'1\';'.
			'} else {'.
			'var end_b=$(\'crm_calendar_event_end_block\');if(end_b)end_b.show();'.
			'var dur_b=$(\'crm_calendar_duration_block\');if(dur_b)dur_b.hide();'.
			'sw.value=\'0\';'.
			'}}');
		$theme->assign('toggle_duration','<a class="button" href="javascript:void(0)" onClick="crm_calendar_duration_switcher()" id="toggle_duration_button">'.$this->lang->t('Toggle').'</a>');
		$theme->assign('duration_block_id','crm_calendar_duration_block');
		$theme->assign('event_end_block_id','crm_calendar_event_end_block');
		$form->addElement('hidden','duration_switch',$duration_switch,array('id'=>'duration_switch'));
		eval_js('crm_calendar_duration_switcher(1)');
		$form->addElement('select', 'duration', $this->lang->t('Duration'),$dur);
		$form->addRule('duration',$this->lang->t('Duration not selected'),'neq','-1');

		//$form->addElement('datepicker', 'date_e', $this->lang->t('Event end'));
		//$form->addRule('date_e', 'Field is required!', 'required');
		$form->addElement('date', 'time_e', $this->lang->t('Event end'), array('format'=>$time_format, 'optionIncrement'  => array('i' => 5), 'language'=>$lang_code));
		$form->addRule('time_e', 'Field is required!', 'required');

		eval_js_once('crm_calendar_event_timeless = function(val) {'.
				'var cal_style;'.
				'var tdb=$(\'toggle_duration_button\');'.
				'if(tdb==null) return;'.
				'if(val){'.
				'cal_style = \'none\';'.
				'$(\'duration_switch\').value=\'0\';'.
				'}else{'.
				'cal_style = \'block\';'.
				'$(\'duration_switch\').value=\'1\';'.
				'}'.
				'var te = $(\'time_e\');'.
				'if(te) te.style.display = cal_style;'.
				'var ts = $(\'time_s\');'.
				'if(ts) ts.style.display = cal_style;'.
				'tdb.style.display = cal_style;'.
				'crm_calendar_duration_switcher(1);'.
			'}');
		$form->addElement('checkbox', 'timeless', $this->lang->t('Timeless'), null,array('onClick'=>'crm_calendar_event_timeless(this.checked)','id'=>'timeless'));
		if ($action=='view') $condition = $timeless;
		else $condition = 'document.getElementsByName(\'timeless\')[0].checked';
		eval_js('crm_calendar_event_timeless('.$timeless.')');

		$form->registerRule('check_dates', 'callback', 'check_dates', $this);
		$form->addRule(array('date_s','time_e', 'date_s', 'time_s', 'timeless','duration_switch'), 'End date must be after begin date...', 'check_dates');


		$form->addElement('header', null, $this->lang->t('Event itself'));

		$color = CRM_Calendar_EventCommon::get_available_colors();
		$color[0] = $this->lang->t('Default').': '.$this->lang->ht(ucfirst($color[0]));
		for($k=1; $k<count($color); $k++)
			$color[$k] = '&bull; '.$this->lang->ht(ucfirst($color[$k]));

		$form->addElement('select', 'access', $this->lang->t('Access'), self::$access, array('style'=>'width: 100%;'));
		$form->addElement('select', 'priority', $this->lang->t('Priority'), self::$priority, array('style'=>'width: 100%;'));
		$form->addElement('select', 'color', $this->lang->t('Color'), $color, array('style'=>'width: 100%;'));

		if ($action=='view') {
			$form->addElement('static', 'emp_id', $this->lang->t('Employees'));
			$form->addElement('static', 'cus_id', $this->lang->t('Customers'));
			$cus_id = '';
			$emp_id = '';
			foreach ($def['cus_id'] as $v)
				$cus_id .= CRM_ContactsCommon::contact_format_default(CRM_ContactsCommon::get_contact($v)).'<br>';
			foreach ($def['emp_id'] as $v)
				$emp_id .= CRM_ContactsCommon::contact_format_no_company(CRM_ContactsCommon::get_contact($v)).'<br>';
			$def['cus_id'] = $cus_id;
			$def['emp_id'] = $emp_id;
		} else {
			$emp = array();
			$emp_alarm = array();
			$ret = CRM_ContactsCommon::get_contacts(array('company_name'=>array(CRM_ContactsCommon::get_main_company())), array(), array('last_name'=>'ASC', 'first_name'=>'ASC'));
			foreach($ret as $c_id=>$data) {
				$emp[$c_id] = $data['last_name'].' '.$data['first_name'];
				if(is_numeric($data['login']))
					$emp_alarm[$c_id] = $data['login'];
			}
			$cus = array();
			$ret = CRM_ContactsCommon::get_contacts(array('(:Fav'=>true, '|:Recent'=>true, '|id'=>$def['cus_id']), array(), array('last_name'=>'ASC', 'first_name'=>'ASC'));
			foreach($ret as $c_id=>$data)
				$cus[$c_id] = CRM_ContactsCommon::contact_format_default($data);

			$form->addElement('multiselect', 'emp_id', $this->lang->t('Employees'), $emp);
			$form->addRule('emp_id', $this->lang->t('At least one employee must be assigned to an event.'), 'required');

			$form->addElement('multiselect', 'cus_id', $this->lang->t('Customers'), $cus);
		}



		if($action != 'view') {
			$rb2 = $this->init_module('Utils/RecordBrowser/RecordPicker');
			$this->display_module($rb2, array('contact', 'cus_id', array('CRM_ContactsCommon','contact_format_no_company'), array(), array('work_phone'=>false, 'mobile_phone'=>false, 'zone'=>false, 'Actions'=>false), array('last_name'=>'ASC')));
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
			$theme->assign('info_tooltip', '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('Utils_RecordBrowser','Created on:').' '.$def['created_on']. '<br>'.
					Base_LangCommon::ts('Utils_RecordBrowser','Created by:').' '.$def['created_by']. '<br>'.
					Base_LangCommon::ts('Utils_RecordBrowser','Edited on:').' '.$def['edited_on']. '<br>'.
					Base_LangCommon::ts('Utils_RecordBrowser','Edited by:').' '.$def['edited_by']).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','info.png').'" /></a>');
		}

		$form->setDefaults($def);

		$theme->assign('access_id',$form->exportValue('access'));
		$theme->assign('priority_id',$form->exportValue('priority'));
		$theme->assign('color_id',$form->exportValue('color'));
		$theme->assign('status_id',isset($event['status'])?$event['status']:0);

		if ($form->validate()) {
			$values = $form->exportValues();
			//print_r($values);
			if (!isset($values['timeless'])) $values['timeless'] = false;
			if($action == 'new' || $action=='clone')
				CRM_CalendarCommon::$last_added = $this->add_event($values);
			else
				$this->update_event($id, $values);
			$this->back_to_calendar();
			return;
		}

		if($action == 'view') {
			$form->freeze();

			$tb = $this->init_module('Utils/TabbedBrowser');
			$tb->start_tab('Notes');
			//attachments
			$a = $this->init_module('Utils/Attachment',array($id,'CRM/Calendar/Event/'.$id));
			$a->set_inline_display();
			$a->additional_header('Event: '.$event['title']);
			$a->allow_protected($this->acl_check('view protected notes'),$this->acl_check('edit protected notes'));
			$a->allow_public($this->acl_check('view public notes'),$this->acl_check('edit public notes'));
			$this->display_module($a);
			$tb->end_tab();

			$tb->start_tab('Alerts');
			$mes_users = array();
			foreach ($def_emp_id as $r)
				if(isset($emp_alarm[$r]))
					$mes_users[$emp_alarm[$r]] = $emp[$r];
			$mes = $this->init_module('Utils/Messenger',array('CRM_Calendar_Event:'.$id,array('CRM_Calendar_EventCommon','get_alarm'),array($id),$event['start'],$mes_users));
			$mes->set_inline_display();
			$this->display_module($mes);
			$tb->end_tab();
			$tb->tag();
			$theme->assign('tabs', $this->get_html_of_module($tb));
		}

//		$theme->assign('view_style', 'new_event');
		$theme->assign('cus_click', $cus_click);
		$form->assign_theme('form', $theme);

		$theme->display();

		if ($action=='view') {
			$pdf = $this->pack_module('Libs/TCPDF', 'P');
			$filename = '';
			if ($pdf->prepare()) {
				$ev = DB::GetRow('SELECT * FROM crm_calendar_event WHERE id=%d', array($id));
				$pdf->set_title($this->lang->t('Event').': '.$ev['title']);
				if (!$ev['timeless']) $pdf->set_subject(Base_RegionalSettingsCommon::time2reg($ev['start']).' - '.Base_RegionalSettingsCommon::time2reg($ev['end']));
				else $pdf->set_subject(Base_RegionalSettingsCommon::time2reg($ev['start'],false));
				$pdf->prepare_header();
				
				$pdf->AddPage();
				$this->make_event_PDF($pdf,$id);
				$filename = $this->lang->t('Event_%s', array($ev['title']));
			}
			$pdf->add_actionbar_icon($filename);
		}

		if($action == 'view') {
			$my_id = CRM_FiltersCommon::get_my_profile();
			if($def['access']==0 || in_array($my_id,$def_emp_id) || Base_AclCommon::i_am_admin()) {
				Base_ActionBarCommon::add('edit','Edit', $this->create_callback_href(array($this, 'view_event'), array('edit', $id)));
				Base_ActionBarCommon::add('clone','Clone', $this->create_confirm_callback_href($this->lang->ht('You are about to create a copy of this record. Do you want to continue?'),array($this,'clone_event'),array($id)));
			}
		} else {
			Base_ActionBarCommon::add('save','Save',' href="javascript:void(0)" onClick="'.addcslashes($form->get_submit_form_js(true),'"').'"');
		}
		Base_ActionBarCommon::add('back','Back',$this->create_back_href());
		return true;
	}

	public function check_dates($arg) {
		if($arg[5]) return true;
		$start = strtotime($arg[2]) + ($arg[4]==true?0:1)*$this->recalculate_time($arg[3]);
		$end = strtotime($arg[0]) + ($arg[4]==true?0:1)*$this->recalculate_time($arg[1]);
		return $end >= $start;
	}

	private function recalculate_time($time) {
		if (isset($time['a'])) {
			$result = 60*($time['i']+60*($time['h']%12));
			if ($time['a']=='pm') $result += 43200;
		} else $result = 60*($time['i']+60*($time['H']));
		return $result;
	}

	public function add_event($vals = array()){
		$start = strtotime($vals['date_s']) + $this->recalculate_time($vals['time_s']);
		if($vals['duration_switch']) {
			$end = $start + $vals['duration'];
			if(date('Y-m-d',$start)!=date('Y-m-d',$end))
				$end = strtotime(date('Y-m-d',$start).' 23:59');
		} else
			$end = strtotime($vals['date_s']) + $this->recalculate_time($vals['time_e']);
		$start = Base_RegionalSettingsCommon::reg2time(date('Y-m-d H:i:s',$start),true);
		$end = Base_RegionalSettingsCommon::reg2time(date('Y-m-d H:i:s',$end),true);
		DB::Execute('INSERT INTO crm_calendar_event (title,'.
													'description,'.
													'start,'.
													'end,'.
													'timeless,'.
													'access,'.
													'priority,'.
													'color,'.
													'status,'.
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
													$vals['status'],
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
		return $id;
	}

	public function update_event($id, $vals = array()){
		$start = strtotime($vals['date_s']) + $this->recalculate_time($vals['time_s']);
		if($vals['duration_switch']) {
			$end = $start + $vals['duration'];
			if(date('Y-m-d',$start)!=date('Y-m-d',$end))
				$end = strtotime(date('Y-m-d',$start).' 23:59');
		} else
			$end = strtotime($vals['date_s']) + $this->recalculate_time($vals['time_e']);
		$start = Base_RegionalSettingsCommon::reg2time(date('Y-m-d H:i:s',$start),true);
		$end = Base_RegionalSettingsCommon::reg2time(date('Y-m-d H:i:s',$end),true);
		DB::Execute('UPDATE crm_calendar_event SET title=%s,'.
													'description=%s,'.
													'start=%d,'.
													'end=%d,'.
													'timeless=%d,'.
													'access=%d,'.
													'priority=%d,'.
													'color=%d,'.
													'status=%d,'.
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
													$vals['status'],
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

	public function clone_event($id) {
		if(!$this->view_event('clone',$id))
			$this->back_to_calendar();
		return true;
	}

}

?>
