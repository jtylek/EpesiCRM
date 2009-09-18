<?php
/**
 * Calendar event module
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage calendar-event
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_Event extends Utils_Calendar_Event {
	private $custom_defaults = array();
	private static $access;
	private static $priority;

	public function construct() {
		self::$access = Utils_CommonDataCommon::get_translated_array('CRM/Access');
		self::$priority = Utils_CommonDataCommon::get_translated_array('CRM/Priority');
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
		$custom_event = false;
		if (!is_array($id)) {
			$check = explode('#', $id);
			if (isset($check[1])) {
				$callback = DB::GetOne('SELECT get_callback FROM crm_calendar_custom_events_handlers WHERE id=%d', $check[0]);
				$ev = call_user_func($callback, $check[1]);
				$no_details = true;
				$custom_event = true;
			} else {
				$ev = DB::GetRow('SELECT *, starts AS start, ends AS end FROM crm_calendar_event WHERE id=%d', array($id));
				$id = explode('_',$id);
				$id = $id[0];
			}
		} else {
			$ev = $id;
			$id = $ev['id'];
			$id = explode('_',$id);
			$id = $id[0];
/*			$ev_details = DB::GetRow('SELECT *, starts AS start, ends AS end FROM crm_calendar_event WHERE id=%d', array($id));
			foreach ($ev_details as $k=>$v)
				if (!isset($ev[$k])) $ev[$k] = $v;*/
			$ev['title'] = strip_tags($ev['title']);
			$check = explode('#', $id);
			if (isset($check[1])) {
				$no_details = true;
				$custom_event = true;
			}
		}
		$pdf_theme = $this->pack_module('Base/Theme');
		$pdf_theme->assign('description', array('label'=>$this->t('Description'), 'value'=>str_replace("\n",'<br/>',htmlspecialchars($ev['description']))));
		if (!$no_details) {
			$ev['status'] = Utils_CommonDataCommon::get_value('CRM/Status/'.$ev['status'],true);
			$ev['access'] = self::$access[$ev['access']];
			$ev['priority'] = self::$priority[$ev['priority']];
			foreach (array('access', 'priority', 'status') as $v)
				$pdf_theme->assign($v, array('label'=>$this->t(ucfirst($v)), 'value'=>$ev[$v]));
			$created_by = CRM_ContactsCommon::get_contact_by_user_id($ev['created_by']);
			if ($created_by!==null) $created_by = $created_by['last_name'].' '.$created_by['first_name'];
			else $created_by = Base_UserCommon::get_user_login($ev['created_by']);
			$created_on = Base_RegionalSettingsCommon::time2reg($ev['created_on'],false);
			$pdf_theme->assign('created_on', array('label'=>$this->t('Created on'), 'value'=>$created_on));
			$pdf_theme->assign('created_by', array('label'=>$this->t('Created by'), 'value'=>$created_by));
			if ($ev['edited_by']!=null) {
				$edited_by = CRM_ContactsCommon::get_contact_by_user_id($ev['edited_by']);
				if ($edited_by!==null) $edited_by = $edited_by['last_name'].' '.$edited_by['first_name'];
				else $edited_by = Base_UserCommon::get_user_login($ev['edited_by']);
				$edited_on = Base_RegionalSettingsCommon::time2reg($ev['edited_on'],false);
			} else {
				$edited_by = '--';
				$edited_on = '--';
			}
			$pdf_theme->assign('edited_on', array('label'=>$this->t('Edited on'), 'value'=>$edited_on));
			$pdf_theme->assign('edited_by', array('label'=>$this->t('Edited by'), 'value'=>$edited_by));
			$pdf_theme->assign('printed_on', array(	'label'=>$this->t('Printed on'),
													'value'=>Base_RegionalSettingsCommon::time2reg(time())));
		}
		if (!$custom_event) {
			$defec = CRM_Calendar_EventCommon::get_emp_and_cus($id);
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
		} else {
			$emps = array();
			$cuss = array();
			$cus_cmps = '';
		}
		$pdf_theme->assign('employees', array(	'main_label'=>$this->t('Employees'),
												'name_label'=>$this->t('Name'),
												'mphone_label'=>$this->t('Mobile Phone'),
												'wphone_label'=>$this->t('Work Phone'),
												'hphone_label'=>$this->t('Home Phone'),
												'lp_label'=>$this->t('Lp'),
												'data'=>$emps
												));
		$pdf_theme->assign('customers', array(	'main_label'=>$this->t('Customers'),
												'name_label'=>$this->t('Name'),
												'mphone_label'=>$this->t('Mobile Phone'),
												'wphone_label'=>$this->t('Work Phone'),
												'hphone_label'=>$this->t('Home Phone'),
												'company_name'=>$this->t('Company Name'),
												'lp_label'=>$this->t('Lp'),
												'data'=>$cuss
												));
		$pdf_theme->assign('customers_companies', array(	'main_label'=>$this->t('Customers Companies'),
															'name_label'=>$this->t('Company Name'),
															'phone_label'=>$this->t('Phone'),
															'fax_label'=>$this->t('Fax'),
															'address_label'=>$this->t('Address'),
															'city_label'=>$this->t('City'),
															'lp_label'=>$this->t('Lp'),
															'data'=>$cus_cmps
															));
		$pdf_theme->assign('title', array(	'label'=>$this->t('Title'),
											'value'=>$ev['title']));
		$start = Base_RegionalSettingsCommon::time2reg($ev['start'],false);
		$pdf_theme->assign('start_date', array(	'label'=>$this->t('Start date'),
												'value'=>$start,
												'details'=>array('weekday'=>date('l', strtotime($start)))));
		if (!isset($ev['timeless'])) {
			$pdf_theme->assign('start_time', array(	'label'=>$this->t('Start time'),
													'value'=>Base_RegionalSettingsCommon::time2reg($ev['start'],true,false)));
			if (!isset($ev['end'])) trigger_error(print_r($ev,true));
			$pdf_theme->assign('end_time', array(	'label'=>$this->t('End time'),
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
			$pdf_theme->assign('duration', array(	'label'=>$this->t('Duration'),
													'value'=>$this->t($format,$duration)));
			if (date('Y-m-d',$ev['start'])!=date('Y-m-d',$ev['end']))
				$pdf_theme->assign('end_date', array(	'label'=>$this->t('End date'),
														'value'=>Base_RegionalSettingsCommon::time2reg($ev['end'],false)));
		} else $pdf_theme->assign('timeless', array(	'label'=>$this->t('Timeless'),
														'value'=>$this->t('Yes')));
		$pdf_theme->assign('type',$type);
		ob_start();
		$pdf_theme->display('pdf_version');
		$cont = ob_get_clean();
		$pdf->writeHTML($cont);
	}

	public function view_event($action, $id=null, $timeless=false){
		$this->help('Calendar Help','main');
		if($this->is_back()) return false;

		$recurrence = strpos($id,'_');
		if($recurrence!==false) {
			$recurrence_id = substr($id,$recurrence+1);
			$id = substr($id,0,$recurrence);
		}

		$form = $this->init_module('Libs/QuickForm');
		$theme =  $this->pack_module('Base/Theme');
		$theme->assign('action',$action);
		

		$def = array();

		$my_id = CRM_FiltersCommon::get_my_profile();
		if($action == 'new') {
			$duration_switch = '1';
			if(!$timeless)
				$id2 = strtotime(Base_RegionalSettingsCommon::time2reg($id,true,true,true,false));
			else
				$id2 = $id;
			$tt = $id2-$id2%300;
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
				'cus_id'=>array(),
				'recurrence'=>false
			);
			foreach($this->custom_defaults as $k=>$v) $def[$k] = $v;
		} else {
			Utils_WatchdogCommon::notified('crm_calendar',$id);
			$event = DB::GetRow('SELECT *,starts as start,ends as end,ends-starts as duration FROM crm_calendar_event WHERE id=%d', $id);
			if ($event['priority']==2) $event['priority']=1;
			if($recurrence) {
				$event['start'] = CRM_Calendar_EventCommon::get_n_recurrence_time($event['start'],$event,$recurrence_id);
				$event['end'] = CRM_Calendar_EventCommon::get_n_recurrence_time($event['end'],$event,$recurrence_id);
			}
			$x = $event['duration'];
			if(in_array($x,array(300,900,1800,2700,3600,7200,14400,28800)))
				$duration_switch='1';
			else {
				$duration_switch='0';
				$x = '-1';
			}
			$evx = Utils_CalendarCommon::process_event($event);
			if(!$event['timeless']) {
//				$event['start'] = strtotime(Base_RegionalSettingsCommon::time2reg($event['start'],true,true,true,false));
				$event['end'] = strtotime(Base_RegionalSettingsCommon::time2reg($event['end'],true,true,true,false));
			}
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
				'time_s' => strtotime(Base_RegionalSettingsCommon::time2reg($event['start'],true,true,true,false)),
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
				'edited_on' => $event['edited_by']?$event['edited_on']:'---',
				'recurrence' => $event['recurrence_type']!=null,
				'recurrence_no_end_date' => $event['recurrence_end']==null
			);
			if($def['recurrence']) {
				$def['recurrence_interval'] = CRM_Calendar_EventCommon::recurrence_type($event['recurrence_type']);
				if(!$def['recurrence_no_end_date'])
					$def['recurrence_end_date'] = $event['recurrence_end'];
				if($def['recurrence_interval'] == 'week_custom')
					for($i=0; $i<7; $i++)
						$def['custom_days'][$i]=$event['recurrence_hash']{$i};
			}
			$defec = CRM_Calendar_EventCommon::get_emp_and_cus($id);
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
			if ($def['access']==2 && !in_array($my_id,$def_emp_id) && !Base_AclCommon::i_am_admin()) {
				print($this->t('You are not authorised to view this record.'));
				Base_ActionBarCommon::add('back','Back',$this->create_back_href());
				Utils_ShortcutCommon::add(array('Esc'), 'function(){'.$this->create_back_href_js().'}');
				return;
			}
			$timeless = $event['timeless'];
			$tmp = $def['title'];
			$def['title'] = Base_LangCommon::ts('CRM/Calendar/Event','Follow up: ').$def['title'];
			$theme->assign('new_event','<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM/Calendar/Event','New Event')).' '.CRM_CalendarCommon::create_new_event_href($def).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Calendar','icon-small.png').'"></a>');
			if (ModuleManager::is_installed('CRM/Tasks')>=0) $theme->assign('new_task','<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM/Calendar/Event','New Task')).' '.Utils_RecordBrowserCommon::create_new_record_href('task', array('title'=>$def['title'],'permission'=>$def['access'],'priority'=>$def['priority'],'description'=>$def['description'],'deadline'=>date('Y-m-d H:i:s', strtotime('+1 day')),'employees'=>$def['emp_id'], 'customers'=>$def['cus_id'])).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Tasks','icon-small.png').'"></a>');
			if (ModuleManager::is_installed('CRM/PhoneCall')>=0) $theme->assign('new_phonecall','<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM/Calendar/Event','New Phonecall')).' '.Utils_RecordBrowserCommon::create_new_record_href('phonecall', array('subject'=>$def['title'],'permission'=>$def['access'],'priority'=>$def['priority'],'description'=>$def['description'],'date_and_time'=>date('Y-m-d H:i:s'),'employees'=>$def['emp_id'], 'contact'=>isset($def['cus_id'])?$def['cus_id']:'')).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_PhoneCall','icon-small.png').'"></a>');
			$def['title'] = $tmp;
		}


		$act = array();

		$form->addElement('text', 'title', $this->t('Title'), array('style'=>'width: 100%;', 'id'=>'event_title'));
		$form->addRule('title', 'Field is required!', 'required');

		if ($action=='view') {
			$form->addElement('static', 'status', $this->t('Status'));
			$status = Utils_CommonDataCommon::get_translated_array('CRM/Status');
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
			$form->addElement('commondata', 'status', $this->t('Status'),'CRM/Status',array('order_by_key'=>true));
		}

		$time_format = Base_RegionalSettingsCommon::time_12h()?'h:i a':'H:i';

		$form->addElement('datepicker', 'date_s', $this->t('Event start'));
		$form->addRule('date_s', 'Field is required!', 'required');
		$lang_code = Base_LangCommon::get_lang_code();
		$form->addElement('date', 'time_s', $this->t('Time'), array('format'=>$time_format, 'optionIncrement'  => array('i' => 5),'language'=>$lang_code));

		$dur = array(
			-1=>$this->ht('---'),
			300=>$this->ht('5 minutes'),
			900=>$this->ht('15 minutes'),
			1800=>$this->ht('30 minutes'),
			2700=>$this->ht('45 minutes'),
			3600=>$this->ht('1 hour'),
			7200=>$this->ht('2 hours'),
			14400=>$this->ht('4 hours'),
			28800=>$this->ht('8 hours'));
		eval_js_once('crm_calendar_duration_switcher = function(x,def) {'.
			'var sw = $(\'duration_switch\');'.
			'if(typeof(def)!=\'undefined\') '.
				'sw.value=def;'.
			'if((!x && sw.value==\'0\') || (x && sw.value==\'1\')) {'.
			'var end_b=$(\'crm_calendar_event_end_block\');if(end_b)end_b.hide();'.
			'var dur_b=$(\'crm_calendar_duration_block\');if(dur_b)dur_b.show();'.
			'sw.value=\'1\';'.
			'} else {'.
			'var end_b=$(\'crm_calendar_event_end_block\');if(end_b)end_b.show();'.
			'var dur_b=$(\'crm_calendar_duration_block\');if(dur_b)dur_b.hide();'.
			'sw.value=\'0\';'.
			'}}');
		$theme->assign('toggle_duration','<a class="button" href="javascript:void(0)" onClick="crm_calendar_duration_switcher()" id="toggle_duration_button">'.$this->t('Toggle').'</a>');
		$theme->assign('duration_block_id','crm_calendar_duration_block');
		$theme->assign('event_end_block_id','crm_calendar_event_end_block');
		$form->addElement('hidden','duration_switch',$duration_switch,array('id'=>'duration_switch'));
		eval_js('crm_calendar_duration_switcher(1,'.$duration_switch.')');
		$form->addElement('select', 'duration', $this->t('Duration'),$dur);
		$form->addRule('duration',$this->t('Duration not selected'),'neq','-1');

		//$form->addElement('datepicker', 'date_e', $this->t('Event end'));
		//$form->addRule('date_e', 'Field is required!', 'required');
		$form->addElement('date', 'time_e', $this->t('Event end'), array('format'=>$time_format, 'optionIncrement'  => array('i' => 5), 'language'=>$lang_code));
		$form->addRule('time_e', 'Field is required!', 'required');

		eval_js_once('crm_calendar_event_timeless = function(val) {'.
				'var cal_style;'.
				'var tdb=$(\'toggle_duration_button\');'.
				'if(tdb==null) return;'.
				'if(val){'.
				'cal_style = \'none\';'.
				'}else{'.
				'cal_style = \'block\';'.
				'}'.
				'var db = $(\'duration\');'.
				'if(db) db.style.display = cal_style;'.
				'var te = $(\'time_e\');'.
				'if(te) te.style.display = cal_style;'.
				'var ts = $(\'time_s\');'.
				'if(ts) ts.style.display = cal_style;'.
				'tdb.style.display = cal_style;'.
			'}');
		$form->addElement('checkbox', 'timeless', $this->t('Timeless'), null,array('onClick'=>'crm_calendar_event_timeless(this.checked)','id'=>'timeless'));
		if ($action=='view') $condition = $timeless;
		else $condition = 'document.getElementsByName(\'timeless\')[0].checked';
		eval_js('crm_calendar_event_timeless('.(($timeless || $timeless==='timeless')?'1':'0').')');

		$form->registerRule('check_dates', 'callback', 'check_dates', $this);
		$form->addRule(array('date_s','time_e', 'date_s', 'time_s', 'timeless','duration_switch'), 'End date must be after begin date...', 'check_dates');


		$form->addElement('header', null, $this->t('Event itself'));

		$color = CRM_Calendar_EventCommon::get_available_colors();
		$color[0] = $this->t('Default').': '.$this->ht(ucfirst($color[0]));
		for($k=1; $k<count($color); $k++)
			$color[$k] = '&bull; '.$this->ht(ucfirst($color[$k]));

		$form->addElement('select', 'access', $this->t('Access'), self::$access, array('style'=>'width: 100%;'));
		$form->addElement('select', 'priority', $this->t('Priority'), self::$priority, array('style'=>'width: 100%;'));
		$form->addElement('select', 'color', $this->t('Color'), $color, array('style'=>'width: 100%;'));

		$emp = array();
		$emp_alarm = array();
		$ret = CRM_ContactsCommon::get_contacts(array('company_name'=>array(CRM_ContactsCommon::get_main_company())), array(), array('last_name'=>'ASC', 'first_name'=>'ASC'));
		foreach($ret as $c_id=>$data) {
			$emp[$c_id] = $data['last_name'].' '.$data['first_name'];
			if(is_numeric($data['login']))
				$emp_alarm[$c_id] = $data['login'];
		}
		if ($action=='view') {
			$form->addElement('static', 'emp_id', $this->t('Employees'));
			$form->addElement('static', 'cus_id', $this->t('Customers'));
			$cus_id = '';
			$emp_id = '';
			foreach ($def['cus_id'] as $v)
				$cus_id .= CRM_ContactsCommon::contact_format_default(CRM_ContactsCommon::get_contact($v)).'<br>';
			foreach ($def['emp_id'] as $v)
				$emp_id .= CRM_ContactsCommon::contact_format_no_company(CRM_ContactsCommon::get_contact($v)).'<br>';
			$def['cus_id'] = $cus_id;
			$def['emp_id'] = $emp_id;
			$theme->assign('subscribe_icon',Utils_WatchdogCommon::get_change_subscription_icon('crm_calendar',$id));
		} else {
			//$cus = array();
			//$ret = CRM_ContactsCommon::get_contacts(array('(:Fav'=>true, '|:Recent'=>true, '|id'=>$def['cus_id']), array(), array('last_name'=>'ASC', 'first_name'=>'ASC'));
			//foreach($ret as $c_id=>$data)
			//	$cus[$c_id] = CRM_ContactsCommon::contact_format_default($data);

			$form->addElement('multiselect', 'emp_id', $this->t('Employees'), $emp);
			$form->addRule('emp_id', $this->t('At least one employee must be assigned to an event.'), 'required');

			$form->addElement('automulti', 'cus_id', $this->t('Customers'), array('CRM_ContactsCommon','automulti_contact_suggestbox'), array(array()), array('CRM_ContactsCommon','contact_format_default'));
		}

		if($action == 'new') {
			eval_js_once('crm_calendar_event_messenger = function(v) {if(v)$("messenger_block").show();else $("messenger_block").hide();}');
			$theme->assign('messenger_block','messenger_block');
			$form->addElement('select','messenger_before',$this->t('Popup alert'),array(0=>$this->ht('on event start'), 900=>$this->ht('15 minutes before event'), 1800=>$this->ht('30 minutes before event'), 2700=>$this->ht('45 minutes before event'), 3600=>$this->ht('1 hour before event'), 2*3600=>$this->ht('2 hours before event'), 3*3600=>$this->ht('3 hours before event'), 4*3600=>$this->ht('4 hours before event'), 8*3600=>$this->ht('8 hours before event'), 12*3600=>$this->ht('12 hours before event'), 24*3600=>$this->ht('24 hours before event')));
			$form->addElement('textarea','messenger_message',$this->t('Popup message'), array('id'=>'messenger_message'));
			$form->addElement('select','messenger_on',$this->t('Alert'),array('none'=>$this->ht('None'),'me'=>$this->ht('me'),'all'=>$this->ht('all selected employees')),array('onChange'=>'crm_calendar_event_messenger(this.value!="none");$("messenger_message").value=$("event_title").value;'));
//			$form->addElement('checkbox','messenger_on',$this->t('Alert me'),null,array('onClick'=>'crm_calendar_event_messenger(this.checked);$("messenger_message").value=$("event_title").value;'));
			eval_js('crm_calendar_event_messenger('.(($form->exportValue('messenger_on')!='none' && $form->exportValue('messenger_on')!='')?1:0).')');
			$form->registerRule('check_my_user', 'callback', 'check_my_user', $this);
			$form->addRule(array('messenger_on','emp_id'), $this->t('You have to select your contact to set alarm on it'), 'check_my_user');
		}

		eval_js_once('crm_calendar_event_recurrence_custom = function(v) {if(v) $("recurrence_custom_days").show(); else $("recurrence_custom_days").hide();}');
		eval_js_once('crm_calendar_event_recurrence_no_end_date = function(v) {if(v) $("recurrence_end_date").disable(); else $("recurrence_end_date").enable();}');
		eval_js_once('crm_calendar_event_recurrence = function(v) {if(v) $("recurrence_block").show(); else $("recurrence_block").hide();if(v) crm_calendar_event_recurrence_custom($("recurrence_interval").value=="week_custom");crm_calendar_event_recurrence_no_end_date($("recurrence_no_end_date").checked)}');
		$theme->assign('recurrence_block','recurrence_block');
		$form->addElement('checkbox','recurrence',$this->t('Recurrence event'),null,array('onClick'=>'crm_calendar_event_recurrence(this.checked)'));
