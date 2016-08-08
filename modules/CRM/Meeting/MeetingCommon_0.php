<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage meeting
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_MeetingCommon extends ModuleCommon {
	public static function crm_calendar_handler($action) {
		$args = func_get_args();
		array_shift($args);
		$ret = null;
		switch ($action) {
			case 'get_all': $ret = call_user_func_array(array('CRM_MeetingCommon','crm_event_get_all'), $args);
							break;
			case 'update': $ret = call_user_func_array(array('CRM_MeetingCommon','crm_event_update'), $args);
							break;
			case 'get': $ret = call_user_func_array(array('CRM_MeetingCommon','crm_event_get'), $args);
							break;
			case 'delete': $ret = call_user_func_array(array('CRM_MeetingCommon','crm_event_delete'), $args);
							break;
			case 'new_event_types': $ret = array(array('label'=>__('Meeting'),'icon'=>Base_ThemeCommon::get_template_file('CRM_Meeting','icon.png')));
							break;
			case 'new_event': $ret = call_user_func_array(array('CRM_MeetingCommon','crm_new_event'), $args);
							break;
			case 'view_event': $ret = call_user_func_array(array('CRM_MeetingCommon','crm_view_event'), $args);
							break;
			case 'edit_event': $ret = call_user_func_array(array('CRM_MeetingCommon','crm_edit_event'), $args);
							break;
			case 'recordset': $ret = 'crm_meeting';
							break;
		}
		return $ret;
	}
	
	public static function crm_new_event($timestamp, $timeless, $id, $object, $cal_obj) {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$me = CRM_ContactsCommon::get_my_record();
		$defaults = array('employees'=>$me['id'], 'priority'=>CRM_CommonCommon::get_default_priority(), 'permission'=>0, 'status'=>0);
		$defaults['date'] = date('Y-m-d', $timestamp);
		$defaults['time'] = date('H:i:s', $timestamp);
		if($object) $defaults['employees'] = $object;
		$defaults['duration'] = $timeless?-1:3600;
		$x->push_main('Utils_RecordBrowser','view_entry',array('add', null, $defaults), 'crm_meeting');
	}

	public static function crm_view_event($id, $cal_obj) {
		$rb = $cal_obj->init_module('Utils_RecordBrowser', 'crm_meeting');
		$id = explode('_',$id);
		$rb->view_entry('view', $id[0], isset($id[1])?array('day'=>$id[1]):array());
		return true;
	}
	
	public static function crm_edit_event($id, $cal_obj) {
		$rb = $cal_obj->init_module('Utils_RecordBrowser', 'crm_meeting');
		$rb->view_entry('edit', $id);
		return true;
	}
	
	public static function applet_caption() {
		if (Utils_RecordBrowserCommon::get_access('crm_meeting','browse'))
			return __('Meetings');
	}

	public static function applet_info() {
		return __('Meetings list');
	}

	public static function meeting_bbcode($text, $param, $opt) {
		return Utils_RecordBrowserCommon::record_bbcode('crm_meeting', array('title'), $text, $param, $opt);
	}

	public static function applet_info_format($r){
		// Build array representing 2-column tooltip
		// Format: array (Label,value)
		$access = Utils_CommonDataCommon::get_translated_array('CRM/Access');
		$priority = Utils_CommonDataCommon::get_translated_array('CRM/Priority');
		$status = Utils_CommonDataCommon::get_translated_array('CRM/Status');

		$args=array(
					__('Meeting')=>'<b>'.$r['title'].'</b>',
					__('Description')=>$r['description'],
                    __('Assigned to')=>Utils_RecordBrowserCommon::get_val('crm_meeting', 'Employees', $r, true),
					__('Customers')=>Utils_RecordBrowserCommon::get_val('crm_meeting', 'Customers', $r, true),
					__('Status')=>$status[$r['status']],
					__('Date')=>$r['duration']>=0?Base_RegionalSettingsCommon::time2reg($r['date'].' '.date('H:i:s',strtotime($r['time']))):Base_RegionalSettingsCommon::time2reg($r['date'],false),
					__('Duration')=>$r['duration']>=0?Base_RegionalSettingsCommon::seconds_to_words($r['duration']):'---',
					__('Permission')=>$access[$r['permission']],
					__('Priority')=>$priority[$r['priority']],
					);
		
		$bg_color = '';
		switch ($r['priority']) {
			case 0: $bg_color = '#FFFFFF'; break; // low priority
			case 1: $bg_color = '#FFFFD5'; break; // medium
			case 2: $bg_color = '#FFD5D5'; break; // high
		}

		// Pass 1 argument: array containing pairs: label/value
		//return	Utils_TooltipCommon::format_info_tooltip($args);

		$ret = array('notes'=>Utils_TooltipCommon::format_info_tooltip($args));
		if ($bg_color) $ret['row_attrs'] = 'style="background:'.$bg_color.';"';
		return $ret;
	}

	public static function QFfield_duration(&$form, $field, $label, $mode, $default, $desc) {
		if ($mode=='add' || $mode=='edit') {
			$dur = array(
				-1=>'---',
				300=>__('5 minutes'),
				900=>__('15 minutes'),
				1800=>__('30 minutes'),
				2700=>__('45 minutes'),
				3600=>__('1 hour'),
				7200=>__('2 hours'),
				14400=>__('4 hours'),
				28800=>__('8 hours'));
			if (isset($dur[$default]))
				$duration_switch='1';
			else
				$duration_switch='0';
			$form->addElement('select', $field, $label, $dur, array('id'=>$field));
			$time_format = Base_RegionalSettingsCommon::time_12h()?'h:i a':'H:i';
			$lang_code = Base_LangCommon::get_lang_code();
			$form->addElement('timestamp', 'end_time', __('End Time'), array('date'=>false, 'format'=>$time_format, 'optionIncrement'  => array('i' => 5),'language'=>$lang_code, 'id'=>'end_time'));

			$form->addElement('hidden','duration_switch',$duration_switch,array('id'=>'duration_switch'));
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
			eval_js_once('crm_calendar_event_timeless = function(val) {'.
					'var cal_style;'.
					'var tdb=$(\'toggle_duration_button\');'.
					'if(tdb==null) return;'.
					'if(val){'.
					'cal_style = \'none\';'.
					'}else{'.
					'cal_style = \'\';'.
					'}'.
					'var db = $(\'duration_end_date__data_\');'.
					'if(db) db.style.display = cal_style;'.
					'var ts = $(\'time_s\');'.
					'if(ts) ts.style.display = cal_style;'.
				'}');
			$form->addElement('button', 'toggle', __('Toggle'), array('onclick'=>'crm_calendar_duration_switcher()', 'id'=>'toggle_duration_button', 'class'=>'button'));
			$form->addElement('checkbox', 'timeless', __('Timeless'), null, array('onClick'=>'crm_calendar_event_timeless(this.checked)', 'id'=>'timeless'));

			eval_js('crm_calendar_event_timeless($("timeless").checked)');
			eval_js('crm_calendar_duration_switcher(1)');

			$form->setDefaults(array('duration_switch'=>$duration_switch));
			$form->setDefaults(array($field=>$default));
			$form->setDefaults(array('timeless'=>($default==-1?1:0)));
			if (class_exists('Utils_RecordBrowser') && isset(Utils_RecordBrowser::$last_record['time']))
				$form->setDefaults(array('end_time'=>strtotime('+'.$default.' seconds', Utils_RecordBrowser::$last_record['time'])));

			$form->addFormRule(array('CRM_MeetingCommon','check_date_and_time'));
		} else {
			$form->addElement('checkbox', 'timeless', __('Timeless'));
			$form->setDefaults(array('timeless'=>($default==-1?1:0)));
		}

		//messanger
		if($mode == 'add') {
			eval_js_once('crm_calendar_event_messenger = function(v) {var mb=$("messenger_block");if(!mb)return;if(v)mb.show();else mb.hide();}');
			$form->addElement('select','messenger_before',__('Popup alert'),array(0=>__('on event start'), 900=>__('15 minutes before event'), 1800=>__('30 minutes before event'), 2700=>__('45 minutes before event'), 3600=>__('1 hour before event'), 2*3600=>__('2 hours before event'), 3*3600=>__('3 hours before event'), 4*3600=>__('4 hours before event'), 8*3600=>__('8 hours before event'), 12*3600=>__('12 hours before event'), 24*3600=>__('24 hours before event')));
			$form->addElement('textarea','messenger_message',__('Popup message'), array('id'=>'messenger_message'));
			$form->addElement('select','messenger_on',__('Alert'),array('none'=>__('None'),'me'=>__('me'),'all'=>__('all selected employees')),array('onChange'=>'crm_calendar_event_messenger(this.value!="none");$("messenger_message").value=$("title").value;'));
//			$form->addElement('checkbox','messenger_on',__('Alert me'),null,array('onClick'=>'crm_calendar_event_messenger(this.checked);$("messenger_message").value=$("event_title").value;'));
			eval_js('crm_calendar_event_messenger('.(($form->exportValue('messenger_on')!='none' && $form->exportValue('messenger_on')!='')?1:0).')');
			$form->registerRule('check_my_user', 'callback', array('CRM_MeetingCommon','check_my_user'));
			$form->addRule(array('messenger_on','emp_id'), __('You have to select your contact to set alarm on it'), 'check_my_user');
		}
	}

	public static function check_my_user($arg) {
		if($arg[0]!=='me') return true;
		$sub = array_filter(explode('__SEP__',$arg[1]));
		$me = CRM_ContactsCommon::get_my_record();
		return in_array($me['id'],$sub);
	}

	public static function check_date_and_time($data) {
		$ret = array();
		if (!$data['duration_switch']) {
			$start = recalculate_time('',$data['time']['__date']);
			$end = recalculate_time('',$data['end_time']['__date']);
			if ($end<$start) $ret['end_time'] = __('Invalid end time');
		}
		if ($data['recurrence_type']==8) {
			$missing = true;
			foreach (array(0=>'Mon',1=>'Tue',2=>'Wed',3=>'Thu',4=>'Fri',5=>'Sat',6=>'Sun') as $k=>$v) {
				if (isset($data['recurrence_hash_'.$k]) && $data['recurrence_hash_'.$k])
					$missing=false;
			}
			if ($missing) $ret['recurrence_hash'] = __('You must select at least one day');
		}
		return $ret;
	}

	public static function QFfield_recurrence(&$form, $field, $label, $mode, $default, $desc) {
		eval_js('recurrence_type_switch = function(arg){'.
			'if (arg==0) mode="none";'.
			'else mode="";'.
			'$("recurrence_end_date_row").style.display=mode;'.
			'if (arg!=8) mode="none";'.
			'else mode="";'.
			'$("recurrence_hash_row").style.display=mode;'.
		'}');
		$options = array(
			''=>__('No'),
			1=>__('Everyday'),
			2=>__('Every second day'),
			3=>__('Every third day'),
			4=>__('Every fourth day'),
			5=>__('Every fifth day'),
			6=>__('Every sixth day'),
			7=>__('Once every week'),
			8=>__('Customize week'),
			9=>__('Every two weeks'),
			10=>__('Every month'),
			11=>__('Every year')
			);
		if ($mode=='add' || $mode=='edit') {
			eval_js('recurrence_type_switch($("recurrence_type").value);');
			$form->addElement('select', $field, __('Recurring Event'), $options, array('id'=>$field, 'onchange'=>'recurrence_type_switch(this.value);'));
			if ($mode=='edit') $form->setDefaults(array($field=>$default));
		} else {
			eval_js('recurrence_type_switch('.($default?$default:'0').');');
			$form->addElement('static', $field, __('Recurring Event'), $options[$default]);
		}
	}

	public static function QFfield_recurrence_end(&$form, $field, $label, $mode, $default, $desc) {
		if ($mode=='add' || $mode=='edit') {
			$form->addElement('datepicker', $field, __('Recurrence End Date'), array('id'=>$field));
			eval_js('recurrence_end_switch = function(arg){'.
				'reds = $("recurrence_end");'.
				'if (arg) reds.disabled="";'.
				'else {'.
					'reds.disabled="1";'.
					'$("recurrence_end").value="";'.
				'}'.
			'}');
			$form->addElement('checkbox', 'recurrence_end_checkbox', __('Recurrence end'), null, array('id'=>'recurrence_end_checkbox','onclick'=>'recurrence_end_switch(this.checked);'));
			eval_js('recurrence_end_switch('.($default?'1':'0').');');
			if ($mode=='edit') {
				$form->setDefaults(array($field=>$default));
				$form->setDefaults(array('recurrence_end_checkbox'=>($default?'1':'0')));
			}
		} else {
			if (!$default) 
				$form->addElement('checkbox', $field, __('Recurrence End Date'));
			else {
				$form->addElement('datepicker', $field, __('Recurrence End Date'));
				$form->setDefaults(array($field=>$default));
			}
			if (Utils_RecordBrowser::$last_record['recurrence_type']>0) {
				$form->addElement('datepicker', 'recurrence_start_date', __('Recurrence Start Date'));
				$form->setDefaults(array('recurrence_start_date'=>Utils_RecordBrowser::$last_record['date']));
			}
		}
	}

	public static function QFfield_recurrence_hash(&$form, $field, $label, $mode, $default, $desc) {
		foreach (array(0=>__('Mon'),1=>__('Tue'),2=>__('Wed'),3=>__('Thu'),4=>__('Fri'),5=>__('Sat'),6=>__('Sun')) as $k=>$v) {
			$form->addElement('checkbox', 'recurrence_hash_'.$k, $v, null, array('id'=>'recurrence_hash_'.$k));
			if (isset($default[$k]) && $default[$k]) $form->setDefaults(array('recurrence_hash_'.$k=>1));
		}
		if ($mode=='add' || $mode=='edit') {
			$form->addElement('text', $field, __('Selected days'), array('id'=>$field));
		} else {
			$form->addElement('static', $field, __('Selected days'), $default);
		}
	}

	public static function menu() {
		if (Utils_RecordBrowserCommon::get_access('crm_meeting','browse'))
			return array(_M('CRM')=>array('__submenu__'=>1,_M('Meetings')=>array()));
		else
			return array();
	}

	public static function get_meetings($crits = array(), $cols = array(), $order = array()) {
		return Utils_RecordBrowserCommon::get_records('crm_meeting', $crits, $cols, $order);
	}

	public static function get_meeting($id) {
		return Utils_RecordBrowserCommon::get_record('crm_meeting', $id);
	}

	public static function applet_settings() {
		return Utils_RecordBrowserCommon::applet_settings(array(
			array('label'=>__('Display closed meetings'),'name'=>'closed','type'=>'checkbox','default'=>false),
			array('label'=>__('Related'),'name'=>'related','type'=>'select','values'=>array(__('Employee'),__('Customer'),__('Both')),'default'=>'0')
			));
	}
	
	public static function employees_crits(){
		return array('(company_name'=>CRM_ContactsCommon::get_main_company(),'|related_companies'=>array(CRM_ContactsCommon::get_main_company()));
	}
	public static function customers_crits($arg){
		if (!$arg) return array('(:Fav'=>true, '|:Recent'=>true);
		else return array();
	}
	public static function display_employees($record, $nolink, $desc) {
		return CRM_ContactsCommon::display_contacts_with_notification('crm_meeting', $record, $nolink, $desc);
	}
    public static function display_title($record, $nolink=false) {
		$ret = Utils_RecordBrowserCommon::create_linked_label_r('crm_meeting', 'Title', $record, $nolink);
		if (isset($record['description']) && $record['description']!='') $ret = '<span '.Utils_TooltipCommon::open_tag_attrs(Utils_RecordBrowserCommon::format_long_text($record['description']), false).'>'.$ret.'</span>';
		return $ret;
	}
    public static function display_title_with_mark($record) {
		$ret = self::display_title($record, false);
		return $ret;
	}
    public static function display_date($record) {
        $date = $record['date'];
        $convert_tz = false;
        if (isset($record['time']) && $record['time']) {
            $date .= ' ' . date('H:i:s', strtotime($record['time']));
            $convert_tz = true;
        }
        return Base_RegionalSettingsCommon::time2reg($date, false, true, $convert_tz);
    }
	public static function get_status_change_leightbox_href($record, $nolink, $desc) {
	    if($nolink) return false;
		$prefix = 'crm_meeting_leightbox';
		CRM_FollowupCommon::drawLeightbox($prefix);

		$v = $record[$desc['id']];
		if (!$v) $v = 0;
		$status = Utils_CommonDataCommon::get_translated_array('CRM/Status');
		if (!Utils_RecordBrowserCommon::get_access('crm_meeting','edit', $record) && !Base_AclCommon::i_am_admin()) return false;
		if ($v>=2) return false;
		if (isset($_REQUEST['form_name']) && $_REQUEST['form_name']==$prefix.'_follow_up_form' && $_REQUEST['id']==$record['id']) {
			unset($_REQUEST['form_name']);
			$v = $_REQUEST['closecancel'];
			$action  = $_REQUEST['action'];

			$note = $_REQUEST['note'];
			if ($note) {
				if (get_magic_quotes_gpc())
					$note = stripslashes($note);
				$note = str_replace("\n",'<br />',$note);
				Utils_AttachmentCommon::add('crm_meeting/'.$record['id'],0,Acl::get_user(),$note);
			}

			if ($action == 'set_in_progress') $v = 1;
			Utils_RecordBrowserCommon::update_record('crm_meeting', $record['id'], array('status'=>$v));
			if ($action == 'set_in_progress') location(array());

			$values = $record;
			$values['date_and_time'] = date('Y-m-d H:i:s');
			$values['title'] = __('Follow-up').': '.$values['title'];
			$values['status'] = 0;

			if ($action != 'none') {		
				$x = ModuleManager::get_instance('/Base_Box|0');
				$values['follow_up'] = array('meeting',$record['id'],$record['title']);
				if ($action == 'new_meeting') $x->push_main(Utils_RecordBrowser::module_name(),'view_entry',array('add', null, $values), array('crm_meeting'));
				if ($action == 'new_task') $x->push_main(Utils_RecordBrowser::module_name(),'view_entry',array('add', null, array('title'=>$values['title'],'permission'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'deadline'=>date('Y-m-d H:i:s', strtotime('+1 day')),'employees'=>$values['employees'], 'customers'=>$values['customers'],'status'=>0,'follow_up'=>$values['follow_up'])), array('task'));
				if ($action == 'new_phonecall') $x->push_main(Utils_RecordBrowser::module_name(),'view_entry',array('add', null, array('subject'=>$values['title'],'permission'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'date_and_time'=>date('Y-m-d H:i:s'),'employees'=>$values['employees'],'status'=>0, 'customer'=>!empty($values['customers'])?array_pop($values['customers']):'','follow_up'=>$values['follow_up'])), array('phonecall'));
				return false;
			}

			location(array());
		}
		if ($v==0) {
			return ' href="javascript:void(0)" onclick="'.$prefix.'_set_action(\'set_in_progress\');'.$prefix.'_set_id(\''.$record['id'].'\');'.$prefix.'_submit_form();"';
		}
		return ' href="javascript:void(0)" class="lbOn" rel="'.$prefix.'_followups_leightbox" onMouseDown="'.$prefix.'_set_id('.$record['id'].');"';
	}
	
	public static function display_status($record, $nolink, $desc) {
		$v = $record[$desc['id']];
		if (!$v) $v = 0;
		$status = Utils_CommonDataCommon::get_translated_array('CRM/Status');
		$href = self::get_status_change_leightbox_href($record, $nolink, $desc);
		$ret = $status[$v];
		if ($href!==false) $ret = '<a '.$href.'>'.$ret.'</a>';
		return $ret;
	}
	public static function subscribed_employees($v) {
		if (!is_array($v)) return;
		foreach ($v['employees'] as $k) {
			$user = Utils_RecordBrowserCommon::get_value('contact',$k,'Login');
			if ($user!==false && $user!==null && is_numeric($user) && $user>0) Utils_WatchdogCommon::user_subscribe($user, 'crm_meeting', $v['id']);
		}
	}

	public static function submit_meeting($values, $mode) {
		$me = CRM_ContactsCommon::get_my_record();
		switch ($mode) {
		case 'delete':
			Utils_MessengerCommon::delete_by_id('CRM_Calendar_Event:'.$values['id']);
			break;
		case 'display':
			$pdf = Utils_RecordBrowser::$rb_obj->pack_module(Libs_TCPDF::module_name(), 'L');
			if ($pdf->prepare()) {
				$pdf->set_title($values['title']);
				$pdf->set_subject('');
				$pdf->prepare_header();
				$pdf->AddPage();
				$v = CRM_Calendar_EventCommon::get(DB::GetOne('SELECT id FROM crm_calendar_custom_events_handlers WHERE group_name=%s', array('Meetings')).'#'.$values['id']);
				$ev_mod = Utils_RecordBrowser::$rb_obj->init_module(CRM_Calendar_Event::module_name());
				$ev_mod->make_event_PDF($pdf,$v,true,'view');
			}
			$pdf->add_actionbar_icon('Print');

			if (isset($_REQUEST['day'])) $values['date'] = $_REQUEST['day'];
			$ret = array();
            if ($values['time']) {
                // normal event
                $start = $values['time']; // time in unix timestamp UTC
                $start_disp = strtotime(Base_RegionalSettingsCommon::time2reg($start,true,true,true,false));
            } else {
                // when event is timeless - all day event
                $time = $values['date'].' 00:00:01';
			    $start = Base_RegionalSettingsCommon::reg2time($time);
                $start_disp = strtotime($time);
            }
			$end = strtotime('+'.$values['duration'].' seconds', $start);
			$ret['day_details'] = array('start'=>array(
				'day'=>'<a '.Base_BoxCommon::create_href(null, CRM_Calendar::module_name(), 'body', array(array('default_view'=>'day', 'default_date'=>strtotime($values['date']))), array()).'>'.date('j', $start_disp).'</a>',
				'month'=>'<a '.Base_BoxCommon::create_href(null, CRM_Calendar::module_name(), 'body', array(array('default_view'=>'month', 'default_date'=>strtotime($values['date']))), array()).'>'.__date('F', $start_disp).'</a>',
				'year'=>'<a '.Base_BoxCommon::create_href(null, CRM_Calendar::module_name(), 'body', array(array('default_view'=>'year', 'default_date'=>strtotime($values['date']))), array()).'>'.date('Y', $start_disp).'</a>',
				'weekday'=>'<a '.Base_BoxCommon::create_href(null, CRM_Calendar::module_name(), 'body', array(array('default_view'=>'week', 'default_date'=>strtotime($values['date']))), array()).'>'.__date('l', $start_disp).'</a>'
			));
			if (!isset($values['timeless']) || !$values['timeless'])
				$ret['event_info'] = array('start_time'=>Base_RegionalSettingsCommon::time2reg($start,2,false), 'end_time'=>Base_RegionalSettingsCommon::time2reg($end,2,false), 'duration'=>Base_RegionalSettingsCommon::seconds_to_words($values['duration']), 'start_date'=>'-', 'end_date'=>'-');
			$ret['form_data']['timeless'] = array('label'=>__('Timeless'), 'html'=>'value');
			$ret['toggle_duration'] = 'tog';
			$ret['duration_block_id'] = '1';
			$ret['event_end_block_id'] = '2';

			$values['title'] = __('Follow-up').': '.$values['title'];
			$values['status'] = 0;
			$cus = reset($values['customers']);
			if (CRM_MeetingInstall::is_installed()) $ret['new']['event'] = '<a '.Utils_TooltipCommon::open_tag_attrs(__('New Meeting')).' '.Utils_RecordBrowserCommon::create_new_record_href('crm_meeting', array('title'=>$values['title'],'permission'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'date'=>date('Y-m-d'),'time'=>date('H:i:s'),'duration'=>3600,'employees'=>$values['employees'], 'customers'=>$values['customers'],'status'=>0), 'none', false).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Calendar','icon-small.png').'" /></a>';
			if (CRM_TasksInstall::is_installed()) $ret['new']['task'] = '<a '.Utils_TooltipCommon::open_tag_attrs(__('New Task')).' '.Utils_RecordBrowserCommon::create_new_record_href('task', array('title'=>$values['title'],'permission'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'employees'=>$values['employees'], 'customers'=>$values['customers'],'status'=>0,'deadline'=>date('Y-m-d', strtotime('+1 day')))).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Tasks','icon-small.png').'"></a>';
			if (CRM_PhoneCallInstall::is_installed()) $ret['new']['phonecall'] = '<a '.Utils_TooltipCommon::open_tag_attrs(__('New Phonecall')).' '.Utils_RecordBrowserCommon::create_new_record_href('phonecall', array('subject'=>$values['title'],'permission'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'date_and_time'=>date('Y-m-d H:i:s'),'employees'=>$values['employees'], 'customer'=>$cus,'status'=>0), 'none', false).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_PhoneCall','icon-small.png').'" /></a>';
			$ret['new']['note'] = Utils_RecordBrowser::$rb_obj->add_note_button('crm_meeting/'.$values['id']);
			return $ret;
		case 'edit':
			self::subscribed_employees($values);
			$alarms = Utils_MessengerCommon::get_alarms('CRM_Calendar_Event:'.$values['id']);
			$old = Utils_RecordBrowserCommon::get_record('crm_meeting', $values['id']);
			$old_time = strtotime($old['date'].' '.date('H:i:s', strtotime($old['time'])));
			$new_time = strtotime($values['date'].' '.date('H:i:s', strtotime($values['time'])));
			foreach ($alarms as $id=>$time) {
				$time = strtotime($time);
				$diff = $old_time - $time;
				Utils_MessengerCommon::update_time($id, $new_time - $diff);
			}
		case 'add':
			if (isset($values['duration_switch']) && !$values['duration_switch']) {
				$values['duration'] = strtotime($values['end_time']) - strtotime($values['time']);
				if ($values['duration']<0) $values['duration'] += 60*60*24; // failsafe
			}
			if (isset($values['timeless']) && $values['timeless'])
				$values['duration'] = -1;
			$new = '';
			foreach (array(0=>'Mon',1=>'Tue',2=>'Wed',3=>'Thu',4=>'Fri',5=>'Sat',6=>'Sun') as $k=>$v) {
				if (isset($values['recurrence_hash_'.$k]) && $values['recurrence_hash_'.$k])
					$new .= '1';
				else
					$new .= '0';
			}
			if ($new!='0000000') $values['recurrence_hash'] = $new;
			if ($values['duration']!=-1) {
				if (isset($values['modded'])) {
					$time = Base_RegionalSettingsCommon::time2reg($values['time'],true,true,true,false);
					$reg_timestamp = $values['date'].' '.date('H:i:s', strtotime($time));
					$timestamp = Base_RegionalSettingsCommon::reg2time($reg_timestamp);
					$values['date'] = date('Y-m-d',$timestamp);
					$values['time'] = date('Y-m-d H:i:s',$timestamp);
					if (isset($values['recurrence_end']) && $values['recurrence_end']) {
						$values['recurrence_end'] = date('Y-m-d',Base_RegionalSettingsCommon::reg2time($values['recurrence_end'].' '.date('H:i:s', strtotime($time))));
						if ($values['recurrence_end']<$values['date']) $values['recurrence_end'] = $values['date'];
						if ($values['recurrence_type']==8) {
							$date =  date('Y-m-d', strtotime('+6 days', strtotime($values['date'])));
							if ($values['recurrence_end']<$date) $values['recurrence_end'] = $date;
						}
					}
				}
			} else {
				$values['time'] = '';
			}
			
			break;
		case 'adding':
			$values['permission'] = Base_User_SettingsCommon::get('CRM_Common','default_record_permission');
		case 'editing':
		case 'view':
			$values['modded'] = 1;
			if (!isset($values['date'])) $values['date'] = date('Y-m-d');
			if (!isset($values['time'])) $values['time'] = time();
			if (!isset($values['duration'])) $values['duration'] = 3600;
			if (!is_numeric($values['time'])) $values['time'] = strtotime($values['time']);
			if ($values['duration']!=-1) {
				if (isset($values['date']) && $values['date']) {
					$values['date'] = Base_RegionalSettingsCommon::time2reg($values['date'].' '.date('H:i:s', $values['time']),false,true,true,false);
					$values['time'] = Base_RegionalSettingsCommon::time2reg($values['date'].' '.date('H:i:s', $values['time']),true,false,true,false);
					$values['time'] = Base_RegionalSettingsCommon::reg2time($values['date'].' '.$values['time']);
				}
				if (isset($values['recurrence_end']) && $values['recurrence_end']) {
					$values['recurrence_end'] = Base_RegionalSettingsCommon::time2reg($values['recurrence_end'].' '.date('H:i:s', $values['time']),false,true,true,false);
				}
			}
			break;
		case 'added':
			if (isset($values['follow_up']))
				CRM_FollowupCommon::add_tracing_notes($values['follow_up'][0], $values['follow_up'][1], $values['follow_up'][2], 'meeting', $values['id'], $values['title']);
			self::subscribed_employees($values);
			$related = array_merge($values['employees'],$values['customers']);
			foreach ($related as $v) {
				list ($t, $id) = CRM_ContactsCommon::decode_record_token($v);
				$subs = Utils_WatchdogCommon::get_subscribers($t,$id);
				foreach($subs as $s)
					Utils_WatchdogCommon::user_subscribe($s, 'crm_meeting',$values['id']);
			}

			if(isset($values['messenger_on']) && $values['messenger_on']!='none') {
				$start = strtotime($values['date'].' '.date('H:i:s',strtotime($values['time'])));
				if($values['messenger_on']=='me')
					Utils_MessengerCommon::add('CRM_Calendar_Event:'.$values['id'],'CRM_Meeting',$values['messenger_message'],$start-$values['messenger_before'], array('CRM_MeetingCommon','get_alarm'),array($values['id']));
				else {
					$eee = array();
					foreach($values['employees'] as $v) {
						$c = CRM_ContactsCommon::get_contact($v);
						if(isset($c['login']))
							$eee[] = $c['login'];
					}
					Utils_MessengerCommon::add('CRM_Calendar_Event:'.$values['id'],'CRM_Meeting',$values['messenger_message'],$start-$values['messenger_before'], array('CRM_MeetingCommon','get_alarm'),array($values['id']),$eee);
				}
			}
			break;
		}
		return $values;
	}
	public static function watchdog_label($rid = null, $events = array(), $details = true) {
		return Utils_RecordBrowserCommon::watchdog_label(
				'crm_meeting',
				__('Meeting'),
				$rid,
				$events,
				'title',
				$details
			);
	}
	
	public static function crm_event_update($id, $start, $duration, $timeless) {
		$id = explode('_', $id);
		$id = reset($id);
		$r = Utils_RecordBrowserCommon::get_record('crm_meeting', $id);
		if (!Utils_RecordBrowserCommon::get_access('crm_meeting', 'edit', $r)) return false;
		$sp_start = explode(' ', date('Y-m-d H:i:s', $start));
		$values = array();
		$values['date'] = $sp_start[0];
		$values['time'] = '1970-01-01 '.$sp_start[1];
		if ($timeless) {
			$values['duration'] = -1;
			unset($values['time']);
		} else
			$values['duration'] = ($duration>0)?$duration:3600;
		$r = self::submit_meeting($r, 'editing');
		$values = self::submit_meeting($values, 'editing');
		$values['recurrence_end'] = $r['recurrence_end'];
		$values = Utils_RecordBrowserCommon::update_record('crm_meeting', $id, $values);
		if ($r['recurrence_type']>0) 
			print('Epesi.updateIndicatorText("Updating calendar");Epesi.request("");');
		return true;
	}

	public static function crm_event_delete($id) {
		$id = explode('_', $id);
		$id = reset($id);
		if (!Utils_RecordBrowserCommon::get_access('crm_meeting','delete', self::get_meeting($id))) return false;
		Utils_RecordBrowserCommon::delete_record('crm_meeting',$id);
		$r = Utils_RecordBrowserCommon::get_record('crm_meeting', $id);
		if ($r['recurrence_type']>0) 
			print('Epesi.updateIndicatorText("Updating calendar");Epesi.request("");');
		return true;
	}

	public static function crm_event_get($id, $day = null) {
		if (!is_array($id)) {
			$id = explode('_', $id);
			if (isset($id[1]) && $day===null) $day = $id[1];
			$id = reset($id);
			$r = Utils_RecordBrowserCommon::get_record('crm_meeting', $id);
		} else {
			$r = $id;
			$id = $r['id'];
		}
        $r = Utils_RecordBrowserCommon::filter_record_by_access('crm_meeting', $r);
        if ($r === false) {
            return null;
        }

		$next = array('type'=>__('Meeting'));

//		if ($r['duration']!=-1) {
//			$r['date'] = Base_RegionalSettingsCommon::time2reg($r['date'].' '.date('H:i:s', strtotime($r['time'])),false,true,true,false);
//			$r['recurrence_end'] = Base_RegionalSettingsCommon::time2reg($r['recurrence_end'].' '.date('H:i:s', strtotime($r['time'])),false,true,true,false);
//		}
		if ($day===null) {
			$day = $r['date'];
			$iday = strtotime($day);
			$next['id'] = $r['id'];
		} else {
			$iday = strtotime($day);
			if ($day<$r['date']) return null;
			if ($r['recurrence_end'] && $day>$r['recurrence_end']) return null;
			if ($r['recurrence_type']<=7 && $r['recurrence_type']>0) {
				$diff = round(($iday-strtotime($r['date']))/(3600*24));
				if ($diff<0 || $diff%$r['recurrence_type']!=0) return null;
			}
			if ($r['recurrence_type']==8) {
				if (isset($r['recurrence_hash'][date('N',$iday)-1]) && !$r['recurrence_hash'][date('N',$iday)-1]) return null;
			}
			if ($r['recurrence_type']==9) {
				$diff = round(($iday-strtotime($r['date']))/(3600*24));
				if ($diff<0 || $diff%14!=0) return null;
			}
			if ($r['recurrence_type']==10) {
				$numdays = date('t', $iday);
				$cday = date('d', $iday);
				$tday = date('d', strtotime($r['date']));
				if ($cday!=$tday && ($tday<=$numdays || $numdays!=$cday)) return null;
			}
			if ($r['recurrence_type']==11) {
				$cmonth = date('m', $iday);
				$tmonth = date('m', strtotime($r['date']));
				if ($cmonth!=$tmonth) return null;
				$numdays = date('t', $iday);
				$cday = date('d', $iday);
				$tday = date('d', strtotime($r['date']));
				if ($cday!=$tday && ($tday<=$numdays || $numdays!=$cday)) return null;
			}
			$next['id'] = $r['id'];
		}
		if ($r['recurrence_type']>0)
			$next['id'] = $r['id'].'_'.$day;

		$base_unix_time = strtotime(date('1970-01-01 00:00:00'));
//		$next['start'] = Base_RegionalSettingsCommon::reg2time(Base_RegionalSettingsCommon::time2reg(date('Y-m-d',$iday).' '.date('H:i:s',strtotime($r['time'])), true, false, true, false));
//		$next['end'] = Base_RegionalSettingsCommon::reg2time(date('Y-m-d',$iday).' '.Base_RegionalSettingsCommon::time2reg(date('Y-m-d',$iday).' '.date('H:i:s',strtotime($r['time'])+$r['duration']), true, false, true, false));
		$next['start'] = date('Y-m-d',$iday).' '.date('H:i:s',strtotime($r['time']));
		$next['end'] = date('Y-m-d',$iday).' '.date('H:i:s',strtotime($r['time'])+$r['duration']);
		$next['start'] = strtotime($next['start']);
		$next['end'] = strtotime($next['end']);

		if ($r['duration']==-1) $next['timeless'] = $day;
		$next['duration'] = intval($r['duration']);
		$next['title'] = (string)$r['title'];
		$next['description'] = (string)$r['description'];
		$next['color'] = 'gray';
		if ($r['status']==0 || $r['status']==1)
			switch ($r['priority']) {
				case 0: $next['color'] = 'green'; break;
				case 1: $next['color'] = 'yellow'; break;
				case 2: $next['color'] = 'red'; break;
			}
		if ($r['status']==2)
			$next['color'] = 'blue';
		if ($r['status']==3)
			$next['color'] = 'gray';

		if($r['recurrence_type'])
			$next['title'] = '<img src="'.Base_ThemeCommon::get_template_file('CRM_Calendar_Event','recurrence.png').'" border=0 hspace=0 vspace=0 align=left>'.$next['title'];

		$next['view_action'] = Utils_RecordBrowserCommon::create_record_href('crm_meeting', $r['id'], 'view', array('day'=>$day));

		if (Utils_RecordBrowserCommon::get_access('crm_meeting','edit', $r)!==false) {
			$next['edit_action'] = Utils_RecordBrowserCommon::create_record_href('crm_meeting', $r['id'], 'edit');
			if ($r['status']<=1) {
				$r_new = $r;
				if ($r['status']==0) $r_new['status'] = 1;
				$next['actions'] = array(array('icon'=>Base_ThemeCommon::get_template_file(CRM_Meeting::module_name(), 'close_event.png'), 'href'=>self::get_status_change_leightbox_href($r_new, false, array('id'=>'status'))));
			}
		} else {
			$next['edit_action'] = false;
			$next['move_action'] = false;
		}
		if (Utils_RecordBrowserCommon::get_access('crm_meeting','delete', $r)==false)
			$next['delete_action'] = false;

        $start_time = Base_RegionalSettingsCommon::time2reg($next['start'],2,false,$r['duration']!=-1);
        $event_date = Base_RegionalSettingsCommon::time2reg($next['start'],false,3,$r['duration']!=-1);
        $end_time = Base_RegionalSettingsCommon::time2reg($next['end'],2,false,$r['duration']!=-1);

        $inf2 = array(
            __('Date')=>'<b>'.$event_date.'</b>');

		if ($r['duration']==-1) {
			$inf2 += array(__('Time')=>__('Timeless event'));
		} else {
			$inf2 += array(
				__('Time')=>$start_time.' - '.$end_time,
				__('Duration')=>Base_RegionalSettingsCommon::seconds_to_words($r['duration'])
				);
			}



		$emps = array();
		foreach ($r['employees'] as $e) {
			$e = CRM_ContactsCommon::contact_format_no_company($e, true);
			$e = str_replace('&nbsp;',' ',$e);
			if (mb_strlen($e,'UTF-8')>33) $e = mb_substr($e , 0, 30, 'UTF-8').'...';
			$emps[] = $e;
		}
		$next['busy_label'] = $r['employees'];
		
		$cuss = array();
		foreach ($r['customers'] as $c) {
			$c = CRM_ContactsCommon::display_company_contact(array('customers'=>$c), true, array('id'=>'customers'));
            $cuss[] = str_replace('&nbsp;',' ',$c);
		}

		$inf2 += array(	__('Event')=> '<b>'.$next['title'].'</b>',
						__('Description')=> $next['description'],
						__('Assigned to')=> implode('<br>',$emps),
						__('Contacts')=> implode('<br>',$cuss),
						__('Status')=> Utils_CommonDataCommon::get_value('CRM/Status/'.$r['status'],true),
						__('Access')=> Utils_CommonDataCommon::get_value('CRM/Access/'.$r['permission'],true),
						__('Priority')=> Utils_CommonDataCommon::get_value('CRM/Priority/'.$r['priority'],true),
						__('Notes')=> Utils_AttachmentCommon::count('crm_meeting/'.$r['id'])
					);

//		$next['employees'] = implode('<br>',$emps);
//		$next['customers'] = implode('<br>',$cuss);
		$next['employees'] = $r['employees'];
		$next['customers'] = $r['customers'];
		$next['status'] = $r['status']<=2?'active':'closed';
		$next['custom_tooltip'] = 
									'<center><b>'.
										__('Meeting').
									'</b></center><br>'.
									Utils_TooltipCommon::format_info_tooltip($inf2).'<hr>'.
									CRM_ContactsCommon::get_html_record_info($r['created_by'],$r['created_on'],null,null);
		return $next;
	}

	public static function crm_event_get_all($start, $end, $filter=null, $customers=null) {
		$start = date('Y-m-d',Base_RegionalSettingsCommon::reg2time($start));
		$crits = array();
		if ($filter===null) $filter = CRM_FiltersCommon::get();
		$f_array = explode(',',trim($filter,'()'));
		if($filter!='()' && $filter)
			$crits['('.'employees'] = $f_array;
		if ($customers && !empty($customers)) 
			$crits['|customers'] = $customers;
		elseif($filter!='()' && $filter) {
			$crits['|customers'] = $f_array;
			foreach ($crits['|customers'] as $k=>$v)
				$crits['|customers'][$k] = 'contact/'.$v;
		}
		$critsb = $crits;
		
		$crits['<=date'] = $end;
		$crits['>=date'] = $start;
		$crits['recurrence_type'] = '';
		
		$count = 0;
		$ret = Utils_RecordBrowserCommon::get_records('crm_meeting', $crits, array(), array('date' => 'DESC', 'time' => 'DESC'), CRM_CalendarCommon::$events_limit);

		$result = array();
		foreach ($ret as $r)
			$result[] = self::crm_event_get($r);

		$count += count($result);

		$crits = $critsb;

		$crits['<=date'] = $end;
		$crits['(>=recurrence_end'] = $start;
		$crits['|recurrence_end'] = '';
		$crits['!recurrence_type'] = '';
		$ret = Utils_RecordBrowserCommon::get_records('crm_meeting', $crits, array(), array(), CRM_CalendarCommon::$events_limit);
		
		$day = strtotime($start);
		$end = strtotime($end);
		while ($day<=$end) {
			foreach ($ret as $r) {
				$next = self::crm_event_get($r, date('Y-m-d', $day));
				if ($next) {
					$result[] = $next;
					$count++;
					if ($count==CRM_CalendarCommon::$events_limit) break;
				}
			}
			$day = strtotime('+1 day', $day);
		}

		return $result;
	}

    public static function search_format($id) {
        $row = Utils_RecordBrowserCommon::get_record('crm_meeting',$id);
        if(!$row) return false;
        return Utils_RecordBrowserCommon::record_link_open_tag('crm_meeting', $row['id']).__( 'Meeting (attachment) #%d, %s at %s', array($row['id'], $row['title'], Base_RegionalSettingsCommon::time2reg($row['date'], false))).Utils_RecordBrowserCommon::record_link_close_tag();
    }

	public static function get_alarm($id) {
		$a = self::get_meeting($id);

		if (!$a) return __('Private record');

		if($a['duration']<0)
			$date = __('Timeless event: %s',array(Base_RegionalSettingsCommon::time2reg($a['date'],false)));
		else
			$date = __('Date: %s',array(Base_RegionalSettingsCommon::time2reg($a['date'].' '.date('H:i:s',strtotime($a['time'])),2)));

		return $date."\n".__('Title: %s',array($a['title']));
	}

    public static function QFfield_recordset(&$form, $field, $label, $mode, $default) {
        if ($mode == 'add' || $mode == 'edit') {
            $rss = DB::GetCol('SELECT f_recordset FROM crm_meeting_related_data_1 WHERE active=1');
            // remove currently selected value
            $key = array_search($default, $rss);
            if ($key !== false) 
                unset($rss[$key]);
            $tabs = DB::GetAssoc('SELECT tab, caption FROM recordbrowser_table_properties WHERE tab not in (\'' . implode('\',\'', $rss) . '\') AND tab not like %s', array('%_related'));
            foreach ($tabs as $k => $v) {
                $tabs[$k] = _V($v) . " ($k)";
            }
            $form->addElement('select', $field, $label, $tabs, array('id' => $field));
            $form->addRule($field, 'Field required', 'required');
            if ($mode == 'edit') 
                $form->setDefaults(array($field => $default));
        } else {
            $form->addElement('static', $field, $label);
            $form->setDefaults(array($field => $default));
        }
    }

    public static function display_recordset($r, $nolink = false) {
        $caption = Utils_RecordBrowserCommon::get_caption($r['recordset']);
        return $caption . ' (' . $r['recordset'] . ')';
    }
    
    public static function QFfield_related(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
        if(DB::GetOne('SELECT 1 FROM crm_meeting_related_data_1 WHERE active=1'))
            Utils_RecordBrowserCommon::QFfield_select($form, $field, $label, $mode, $default, $desc, $rb_obj);
    }

    public static function related_crits() {
        $recordsets = DB::GetCol('SELECT f_recordset FROM crm_meeting_related_data_1 WHERE active=1');
        $crits = array(
            '' => array(),
        );
        foreach ($recordsets as $rec) 
            $crits[$rec] = array();
        return $crits;
    }

    public static function processing_related($values, $mode) {
        switch ($mode) {
            case 'edit':
            $rec = Utils_RecordBrowserCommon::get_record('crm_meeting_related', $values['id']);
            $rs = $rec['recordset'];
            self::delete_addon($rs);
            case 'add':
            $rs = $values['recordset'];
            self::new_addon($rs);
            break;

            case 'delete':
            $rs = $values['recordset'];
            self::delete_addon($rs);
            break;
        }
        return $values;
    }

    public static function new_addon($table) {
        Utils_RecordBrowserCommon::new_addon($table, CRM_Meeting::module_name(), 'addon', 'Meetings');
    }

    public static function delete_addon($table) {
        Utils_RecordBrowserCommon::delete_addon($table, CRM_Meeting::module_name(), 'addon');
    }

    public static function admin_caption() {
        return array('label' => __('Meetings'), 'section' => __('Features Configuration'));
    }

	///////////////////////////////////
	// mobile devices

	public static function mobile_menu() {
		if(!Utils_RecordBrowserCommon::get_access('crm_meeting','browse'))
			return array();
		return array(__('Meetings')=>array('func'=>'mobile_meetings','color'=>'blue'));
	}
	
	public static function mobile_meetings() {
		$me = CRM_ContactsCommon::get_my_record();
		$defaults = array('employees'=>array($me['id']),'status'=>0, 'permission'=>0, 'priority'=>CRM_CommonCommon::get_default_priority());
		Utils_RecordBrowserCommon::mobile_rb('crm_meeting',array('employees'=>array($me['id'])),array('date'=>'ASC', 'time'=>'ASC', 'priority'=>'DESC', 'title'=>'ASC'),array('date'=>1,'time'=>1,'priority'=>1,'longterm'=>1),$defaults);
	}
}

?>
