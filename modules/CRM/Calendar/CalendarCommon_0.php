<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage calendar
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_CalendarCommon extends ModuleCommon {
	public static $last_added = null;

	public static function body_access() {
		return self::Instance()->acl_check('access');
	}
	
	public static function menu() {
		if(self::Instance()->acl_check('access'))
			return array('CRM'=>array('__submenu__'=>1,'Calendar'=>array()));
		else
			return array();
	}

	public static function view_event($func, $def) {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if (!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		if ($func=='add') $def = array(date('Y-m-d H:i:s'), false, $def);
		$x->push_main('CRM_Calendar_Event',$func,$def);
	}

	public static function get_new_event_href($def, $id='none'){
		if (self::$last_added!==null) {
			if (is_numeric(self::$last_added)) self::view_event('view', self::$last_added);
			self::$last_added = null;
		}
		if (isset($_REQUEST['__add_event']) &&
			($id==$_REQUEST['__add_event'])) {
			unset($_REQUEST['__add_event']);
			self::view_event('add',$def);
			return array();
		}
		return array('__add_event'=>$id);
	}
	public static function create_new_event_href($def, $id='none'){
		return Module::create_href(self::get_new_event_href($def, $id));
	}

	public static function user_settings() {
		if(self::Instance()->acl_check('access')) {
/*			$start_day = array();
			foreach(range(0, 11) as $x)
				$start_day[$x.':00'] = Base_RegionalSettingsCommon::time2reg($x.':00',2,false,false);
			$end_day = array();
			foreach(range(12, 23) as $x)
				$end_day[$x.':00'] = Base_RegionalSettingsCommon::time2reg($x.':00',2,false,false);*/
			$start_day = array();
			foreach(range(0, 23) as $x)
				$start_day[$x.':00'] = Base_RegionalSettingsCommon::time2reg($x.':00',2,false,false);
			$end_day = $start_day;

			$color = array(1 => 'green', 2 => 'yellow', 3 => 'red', 4 => 'blue', 5=> 'gray', 6 => 'cyan', 7 =>'magenta');
			return array(
				'Calendar'=>array(
					array('name'=>'default_view','label'=>'Default view', 'type'=>'select', 'values'=>array('agenda'=>'Agenda', 'day'=>'Day', 'week'=>'Week', 'month'=>'Month', 'year'=>'Year'), 'default'=>'week'),

					array('name'=>'start_day','label'=>'Start day at', 'type'=>'select', 'values'=>$start_day, 'default'=>'8:00'),
					array('name'=>'end_day','label'=>'End day at', 'type'=>'select', 'values'=>$end_day, 'default'=>'17:00'),
					array('name'=>'interval','label'=>'Interval of grid', 'type'=>'select', 'values'=>array('0:30'=>'30 minutes','1:00'=>'1 hour','2:00'=>'2 hours'), 'default'=>'1:00'),
					array('name'=>'default_color','label'=>'Default event color', 'type'=>'select', 'values'=>$color, 'default'=>'1')
				)
			);
		}
		return array();
	}

	public static function applet_caption() {
		if(!self::Instance()->acl_check('access'))
			return false;

		return "Agenda";
	}

	public static function applet_info() {
		return "Displays Calendar Agenda";
	}

	public static function applet_settings() {
		$cols = CRM_Calendar_EventCommon::get_available_colors();
		$cols[0] = 'All';
		$ret = array(	array('name'=>'days', 'label'=>'Look for events in', 'type'=>'select', 'default'=>'7', 'values'=>array('1'=>'1 day','2'=>'2 days','3'=>'3 days','5'=>'5 days','7'=>'1 week','14'=>'2 weeks', '30'=>'1 month', '61'=>'2 months')),
						array('name'=>'color', 'label'=>'Only events with selected color', 'type'=>'select', 'default'=>'0', 'values'=>$cols));
		$custom_events = DB::GetAssoc('SELECT id, group_name FROM crm_calendar_custom_events_handlers ORDER BY group_name');
		if (!empty($custom_events)) {
			$ret[] = array('name'=>'events_handlers__', 'label'=>'Meetings', 'type'=>'checkbox', 'default'=>'1');
			foreach ($custom_events as $id=>$l)
				$ret[] = array('name'=>'events_handlers__'.$id, 'label'=>$l, 'type'=>'checkbox', 'default'=>'1');
		}
		return $ret;
	}
	
	public static function search_format($id) {
		if(!self::Instance()->acl_check('access'))
			return false;

/*		$query = 'SELECT ev.starts as start,ev.title,ev.id FROM crm_calendar_event ev '.
					'WHERE ((ev.access<2 OR ev.created_by='.Acl::get_user().') AND '.
 					'ev.id=%d)';
 		$row = DB::GetRow($query,array($id));*/
 		$row = array();
		
		if(!$row) return false;
		return '<a '.Base_BoxCommon::create_href(null, 'CRM_Calendar', null, array(), array(), array('search_date'=>$row['start'],'ev_id'=>$row['id'])).'>'.Base_LangCommon::ts('CRM_Calendar','Event (attachment) #%d, %s',array($row['id'], $row['title'])).'</a>';
	}

	public static function search($word){
		if(!self::Instance()->acl_check('access'))
			return array();
		
/*		$query = 'SELECT ev.starts as start,ev.title,ev.id FROM crm_calendar_event ev '.
					'WHERE ((ev.access<2 OR ev.created_by='.Acl::get_user().') AND (ev.title LIKE '.DB::Concat('\'%\'',DB::qstr($word),'\'%\'').
 					' OR ev.description LIKE '.DB::Concat('\'%\'',DB::qstr($word),'\'%\'').
					'))';
 		$recordSet = DB::Execute($query);*/
 		$result = array();

/* 		while (!$recordSet->EOF){
 			$row = $recordSet->FetchRow();
			$result[$row['id']] = '<a '.Base_BoxCommon::create_href(null, 'CRM_Calendar', null, array(), array(), array('search_date'=>$row['start'],'ev_id'=>$row['id'])).'>'.Base_LangCommon::ts('CRM_Calendar','Event #%d, %s',array($row['id'], $row['title'])).'</a>';
 		} 		*/
		return $result;
	}

	public static function watchdog_label($rid = null, $events = array()) {
		$ret = array('category'=>Base_LangCommon::ts('CRM_Calendar', 'Events'));
		if ($rid!==null) {
			$title = DB::GetOne('SELECT title FROM crm_calendar_event WHERE id=%d',array($rid));
			if ($title===false || $title===null)
				return null;
			$access = DB::GetOne('SELECT access FROM crm_calendar_event WHERE id=%d',array($rid));
			if ($access>=2) {
				$me = CRM_ContactsCommon::get_my_record();
				$am_i_there = DB::GetOne('SELECT 1 FROM crm_calendar_event_group_emp WHERE id=%d AND contact=%d',array($rid, $me['id']));
				if ($am_i_there===false || $am_i_there===null) return null;
			}
			$ret['view_href'] = Module::create_href(array('crm_calendar_watchdog_view_event'=>$rid));
			if (isset($_REQUEST['crm_calendar_watchdog_view_event'])
				&& $_REQUEST['crm_calendar_watchdog_view_event']==$rid) {
				unset($_REQUEST['crm_calendar_watchdog_view_event']);
				$x = ModuleManager::get_instance('/Base_Box|0');
				if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
				$x->push_main('CRM_Calendar_Event','view',$rid);
			}
			$ret['title'] = '<a '.$ret['view_href'].'>'.$title.'</a>';
			$events_display = array();
			foreach ($events as $v) {
				$events_display[] = '<b>'.Base_LangCommon::ts('CRM_Calendar',$v).'</b>';	
			}
			$ret['events'] = implode('<hr>',array_reverse($events_display));
		}
		return $ret;
	}
	
	//////////////////////////////////////////////
	/// mobile methods
	
	public static function mobile_menu() {
		if(Acl::is_user())
			return array('Calendar'=>array('func'=>'mobile_agenda','color'=>'green'));
	}
	
	public static function mobile_agenda($time_shift=0) {
		print(Base_RegionalSettingsCommon::time2reg(time()+$time_shift,false,true).' - '.Base_RegionalSettingsCommon::time2reg(time()+7*24*3600+$time_shift,false,true).'<br>');
	
//		print('<a '.(IPHONE?'class="button blue" ':'').mobile_stack_href(array('CRM_CalendarCommon','mobile_add_event')).'>'.Base_LangCommon::ts('Utils_Calendar','Add event').'</a>');
		CRM_Calendar_EventCommon::$filter = CRM_FiltersCommon::get();
		if($time_shift)
			print('<a '.(IPHONE?'class="button red" ':'').mobile_stack_href(array('CRM_CalendarCommon','mobile_agenda'),array(0)).'>'.Base_LangCommon::ts('Utils_Calendar','Show current week').'</a>');
		else
			print('<a '.(IPHONE?'class="button green" ':'').mobile_stack_href(array('CRM_CalendarCommon','mobile_agenda'),array(7 * 24 * 60 * 60)).'>'.Base_LangCommon::ts('Utils_Calendar','Show next week').'</a>');
		Utils_CalendarCommon::mobile_agenda('CRM/Calendar/Event',array('custom_agenda_cols'=>array('Description','Assigned to','Related with')),$time_shift,array('CRM_CalendarCommon','mobile_view_event'));
	}
	
	public static function mobile_add_event() {
		$qf = new HTML_QuickForm('calendar_add_ev', 'get','mobile.php?'.http_build_query($_GET));
		$qf->addElement('text', 'title', Base_LangCommon::ts('CRM_Calendar','Title'), array('style'=>'width: 100%;', 'id'=>'event_title'));
		$qf->addRule('title', 'Field is required!', 'required');
		
		$qf->addElement('commondata', 'status', Base_LangCommon::ts('CRM_Calendar','Status'),'CRM/Status',array('order_by_key'=>true));

		$time_format = 'Y M d<br>';
		$time_format .= Base_RegionalSettingsCommon::time_12h()?'h:i a':'H:i';
		$lang_code = Base_LangCommon::get_lang_code();
		$qf->addElement('date', 'time', Base_LangCommon::ts('CRM_Calendar','Time'), array('format'=>$time_format, 'optionIncrement'  => array('i' => 5),'language'=>$lang_code));
		$qf->addRule('time', Base_LangCommon::ts('CRM_Calendar','Field required'), 'required');

		$qf->addElement('checkbox', 'timeless', Base_LangCommon::ts('CRM_Calendar','Timeless'));

		$dur = array(
			-1=>Base_LangCommon::ts('CRM_Calendar','---'),
			300=>Base_LangCommon::ts('CRM_Calendar','5 minutes'),
			900=>Base_LangCommon::ts('CRM_Calendar','15 minutes'),
			1800=>Base_LangCommon::ts('CRM_Calendar','30 minutes'),
			2700=>Base_LangCommon::ts('CRM_Calendar','45 minutes'),
			3600=>Base_LangCommon::ts('CRM_Calendar','1 hour'),
			7200=>Base_LangCommon::ts('CRM_Calendar','2 hours'),
			14400=>Base_LangCommon::ts('CRM_Calendar','4 hours'),
			28800=>Base_LangCommon::ts('CRM_Calendar','8 hours'));
		$qf->addElement('select', 'duration', Base_LangCommon::ts('CRM_Calendar','Duration'),$dur);
		$qf->addRule('duration',Base_LangCommon::ts('CRM_Calendar','Duration not selected'),'neq','-1');

		$color = CRM_Calendar_EventCommon::get_available_colors();
		$color[0] = Base_LangCommon::ts('CRM_Calendar','Default').': '.Base_LangCommon::ts('CRM_Calendar',ucfirst($color[0]));
		for($k=1; $k<count($color); $k++)
			$color[$k] = '&bull; '.Base_LangCommon::ts('CRM_Calendar',ucfirst($color[$k]));

		$qf->addElement('textarea', 'description',  Base_LangCommon::ts('CRM_Calendar','Description'), array('rows'=>4, 'style'=>'width: 100%;'));

		$qf->addElement('select', 'access', Base_LangCommon::ts('CRM_Calendar','Access'), Utils_CommonDataCommon::get_translated_array('CRM/Access'), array('style'=>'width: 100%;'));
		$qf->addElement('select', 'priority', Base_LangCommon::ts('CRM_Calendar','Priority'), Utils_CommonDataCommon::get_translated_array('CRM/Priority'), array('style'=>'width: 100%;'));
		$qf->addElement('select', 'color', Base_LangCommon::ts('CRM_Calendar','Color'), $color, array('style'=>'width: 100%;'));

		$qf->addElement('submit', 'submit_button', Base_LangCommon::ts('CRM_Calendar','OK'),IPHONE?'class="button white"':'');
		$renderer =& $qf->defaultRenderer();
		$qf->accept($renderer);
		print($renderer->toHtml());
		
/*		$emp = array();
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
*/
	}
	
	public static function mobile_view_event($id) {
		$recurrence = strpos($id,'_');
		if($recurrence!==false)
			$id = substr($id,0,$recurrence);
		$row = CRM_Calendar_EventCommon::get($id);
		$row_orig = DB::GetRow('SELECT * FROM crm_calendar_event WHERE id=%d',array($id));
		$ex = Utils_CalendarCommon::process_event($row);
		
		print('<ul class="field">');
		print('<li>'.Base_LangCommon::ts('CRM_Calendar','Title').': '.$row['title'].'</li>');
		print('<li>'.Base_LangCommon::ts('CRM_Calendar','Starts').': '.$ex['start'].'</li>');
		print('<li>'.Base_LangCommon::ts('CRM_Calendar','Duration').': '.$ex['duration'].'</li>');
		print('<li>'.Base_LangCommon::ts('CRM_Calendar','Ends').': '.$ex['end'].'</li>');
		print('<li>'.Base_LangCommon::ts('CRM_Calendar','Description').': '.$row['description'].'</li>');
		print('<li>'.Base_LangCommon::ts('CRM_Calendar','Priority').': '.Utils_CommonDataCommon::get_value('CRM/Priority/'.$row_orig['priority'],true).'</li>');
		print('<li>'.Base_LangCommon::ts('CRM_Calendar','Status').': '.Utils_CommonDataCommon::get_value('CRM/Status/'.$row_orig['status'],true).'</li>');
		print('<li>'.Base_LangCommon::ts('CRM_Calendar','Access').': '.Utils_CommonDataCommon::get_value('CRM/Access/'.$row_orig['access'],true).'</li>');
		print('</ul>');
//		'color I1 DEFAULT 0, '.
	}
	
	public static function new_event_handler($name, $callback) {
		DB::Execute('INSERT INTO crm_calendar_custom_events_handlers(group_name, handler_callback) VALUES (%s, %s)', array($name, implode('::',$callback)));
	}
	
	public static function delete_event_handler($name) {
		DB::Execute('DELETE FROM crm_calendar_custom_events_handlers WHERE group_name=%s', array($name));
	}

}
?>