//		print('='.$form->exportValue('recurrence').'=');
		eval_js('crm_calendar_event_recurrence('.(($form->exportValue('recurrence') || $def['recurrence'])?1:0).')');
		$form->addElement('select','recurrence_interval',$this->t('Recurrence interval'),array('everyday'=>$this->ht('everyday'),'second'=>$this->ht('every second day'),'third'=>$this->ht('every third day'),'fourth'=>$this->ht('every fourth day'),'fifth'=>$this->ht('every fifth day'),'sixth'=>$this->ht('every sixth day'),'week'=>$this->ht('once every week'),'week_custom'=>$this->ht('customize week'),'two_weeks'=>$this->ht('every two weeks'),'month'=>$this->ht('every month'),'year'=>$this->ht('every year')),array('onChange'=>'crm_calendar_event_recurrence_custom(this.value=="week_custom")', 'id'=>'recurrence_interval'));
		$theme->assign('recurrence_custom_days','recurrence_custom_days');
		$custom_week = array();
		$custom_week[] = $form->createElement('checkbox','0',null,$this->t('Monday'));
		$custom_week[] = $form->createElement('checkbox','1',null,$this->t('Tuesday'));
		$custom_week[] = $form->createElement('checkbox','2',null,$this->t('Wednesday'));
		$custom_week[] = $form->createElement('checkbox','3',null,$this->t('Thursday'));
		$custom_week[] = $form->createElement('checkbox','4',null,$this->t('Friday'));
		$custom_week[] = $form->createElement('checkbox','5',null,$this->t('Saturday'));
		$custom_week[] = $form->createElement('checkbox','6',null,$this->t('Sunday'));
		$form->addGroup($custom_week,'custom_days');
//		trigger_error($form->exportValue('recurrence'));
		if($form->exportValue('recurrence') && $form->exportValue('recurrence_interval')==='week_custom')
			$form->addGroupRule('custom_days',$this->t('Please check at least one day'),'required',null,1);
		$form->addElement('checkbox','recurrence_no_end_date',$this->t('No end date'),null,array('onClick'=>'crm_calendar_event_recurrence_no_end_date(this.checked)','id'=>'recurrence_no_end_date'));
		$form->addElement('datepicker','recurrence_end_date',$this->t('End date'),array('id'=>'recurrence_end_date'));
		if($form->exportValue('recurrence') && !$form->exportValue('recurrence_no_end_date'))
			$form->addRule('recurrence_end_date', $this->t('Field required.'), 'required');
		$form->registerRule('check_recurrence2', 'callback', 'check_recurrence2', $this);
		$form->addRule(array('recurrence_end_date','recurrence','date_s','recurrence_no_end_date'), $this->t('End date cannot be before start date.'), 'check_recurrence2');

//		if($action != 'view') {
//			$rb2 = $this->init_module('Utils/RecordBrowser/RecordPicker');
//			$rb2->disable_actions();
//			$this->display_module($rb2, array('contact', 'cus_id', array('CRM_ContactsCommon','contact_format_no_company'), array(), array('work_phone'=>false, 'mobile_phone'=>false, 'zone'=>false), array('last_name'=>'ASC')));
//			$cus_click = $rb2->create_open_link($this->t('Advanced'));
//		} else {
//			$cus_click = '';
//		}
		$form->addElement('text', 'rel_emp', $this->t('Related Person'), array('style'=>'width: 100%;'));

		$form->addElement('textarea', 'description',  $this->t('Description'), array('rows'=>6, 'style'=>'width: 100%;'));

		if($action === 'view') {
			$form->addElement('static', 'created_by',  $this->t('Created by'));
			$form->addElement('static', 'created_on',  $this->t('Created on'));
			$form->addElement('static', 'edited_by',  $this->t('Edited by'));
			$form->addElement('static', 'edited_on',  $this->t('Edited on'));
			$theme->assign('info_tooltip', '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('Utils_RecordBrowser','Created on:').' '.Base_RegionalSettingsCommon::time2reg($def['created_on']). '<br>'.
					Base_LangCommon::ts('Utils_RecordBrowser','Created by:').' '.$def['created_by']. '<br>'.
					Base_LangCommon::ts('Utils_RecordBrowser','Edited on:').' '.($def['edited_on']!='---'?Base_RegionalSettingsCommon::time2reg($def['edited_on']):$def['edited_on']). '<br>'.
					Base_LangCommon::ts('Utils_RecordBrowser','Edited by:').' '.$def['edited_by']).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','info.png').'" /></a>');
		}
		
		$fields = DB::GetAssoc('SELECT field, callback FROM crm_calendar_event_custom_fields');
		
		$custom_fields = array();
		foreach ($fields as $k=>$v) {
			call_user_func(explode('::',$v), $form, $action, isset($event)?$event:array());
			$custom_fields[] = $k;
		}

		$form->setDefaults($def);
		
		$theme->assign('custom_fields',$custom_fields);
		$theme->assign('access_id',$form->exportValue('access'));
		$theme->assign('priority_id',$form->exportValue('priority'));
		$theme->assign('color_id',$form->exportValue('color'));
		$theme->assign('status_id',isset($event['status'])?$event['status']:0);

		if ($form->validate()) {
			$values = $form->exportValues();
			$added_contacts = array();
			foreach ($values['emp_id'] as $v) $added_contacts[$v] = $v;
			foreach ($values['cus_id'] as $v) $added_contacts[$v] = $v;
			if (!isset($values['timeless'])) $values['timeless'] = false;
			if($action == 'new' || $action=='clone') {
				$id = CRM_CalendarCommon::$last_added = $this->add_event($values);
				Utils_WatchdogCommon::new_event('crm_calendar',CRM_CalendarCommon::$last_added,'Event added');
			} else {
				foreach ($defec['emp_id'] as $v) unset($added_contacts[$v]);
				foreach ($defec['cus_id'] as $v) unset($added_contacts[$v]);
				$this->update_event($id, $values);
				Utils_WatchdogCommon::new_event('crm_calendar',$id,'Event updated');
			}
			$fields = DB::GetAssoc('SELECT field, callback FROM crm_calendar_event_custom_fields');
			foreach ($fields as $k=>$v) {
				if (!preg_match('/^[a-zA-Z_]+$/', $k)) trigger_error('Invalid field: '.$k, E_USER_ERROR);
				else DB::Execute('UPDATE crm_calendar_event SET '.$k.'=%s WHERE id=%d', array($values[$k], $id));
			}

			foreach ($values['emp_id'] as $v) {
				$uid = Utils_RecordBrowserCommon::get_value('contact',$v,'Login');
				if ($uid) Utils_WatchdogCommon::user_subscribe($uid,'crm_calendar',$id);
			}
			foreach ($added_contacts as $v) {
				$subs = Utils_WatchdogCommon::get_subscribers('contact',$v);
				foreach($subs as $s)
					Utils_WatchdogCommon::user_subscribe($s, 'crm_calendar',$id);
			}
			$this->back_to_calendar();
			return false;
		}

		if($action == 'view') {
			$form->freeze();

			$tb = $this->init_module('Utils/TabbedBrowser');
			$tb->start_tab('Notes');
			//attachments
			$writable = ($def['access']==0 || in_array($my_id,$def_emp_id) || Base_AclCommon::i_am_admin()) && !$event['deleted'];
			$a = $this->init_module('Utils/Attachment',array('CRM/Calendar/Event/'.$id));
			$a->set_view_func(array('CRM_CalendarCommon','search_format'),array($id));
			$a->set_inline_display();
			$a->additional_header('Event: '.$event['title']);
			$a->allow_protected($this->acl_check('view protected notes'),$writable && $this->acl_check('edit protected notes'));
			$a->allow_public($this->acl_check('view public notes'),$writable && $this->acl_check('edit public notes'));
			$this->display_module($a);
			$tb->end_tab();

			if(!$event['deleted']) {
			    $tb->start_tab('Alerts');
    			    $mes_users = array();
			    foreach ($def_emp_id as $r)
				if(isset($emp_alarm[$r]))
					$mes_users[$emp_alarm[$r]] = $emp[$r];
			    $mes = $this->init_module('Utils/Messenger',array('CRM_Calendar_Event:'.$id,array('CRM_Calendar_EventCommon','get_alarm'),array($id),$event['start'],$mes_users));
			    $mes->set_inline_display();
			    $this->display_module($mes);
			    $tb->end_tab();
			}
			
			$tb->tag();
			$theme->assign('tabs', $this->get_html_of_module($tb));
		}

//		$theme->assign('view_style', 'new_event');
//		$theme->assign('cus_click', $cus_click);
		$form->assign_theme('form', $theme);

		$theme->display();

		if ($action=='view') {
			$pdf = $this->pack_module('Libs/TCPDF', 'P');
			$filename = '';
			if ($pdf->prepare()) {
				$ev = DB::GetRow('SELECT * FROM crm_calendar_event WHERE id=%d', array($id));
				$pdf->set_title($this->t('Event').': '.$ev['title']);
				if (!$ev['timeless']) $pdf->set_subject(Base_RegionalSettingsCommon::time2reg($ev['starts']).' - '.Base_RegionalSettingsCommon::time2reg($ev['ends']));
				else $pdf->set_subject(Base_RegionalSettingsCommon::time2reg($ev['starts'],false));
				$pdf->prepare_header();

				$pdf->AddPage();
				$this->make_event_PDF($pdf,$id);
				$filename = $this->t('Event_%s', array($ev['title']));
			}
			$pdf->add_actionbar_icon($filename);

			if($def['recurrence'])
				Base_ActionBarCommon::add('save','Split',$this->create_callback_href(array($this,'split_event'),array($id,$def)));

			if($event['deleted']) {
				Base_ActionBarCommon::add(Base_ThemeCommon::get_template_file('CRM_Calendar_Event','restore.png'),'Restore', $this->create_callback_href(array('CRM_Calendar_EventCommon', 'restore_event'), array($id)));
			} elseif($writable) {
				Base_ActionBarCommon::add('edit','Edit', $this->create_callback_href(array($this, 'view_event'), array('edit', $id)));
				Utils_ShortcutCommon::add(array('Ctrl','E'), 'function(){'.$this->create_callback_href_js(array($this, 'view_event'), array('edit', $id)).'}');
				Base_ActionBarCommon::add('clone','Clone', $this->create_confirm_callback_href($this->ht('You are about to create a copy of this record. Do you want to continue?'),array($this,'clone_event'),array($id)));
			}
		} else {
			Utils_ShortcutCommon::add(array('Ctrl','S'), 'function(){'.$form->get_submit_form_js(true).'}');
			Base_ActionBarCommon::add('save','Save',' href="javascript:void(0)" onClick="'.addcslashes($form->get_submit_form_js(true),'"').'"');
		}
		Base_ActionBarCommon::add('back','Back',$this->create_back_href());
		Utils_ShortcutCommon::add(array('Esc'), 'function(){'.$this->create_back_href_js().'}');
		return true;
	}
	
	public function split_event($id,$def) {
		CRM_Calendar_EventCommon::split_event($id,$def);
	}
	
	public function check_my_user($arg) {
		if($arg[0]!=='me') return true;
		$sub = array_filter(explode('__SEP__',$arg[1]));
		$me = CRM_ContactsCommon::get_my_record();
		return in_array($me['id'],$sub);
	}

	public function check_dates($arg) {
		if($arg[5]) return true;
		$start = ($arg[4]==true?strtotime($arg[2]):recalculate_time($arg[2],$arg[3]));
		$end = ($arg[4]==true?strtotime($arg[0]):recalculate_time($arg[0],$arg[1]));
		return $end >= $start;
	}

	public function check_recurrence2($arg) {
		if(!$arg[1] || (isset($arg[3]) && $arg[3])) return true;
		$start = strtotime(strftime('%Y-%m-%d',Base_RegionalSettingsCommon::reg2time($arg[2],false)));
		$end = strtotime(strftime('%Y-%m-%d',Base_RegionalSettingsCommon::reg2time($arg[0],false)));
		return $end > $start;
	}

	public function add_event($vals = array()){
		$start = recalculate_time($vals['date_s'],$vals['time_s']);
		if($vals['duration_switch']) {
			$end = $start + $vals['duration'];
			if(date('Y-m-d',$start)!=date('Y-m-d',$end))
				$end = strtotime(date('Y-m-d',$start).' 23:59');
		} else
			$end = recalculate_time($vals['date_s'],$vals['time_e']);
		if($vals['timeless']) {
			$start = strtotime(date('Y-m-d',$start));
			$end = strtotime(date('Y-m-d',$end));
		} else {
			$start = Base_RegionalSettingsCommon::reg2time(date('Y-m-d H:i:s',$start),true);
			$end = Base_RegionalSettingsCommon::reg2time(date('Y-m-d H:i:s',$end),true);
		}
		DB::Execute('INSERT INTO crm_calendar_event (title,'.
													'description,'.
													'starts,'.
													'ends,'.
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

		if(isset($vals['messenger_on']) && $vals['messenger_on']!='none') {
			if($vals['messenger_on']=='me')
				Utils_MessengerCommon::add('CRM_Calendar_Event:'.$id,$this->get_type(),$vals['messenger_message'],$start-$vals['messenger_before'], array('CRM_Calendar_EventCommon','get_alarm'),array($id));
			else {
				$eee = array();
				foreach($vals['emp_id'] as $v) {
					$c = CRM_ContactsCommon::get_contact($v);
					if(isset($c['login']))
						$eee[] = $c['login'];
				}
				Utils_MessengerCommon::add('CRM_Calendar_Event:'.$id,$this->get_type(),$vals['messenger_message'],$start-$vals['messenger_before'], array('CRM_Calendar_EventCommon','get_alarm'),array($id),$eee);
			}
		}

		foreach($vals['emp_id'] as $v)
				DB::Execute('INSERT INTO crm_calendar_event_group_emp (id,contact) VALUES (%d, %d)', array($id, $v));
		foreach($vals['cus_id'] as $v)
				DB::Execute('INSERT INTO crm_calendar_event_group_cus (id,contact) VALUES (%d, %d)', array($id, $v));
		if(isset($vals['recurrence']) && $vals['recurrence']) {
			$type = CRM_Calendar_EventCommon::recurrence_type($vals['recurrence_interval']);
			if(isset($vals['recurrence_no_end_date']) && $vals['recurrence_no_end_date'])
				DB::Execute('UPDATE crm_calendar_event SET recurrence_type=%d,recurrence_end=null WHERE id=%d',array($type,$id));
			else
				DB::Execute('UPDATE crm_calendar_event SET recurrence_type=%d,recurrence_end=%D WHERE id=%d',array($type,$vals['recurrence_end_date'],$id));
			if($vals['recurrence_interval'] == 'week_custom') {
				$days = '0000000';
				foreach($vals['custom_days'] as $k=>$v)
					$days{$k} = '1';
				DB::Execute('UPDATE crm_calendar_event SET recurrence_hash=%s WHERE id=%d',array($days,$id));
			}
		}
		return $id;
	}

	public function update_event($id, $vals = array()){
		$start = recalculate_time($vals['date_s'],$vals['time_s']);
		$debug = strtotime('2008-10-26 3:00:00').'--'.$start.' - '.date('Y-m-d H:i:s',$start).' ----- ';
		if($vals['duration_switch']) {
			$end = $start + $vals['duration'];
			if(date('Y-m-d',$start)!=date('Y-m-d',$end))
				$end = strtotime(date('Y-m-d',$start).' 23:59');
		} else
			$end = recalculate_time($vals['date_s'],$vals['time_e']);
		if($vals['timeless']) {
			$start = strtotime(date('Y-m-d',$start));
			$end = strtotime(date('Y-m-d',$end));
		} else {
			$start = Base_RegionalSettingsCommon::reg2time(date('Y-m-d H:i:s',$start),true);
			$end = Base_RegionalSettingsCommon::reg2time(date('Y-m-d H:i:s',$end),true);


		}
//		$prev = DB::GetRow('SELECT * FROM crm_calendar_event WHERE id=%d',array($id));
		DB::Execute('UPDATE crm_calendar_event SET title=%s,'.
													'description=%s,'.
													'starts=%d,'.
													'ends=%d,'.
													'timeless=%d,'.
													'access=%d,'.
													'priority=%d,'.
													'color=%d,'.
													'status=%d,'.
													'edited_by=%d,'.
													'edited_on=%T,recurrence_type=null WHERE id=%d',
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
		if(isset($vals['recurrence']) && $vals['recurrence']) {
			$type = CRM_Calendar_EventCommon::recurrence_type($vals['recurrence_interval']);
			if(isset($vals['recurrence_no_end_date']) && $vals['recurrence_no_end_date'])
				DB::Execute('UPDATE crm_calendar_event SET recurrence_type=%d,recurrence_end=null WHERE id=%d',array($type,$id));
			else
				DB::Execute('UPDATE crm_calendar_event SET recurrence_type=%d,recurrence_end=%D WHERE id=%d',array($type,$vals['recurrence_end_date'],$id));
			if($vals['recurrence_interval'] == 'week_custom') {
				$days = '0000000';
				foreach($vals['custom_days'] as $k=>$v)
					$days{$k} = '1';
				DB::Execute('UPDATE crm_calendar_event SET recurrence_hash=%s WHERE id=%d',array($days,$id));
			}
		}
	}

	public function get_navigation_bar_additions() {
		$custom_handlers = DB::GetAssoc('SELECT id, group_name FROM crm_calendar_custom_events_handlers');
		if (empty($custom_handlers)) return '';
		$form = $this->init_module('Libs/QuickForm');

		$form->addElement('checkbox', 'events_handlers__', $this->t('Events'), null, array('onchange'=>$form->get_submit_form_js()));
		$elements_name = array(-1=>'events_handlers__');
		$default = array(-1);
		foreach ($custom_handlers as $k=>$v) {
			$form->addElement('checkbox', 'events_handlers__'.$k, $this->t($v), null, array('onchange'=>$form->get_submit_form_js()));
			$elements_name[$k] = 'events_handlers__'.$k;
			$default[] = $k;
		}

		$selected = $this->get_module_variable('events_handlers', $default);
		if ($form->validate()) {
			$vals = $form->exportValues();
			$selected = array();
			foreach ($elements_name as $k=>$e)
				if (isset($vals[$e]) && $vals[$e]) $selected[] = $k;
			$this->set_module_variable('events_handlers', $selected);
		}
		CRM_Calendar_EventCommon::$events_handlers = $selected;

		foreach ($selected as $k=>$e)
			$form->setDefaults(array($elements_name[$e]=>true));

		$theme = $this->init_module('Base/Theme');
		$theme->assign('elements_name', $elements_name);
		$form->assign_theme('form', $theme);
		ob_start();
		$theme->display('custom_event_handlers_form');
		$handlers_form = ob_get_clean();

		return $handlers_form;
	}

	
	public function clone_event($id) {
		if(!$this->view_event('clone',$id))
			$this->back_to_calendar();
		return true;
	}

}

?>
