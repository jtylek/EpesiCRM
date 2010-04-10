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
			case 'new_event_types': $ret = array(array('label'=>'Meeting','icon'=>Base_ThemeCommon::get_template_file('CRM_Meeting','icon.png')));
							break;
			case 'new_event': $ret = call_user_func_array(array('CRM_MeetingCommon','crm_new_event'), $args);
							break;
			case 'view_event': $ret = call_user_func_array(array('CRM_MeetingCommon','crm_view_event'), $args);
							break;
			case 'edit_event': $ret = call_user_func_array(array('CRM_MeetingCommon','crm_edit_event'), $args);
							break;
		}
		return $ret;
	}
	
	public static function crm_new_event($timestamp, $timeless, $id, $cal_obj) {
		$rb = $cal_obj->init_module('Utils_RecordBrowser', 'crm_meeting');
		$me = CRM_ContactsCommon::get_my_record();
		$defaults = array('employees'=>$me['id'], 'priority'=>1, 'permission'=>0, 'status'=>0);
		$defaults['date'] = date('Y-m-d', $timestamp);
		$defaults['time'] = date('H:i:s', $timestamp);
		$defaults['duration'] = $timeless?-1:3600;
		$rb->view_entry('add', null, $defaults);
		return true;
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
		if(self::Instance()->acl_check('browse meetings'))
			return "Meetings";
	}

	public static function applet_info() {
		return "Meetings list";
	}

	public static function applet_info_format($r){
		// Build array representing 2-column tooltip
		// Format: array (Label,value)
		$access = Utils_CommonDataCommon::get_translated_array('CRM/Access');
		$priority = Utils_CommonDataCommon::get_translated_array('CRM/Priority');
		$status = Utils_CommonDataCommon::get_translated_array('CRM/Status');

		$args=array(
					'Meeting:'=>'<b>'.$r['title'].'</b>',
					'Description:'=>$r['description'],
					'Assigned to:'=>CRM_ContactsCommon::display_contact(array('id'=>$r['employees']),true,array('id'=>'id', 'param'=>'::;CRM_ContactsCommon::contact_format_no_company')),
					'Contacts:'=>CRM_ContactsCommon::display_contact(array('id'=>$r['customers']),true,array('id'=>'id', 'param'=>'::;CRM_ContactsCommon::contact_format_default')),
					'Status:'=>$status[$r['status']],
					'Date:'=>$r['duration']>=0?Base_RegionalSettingsCommon::time2reg($r['date'].' '.date('H:i:s',strtotime($r['time']))):Base_RegionalSettingsCommon::time2reg($r['date'],false),
					'Duration:'=>$r['duration']>=0?Base_RegionalSettingsCommon::seconds_to_words($r['duration']):'---',
					'Permission:'=>$access[$r['permission']],
					'Priority:'=>$priority[$r['priority']],
					);
		
		$bg_color = '';
		switch ($r['priority']) {
			case 0: $bg_color = '#FFFFFF'; break; // low priority
			case 1: $bg_color = '#FFFFD5'; break; // medium
			case 2: $bg_color = '#FFD5D5'; break; // high
		}

		// Pass 2 arguments: array containing pairs: label/value
		// and the name of the group for translation
		//return	Utils_TooltipCommon::format_info_tooltip($args,'CRM_Tasks');

		$ret = array('notes'=>Utils_TooltipCommon::format_info_tooltip($args,'CRM_Meeting'));
		if ($bg_color) $ret['row_attrs'] = 'style="background:'.$bg_color.';"';
		return $ret;
	}

	public static function QFfield_duration(&$form, $field, $label, $mode, $default, $desc) {
		if ($mode=='add' || $mode=='edit') {
			$dur = array(
				-1=>Base_LangCommon::ts('CRM_Meeting','---'),
				300=>Base_LangCommon::ts('CRM_Meeting','5 minutes'),
				900=>Base_LangCommon::ts('CRM_Meeting','15 minutes'),
				1800=>Base_LangCommon::ts('CRM_Meeting','30 minutes'),
				2700=>Base_LangCommon::ts('CRM_Meeting','45 minutes'),
				3600=>Base_LangCommon::ts('CRM_Meeting','1 hour'),
				7200=>Base_LangCommon::ts('CRM_Meeting','2 hours'),
				14400=>Base_LangCommon::ts('CRM_Meeting','4 hours'),
				28800=>Base_LangCommon::ts('CRM_Meeting','8 hours'));
			if (isset($dur[$default]))
				$duration_switch='1';
			else
				$duration_switch='0';
			$form->addElement('select', $field, $label, $dur, array('id'=>$field));
			$time_format = Base_RegionalSettingsCommon::time_12h()?'h:i a':'H:i';
			$lang_code = Base_LangCommon::get_lang_code();
			$form->addElement('timestamp', 'end_time', Base_LangCommon::ts('CRM_Meeting','End Time'), array('date'=>false, 'format'=>$time_format, 'optionIncrement'  => array('i' => 5),'language'=>$lang_code, 'id'=>'end_time'));

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
			$form->addElement('button', 'toggle', Base_LangCommon::ts('Utils_RecordBrowser','Toggle'), array('onclick'=>'crm_calendar_duration_switcher()', 'id'=>'toggle_duration_button', 'class'=>'button'));
			$form->addElement('checkbox', 'timeless', Base_LangCommon::ts('Utils_RecordBrowser','Timeless'), null, array('onClick'=>'crm_calendar_event_timeless(this.checked)', 'id'=>'timeless'));

			eval_js('crm_calendar_event_timeless($("timeless").checked)');
			eval_js('crm_calendar_duration_switcher(1)');

			$form->setDefaults(array('duration_switch'=>$duration_switch));
			$form->setDefaults(array($field=>$default));
			$form->setDefaults(array('timeless'=>($default==-1?1:0)));
			if (isset(Utils_RecordBrowser::$last_record['time']))
				$form->setDefaults(array('end_time'=>strtotime('+'.$default.' seconds', Utils_RecordBrowser::$last_record['time'])));

			$form->addFormRule(array('CRM_MeetingCommon','check_date_and_time'));
		} else {
			$form->addElement('checkbox', 'timeless', Base_LangCommon::ts('Utils_RecordBrowser','Timeless'));
			$form->setDefaults(array('timeless'=>($default==-1?1:0)));
		}

		//messanger
		if($mode == 'add') {
			eval_js_once('crm_calendar_event_messenger = function(v) {var mb=$("messenger_block");if(!mb)return;if(v)mb.show();else mb.hide();}');
			$form->addElement('select','messenger_before',Base_LangCommon::ts('CRM_Meeting','Popup alert'),array(0=>Base_LangCommon::ts('CRM_Meeting','on event start'), 900=>Base_LangCommon::ts('CRM_Meeting','15 minutes before event'), 1800=>Base_LangCommon::ts('CRM_Meeting','30 minutes before event'), 2700=>Base_LangCommon::ts('CRM_Meeting','45 minutes before event'), 3600=>Base_LangCommon::ts('CRM_Meeting','1 hour before event'), 2*3600=>Base_LangCommon::ts('CRM_Meeting','2 hours before event'), 3*3600=>Base_LangCommon::ts('CRM_Meeting','3 hours before event'), 4*3600=>Base_LangCommon::ts('CRM_Meeting','4 hours before event'), 8*3600=>Base_LangCommon::ts('CRM_Meeting','8 hours before event'), 12*3600=>Base_LangCommon::ts('CRM_Meeting','12 hours before event'), 24*3600=>Base_LangCommon::ts('CRM_Meeting','24 hours before event')));
			$form->addElement('textarea','messenger_message',Base_LangCommon::ts('CRM_Meeting','Popup message'), array('id'=>'messenger_message'));
			$form->addElement('select','messenger_on',Base_LangCommon::ts('CRM_Meeting','Alert'),array('none'=>Base_LangCommon::ts('CRM_Meeting','None'),'me'=>Base_LangCommon::ts('CRM_Meeting','me'),'all'=>Base_LangCommon::ts('CRM_Meeting','all selected employees')),array('onChange'=>'crm_calendar_event_messenger(this.value!="none");$("messenger_message").value=$("title").value;'));
//			$form->addElement('checkbox','messenger_on',Base_LangCommon::ts('CRM_Meeting','Alert me'),null,array('onClick'=>'crm_calendar_event_messenger(this.checked);$("messenger_message").value=$("event_title").value;'));
			eval_js('crm_calendar_event_messenger('.(($form->exportValue('messenger_on')!='none' && $form->exportValue('messenger_on')!='')?1:0).')');
			$form->registerRule('check_my_user', 'callback', array('CRM_MeetingCommon','check_my_user'));
			$form->addRule(array('messenger_on','emp_id'), Base_LangCommon::ts('CRM_Meeting','You have to select your contact to set alarm on it'), 'check_my_user');
		}
	}

	public function check_my_user($arg) {
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
			if ($end<$start) $ret['end_time'] = Base_LangCommon::ts('CRM_Meeting','Invalid end time');
		}
		if ($data['recurrence_type']==8) {
			$missing = true;
			foreach (array(0=>'Mon',1=>'Tue',2=>'Wed',3=>'Thu',4=>'Fri',5=>'Sat',6=>'Sun') as $k=>$v) {
				if (isset($data['recurrence_hash_'.$k]) && $data['recurrence_hash_'.$k])
					$missing=false;
			}
			if ($missing) $ret['recurrence_hash'] = Base_LangCommon::ts('CRM_Meeting','You must select at least one day');
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
			''=>'No',
			1=>Base_LangCommon::ts('CRM_Meeting','Everyday'),
			2=>Base_LangCommon::ts('CRM_Meeting','Every second day'),
			3=>Base_LangCommon::ts('CRM_Meeting','Every third day'),
			4=>Base_LangCommon::ts('CRM_Meeting','Every fourth day'),
			5=>Base_LangCommon::ts('CRM_Meeting','Every fifth day'),
			6=>Base_LangCommon::ts('CRM_Meeting','Every sixth day'),
			7=>Base_LangCommon::ts('CRM_Meeting','Once every week'),
			8=>Base_LangCommon::ts('CRM_Meeting','Customize week'),
			9=>Base_LangCommon::ts('CRM_Meeting','Every two weeks'),
			10=>Base_LangCommon::ts('CRM_Meeting','Every month'),
			11=>Base_LangCommon::ts('CRM_Meeting','Every year')
			);
		if ($mode=='add' || $mode=='edit') {
			eval_js('recurrence_type_switch($("recurrence_type").value);');
			$form->addElement('select', $field, Base_LangCommon::ts('Utils_RecordBrowser','Recurring Event'), $options, array('id'=>$field, 'onchange'=>'recurrence_type_switch(this.value);'));
			if ($mode=='edit') $form->setDefaults(array($field=>$default));
		} else {
			eval_js('recurrence_type_switch('.($default?$default:'0').');');
			$form->addElement('static', $field, Base_LangCommon::ts('Utils_RecordBrowser','Recurring Event'), $options[$default]);
		}
	}

	public static function QFfield_recurrence_end(&$form, $field, $label, $mode, $default, $desc) {
		if ($mode=='add' || $mode=='edit') {
			$form->addElement('datepicker', $field, Base_LangCommon::ts('Utils_RecordBrowser','Recurrence End Date'), array('id'=>$field));
			eval_js('recurrence_end_switch = function(arg){'.
				'reds = $("recurrence_end_date_span");'.
				'if (arg) reds.style.display="";'.
				'else {'.
					'reds.style.display="none";'.
					'$("recurrence_end").value="";'.
				'}'.
			'}');
			$form->addElement('checkbox', 'recurrence_end_checkbox', Base_LangCommon::ts('Utils_RecordBrowser','Recurrence End'), null, array('id'=>'recurrence_end_checkbox','onclick'=>'recurrence_end_switch(this.checked);'));
			eval_js('recurrence_end_switch('.($default?'1':'0').');');
			if ($mode=='edit') {
				$form->setDefaults(array($field=>$default));
				$form->setDefaults(array('recurrence_end_checkbox'=>($default?'1':'0')));
			}
		} else {
			if (!$default) 
				$form->addElement('checkbox', $field, Base_LangCommon::ts('Utils_RecordBrowser','Recurrence End Date'));
			else {
				$form->addElement('datepicker', $field, Base_LangCommon::ts('Utils_RecordBrowser','Recurrence End Date'));
				$form->setDefaults(array($field=>$default));
			}
			if (Utils_RecordBrowser::$last_record['recurrence_type']>0) {
				$form->addElement('datepicker', 'recurrence_start_date', Base_LangCommon::ts('Utils_RecordBrowser','Recurrence Start Date'));
				$form->setDefaults(array('recurrence_start_date'=>Utils_RecordBrowser::$last_record['date']));
			}
		}
	}

	public static function QFfield_recurrence_hash(&$form, $field, $label, $mode, $default, $desc) {
		foreach (array(0=>'Mon',1=>'Tue',2=>'Wed',3=>'Thu',4=>'Fri',5=>'Sat',6=>'Sun') as $k=>$v) {
			$form->addElement('checkbox', 'recurrence_hash_'.$k, Base_LangCommon::ts('Utils_RecordBrowser',$v), null, array('id'=>'recurrence_hash_'.$k));
			if (isset($default[$k]) && $default[$k]) $form->setDefaults(array('recurrence_hash_'.$k=>1));
		}
		if ($mode=='add' || $mode=='edit') {
			$form->addElement('text', $field, Base_LangCommon::ts('Utils_RecordBrowser','Selected days'), array('id'=>$field));
		} else {
			$form->addElement('static', $field, Base_LangCommon::ts('Utils_RecordBrowser','Selected days'), $default);
		}
	}

	public static function menu() {
		if(self::Instance()->acl_check('browse meetings'))
			return array('CRM'=>array('__submenu__'=>1,'Meetings'=>array()));
		else
			return array();
	}

	public static function get_meetings($crits = array(), $cols = array(), $order = array()) {
		return Utils_RecordBrowserCommon::get_records('crm_meeting', $crits, $cols, $order);
	}

	public static function get_meeting($id) {
		return Utils_RecordBrowserCommon::get_record('crm_meeting', $id);
	}

	public static function access_meeting($action, $param=null){
		$i = self::Instance();
		switch ($action) {
			case 'browse_crits':	$me = CRM_ContactsCommon::get_my_record();
									return array('(!permission'=>2, '|employees'=>$me['id']);
			case 'browse':	if (!$i->acl_check('browse meetings')) return false;
							return true;
			case 'view':	if (!$i->acl_check('view meeting')) return false;
							$me = CRM_ContactsCommon::get_my_record();
							return ($param['permission']!=2 || isset($param['employees'][$me['id']]));
			case 'clone':
			case 'add':		return $i->acl_check('edit meeting');
			case 'edit':	$me = CRM_ContactsCommon::get_my_record();
							if ($param['permission']>=1 &&
								!in_array($me['id'],$param['employees']) &&
								!in_array($me['id'],$param['customers'])) return false;
							if ($i->acl_check('edit meeting')) return true;
							return false;
			case 'delete':	if ($i->acl_check('delete meeting')) return true;
							$me = CRM_ContactsCommon::get_my_record();
							if ($me['login']==$param['created_by']) return true;
							return false;
		}
		return false;
	}

	public static function applet_settings() {
		return Utils_RecordBrowserCommon::applet_settings(array(
			array('label'=>'Display closed meetings','name'=>'closed','type'=>'checkbox','default'=>false),
			array('label'=>'Related','name'=>'related','type'=>'select','values'=>array('Employee','Customer','Both'),'default'=>'0')
			));
	}
	
	public static function employees_crits(){
		return array('company_name'=>array(CRM_ContactsCommon::get_main_company()));
	}
	public static function customers_crits($arg){
		if (!$arg) return array('(:Fav'=>true, '|:Recent'=>true);
		else return array();
	}
	public static function display_employees($record, $nolink, $desc) {
		$icon_on = Base_ThemeCommon::get_template_file('images/active_on.png');
		$icon_off = Base_ThemeCommon::get_template_file('images/active_off.png');
		$icon_none = Base_ThemeCommon::get_template_file('images/active_off2.png');
		$v = $record[$desc['id']];
		$def = '';
		$first = true;
		$param = explode(';',$desc['param']);
		if ($param[1] == '::') $callback = array('CRM_ContactsCommon', 'contact_format_default');
		else $callback = explode('::', $param[1]);
		if (!is_array($v)) $v = array($v);
		foreach($v as $k=>$w){
			if ($w=='') break;
			if ($first) $first = false;
			else $def .= '<br>';
			$contact = CRM_ContactsCommon::get_contact($w);
			if (!$nolink) {
				if ($contact['login']=='') $icon = $icon_none;
				else {
//					trigger_error(print_r($record,true));
					$icon = Utils_WatchdogCommon::user_check_if_notified($contact['login'],'crm_meeting',$record['id']);
					if ($icon===null) $icon = $icon_none;
					elseif ($icon===true) $icon = $icon_on;
					else $icon = $icon_off;
				}
				$def .= '<img src="'.$icon.'" />';
			}
			$def .= Utils_RecordBrowserCommon::no_wrap(call_user_func($callback, $contact, $nolink));
		}
		if (!$def) 	$def = '---';
		return $def;
	}
    public static function display_title($record, $nolink) {
		$ret = Utils_RecordBrowserCommon::create_linked_label_r('crm_meeting', 'Title', $record, $nolink);
		if (isset($record['description']) && $record['description']!='') $ret = '<span '.Utils_TooltipCommon::open_tag_attrs($record['description'], false).'>'.$ret.'</span>';
		return $ret;
	}
    public static function display_title_with_mark($record) {
		$me = CRM_ContactsCommon::get_my_record();
		$ret = self::display_title($record, false);
		if (!in_array($me['id'], $record['employees'])) return $ret;
		$notified = Utils_WatchdogCommon::check_if_notified('crm_meeting',$record['id']);
		if ($notified!==true && $notified!==null) $ret = '<img src="'.Base_ThemeCommon::get_template_file('CRM_Meeting','notice.png').'" />'.$ret;
		return $ret;
	}
	public static function get_status_change_leightbox_href($record, $nolink, $desc) {
		$prefix = 'crm_meeting_leightbox';
		CRM_FollowupCommon::drawLeightbox($prefix);

		$v = $record[$desc['id']];
		if (!$v) $v = 0;
		$status = Utils_CommonDataCommon::get_translated_array('CRM/Status');
		if (!self::access_meeting('edit', $record) && !Base_AclCommon::i_am_admin()) return false;
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
				Utils_AttachmentCommon::add('CRM/Calendar/Event/'.$record['id'],0,Acl::get_user(),$note);
			}

			if ($action == 'set_in_progress') $v = 1;
			Utils_RecordBrowserCommon::update_record('crm_meeting', $record['id'], array('status'=>$v));
			if ($action == 'set_in_progress') location(array());

			$values = $record;
			$values['date_and_time'] = date('Y-m-d H:i:s');
			$values['title'] = Base_LangCommon::ts('CRM/Meeting','Follow up: ').$values['title'];
			$values['status'] = 0;

			if ($action != 'none') {		
				$x = ModuleManager::get_instance('/Base_Box|0');
				if ($action == 'new_meeting') $x->push_main('Utils/RecordBrowser','view_entry',array('add', null, $values), array('crm_meeting'));
				if ($action == 'new_task') $x->push_main('Utils/RecordBrowser','view_entry',array('add', null, array('title'=>$values['title'],'permission'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'deadline'=>date('Y-m-d H:i:s', strtotime('+1 day')),'employees'=>$values['employees'], 'customers'=>$values['customers'],'status'=>0)), array('task'));
				if ($action == 'new_phonecall') $x->push_main('Utils/RecordBrowser','view_entry',array('add', null, array('subject'=>$values['title'],'permission'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'date_and_time'=>date('Y-m-d H:i:s'),'employees'=>$values['employees'],'status'=>0, 'customer'=>!empty($values['customers'])?array_pop($values['customers']):'')), array('phonecall'));
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
			if ($user!==false && $user!==null) Utils_WatchdogCommon::user_subscribe($user, 'crm_meeting', $v['id']);
		}
	}

	public static function submit_meeting($values, $mode) {
		$me = CRM_ContactsCommon::get_my_record();
		switch ($mode) {
		case 'display':
			if (isset($_REQUEST['day'])) $values['date'] = $_REQUEST['day'];
			$ret = array();
			$start = strtotime($values['date'].' '.date('H:i:s', strtotime($values['time'])));
			$start_disp = strtotime(Base_RegionalSettingsCommon::time2reg($start,true,true,true,false));
			$end = strtotime('+'.$values['duration'].' seconds', $start);
			$ret['day_details'] = array('start'=>array(
				'day'=>date('j', $start_disp), 
				'month'=>date('F', $start_disp), 
				'year'=>date('Y', $start_disp), 
				'weekday'=>date('l', $start_disp))
			);

			$ret['event_info'] = array('start_time'=>Base_RegionalSettingsCommon::time2reg($start,2,false), 'end_time'=>Base_RegionalSettingsCommon::time2reg($end,2,false), 'duration'=>Base_RegionalSettingsCommon::seconds_to_words($values['duration']), 'start_date'=>'-', 'end_date'=>'-');
			$ret['form_data']['timeless'] = array('label'=>'Timeless', 'html'=>'value');
			$ret['toggle_duration'] = 'tog';
			$ret['duration_block_id'] = '1';
			$ret['event_end_block_id'] = '2';

			$values['title'] = Base_LangCommon::ts('CRM_Meeting','Follow up: ').$values['title'];
			$values['status'] = 0;
			$cus = reset($values['customers']);
			if (ModuleManager::is_installed('CRM/Meeting')>=0) $ret['new_event'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM_Meeting','New Event')).' '.Utils_RecordBrowserCommon::create_new_record_href('crm_meeting', array('title'=>$values['title'],'permission'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'date'=>date('Y-m-d'),'time'=>date('H:i:s'),'duration'=>3600,'employees'=>$values['employees'], 'customers'=>$values['customers'],'status'=>0), 'none', false).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Calendar','icon-small.png').'" /></a>';
			if (ModuleManager::is_installed('CRM/Tasks')>=0) $ret['new_task'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM_Tasks','New Task')).' '.Utils_RecordBrowserCommon::create_new_record_href('task', array('title'=>$values['title'],'permission'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'employees'=>$values['employees'], 'customers'=>$values['customers'],'status'=>0,'deadline'=>date('Y-m-d', strtotime('+1 day')))).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Tasks','icon-small.png').'"></a>';
			if (ModuleManager::is_installed('CRM/PhoneCall')>=0) $ret['new_phonecall'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM_PhoneCall','New Phonecall')).' '.Utils_RecordBrowserCommon::create_new_record_href('phonecall', array('subject'=>$values['title'],'permission'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'date_and_time'=>date('Y-m-d H:i:s'),'employees'=>$values['employees'], 'customer'=>$cus,'status'=>0), 'none', false).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_PhoneCall','icon-small.png').'" /></a>';
			return $ret;
		case 'add':
		case 'edit':
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
					$values['time'] = date('1970-01-01 H:i:s',$timestamp);
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
		case 'editing':
		case 'adding':
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
			self::subscribed_employees($values);
			$related = array_merge($values['employees'],$values['customers']);
			foreach ($related as $v) {
				if ($mode==='edit' && in_array($v, $old_related)) continue;
				$subs = Utils_WatchdogCommon::get_subscribers('contact',$v);
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
				Base_LangCommon::ts('CRM_Meeting','Meeting'),
				$rid,
				$events,
				'title',
				$details
			);
	}
	
	public static function get_available_colors() {
		static $color = array(0 => '', 1 => 'green', 2 => 'yellow', 3 => 'red', 4 => 'blue', 5=> 'gray', 6 => 'cyan', 7 =>'magenta');
		$color[0] = $color[Base_User_SettingsCommon::get('CRM_Calendar','default_color')];
		return $color;
	}
	
	public static function crm_event_update($id, $start, $duration, $timeless) {
		$id = explode('_', $id);
		$id = reset($id);
		$r = Utils_RecordBrowserCommon::get_record('crm_meeting', $id);
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

		$next = array();
		
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
		$next['edit_action'] = Utils_RecordBrowserCommon::create_record_href('crm_meeting', $r['id'], 'edit');
//		$next['delete_action'] = Module::create_confirm_href(Base_LangCommon::ts('Premium_SchoolRegister','Are you sure you want to delete this '.$type.'?'),array('delete_'.$type=>$record['id']));

		$r_new = $r;
		if ($r['status']==0) $r_new['status'] = 1;
		if ($r['status']<=1) $next['actions'] = array(
			array('icon'=>Base_ThemeCommon::get_template_file('CRM/Meeting', 'close_event.png'), 'href'=>self::get_status_change_leightbox_href($r_new, false, array('id'=>'status')))
		);

        $start_time = Base_RegionalSettingsCommon::time2reg($next['start'],2,false,$r['duration']!=-1);
        $event_date = Base_RegionalSettingsCommon::time2reg($next['start'],false,3,$r['duration']!=-1);
        $end_time = Base_RegionalSettingsCommon::time2reg($next['end'],2,false,$r['duration']!=-1);

        $inf2 = array(
            'Date'=>'<b>'.$event_date.'</b>');

		if ($r['duration']==-1) {
			$inf2 += array('Time'=>Base_LangCommon::ts('CRM_Calendar_Event','Timeless event'));
		} else {
			$inf2 += array(
				'Time'=>$start_time.' - '.$end_time,
				'Duration'=>Base_RegionalSettingsCommon::seconds_to_words($r['duration'])
				);
			}



		$emps = array();
		foreach ($r['employees'] as $e) $emps[] = CRM_ContactsCommon::contact_format_no_company($e);
		$cuss = array();
		foreach ($r['customers'] as $c) $cuss[] = CRM_ContactsCommon::display_company_contact(array('customers'=>$c), true, array('id'=>'customers'));

		$inf2 += array(	'Event' => '<b>'.$next['title'].'</b>',
						'Description' => $next['description'],
						'Assigned to' => implode('<br>',$emps),
						'Contacts' => implode('<br>',$cuss),
						'Status' => Utils_CommonDataCommon::get_value('CRM/Status/'.$r['status']),
						'Access' => Utils_CommonDataCommon::get_value('CRM/Access/'.$r['permission']),
						'Priority' => Utils_CommonDataCommon::get_value('CRM/Priority/'.$r['priority']),
						'Notes' => Utils_AttachmentCommon::count('CRM/Calendar/Event/'.$r['id'])
					);

//		$next['employees'] = implode('<br>',$emps);
//		$next['customers'] = implode('<br>',$cuss);
		$next['employees'] = $r['employees'];
		$next['customers'] = $r['customers'];
		$next['status'] = $r['status']<=2?'active':'closed';
		$next['custom_tooltip'] = 
									'<center><b>'.
										Base_LangCommon::ts('CRM_Meeting','Meeting').
									'</b></center><br>'.
									Utils_TooltipCommon::format_info_tooltip($inf2,'CRM_Calendar_Event').'<hr>'.
									CRM_ContactsCommon::get_html_record_info($r['created_by'],$r['created_on'],null,null);
		return $next;
	}

	public static function crm_event_get_all($start, $end, $filter=null, $customers=null) {
		$start = date('Y-m-d',Base_RegionalSettingsCommon::reg2time($start));
		$crits = array();
		if ($filter===null) $filter = CRM_FiltersCommon::get();
		if($filter!='()' && $filter)
			$crits[($customers?'(':'').'employees'] = explode(',',trim($filter,'()'));
		if ($customers) 
			$crits['|customers'] = $customers;
		$me = CRM_ContactsCommon::get_my_record();
		if(!Base_AclCommon::i_am_admin()) {
			$crits = Utils_RecordBrowserCommon::merge_crits($crits, array('(employees' => $me['id'], '|<permission' => 2));
		}
		$critsb = $crits;
		
		$crits['<=date'] = $end;
		$crits['>=date'] = $start;
		$crits['recurrence_type'] = '';
		
		$ret = Utils_RecordBrowserCommon::get_records('crm_meeting', $crits);

		$result = array();
		foreach ($ret as $r)
			$result[] = self::crm_event_get($r);

		$crits = $critsb;

		$crits['<=date'] = $end;
		$crits['>=recurrence_end'] = array($start,'');
		$crits['!recurrence_type'] = '';
		$ret = Utils_RecordBrowserCommon::get_records('crm_meeting', $crits);
		
		$day = strtotime($start);
		$end = strtotime($end);
		while ($day<=$end) {
			foreach ($ret as $r) {
				$next = self::crm_event_get($r, date('Y-m-d', $day));
				if ($next) $result[] = $next;
			}
			$day = strtotime('+1 day', $day);
		}

		return $result;
	}

    public static function search_format($id) {
        $row = Utils_RecordBrowserCommon::get_record('crm_meeting',$id);
        if(!$row) return false;
        return Utils_RecordBrowserCommon::record_link_open_tag('crm_meeting', $row['id']).Base_LangCommon::ts('Custom_CADES_Hospitalizations', 'Meeting (attachment) #%d, %s at %s', array($row['id'], $row['title'], Base_RegionalSettingsCommon::time2reg($row['date'], false))).Utils_RecordBrowserCommon::record_link_close_tag();
    }

	public static function search($word){
		$ret = array();
		if(self::Instance()->acl_check('browse meetings')) {
			$result = Utils_RecordBrowserCommon::get_records('crm_meeting',array('(~"title'=>DB::Concat('\'%\'',DB::qstr($word),'\'%\''), '|~"description'=>DB::Concat('\'%\'',DB::qstr($word),'\'%\'')));

	 		foreach ($result as $row) {
				$ret[$row['id']] = Utils_RecordBrowserCommon::record_link_open_tag('crm_meeting', $row['id']).Base_LangCommon::ts('Custom_CADES_Hospitalizations', 'Meeting #%d, %s at %s', array($row['id'], $row['title'], Base_RegionalSettingsCommon::time2reg($row['date'], false))).Utils_RecordBrowserCommon::record_link_close_tag();
	 		}
 		}
		
		return $ret;
	}

	public static function get_alarm($id) {
		$a = self::get_meeting($id);

		if (!$a) return Base_LangCommon::ts('CRM_Calendar_Event','Private record');

		if($a['duration']<0)
			$date = Base_LangCommon::ts('CRM_Calendar_Event','Timeless event: %s',array(Base_RegionalSettingsCommon::time2reg($a['date'],false)));
		else
			$date = Base_LangCommon::ts('CRM_Calendar_Event',"Date: %s",array(Base_RegionalSettingsCommon::time2reg($a['date'].' '.date('H:i:s',strtotime($a['time'])),2)));

		return $date."\n".Base_LangCommon::ts('CRM_Calendar_Event',"Title: %s",array($a['title']));
	}


	///////////////////////////////////
	// mobile devices

	public function mobile_menu() {
		if(!self::Instance()->acl_check('browse meetings'))
			return array();
		return array('Meetings'=>array('func'=>'mobile_meetings','color'=>'blue'));
	}
	
	public function mobile_meetings() {
		$me = CRM_ContactsCommon::get_my_record();
		$defaults = array('employees'=>array($me['id']),'status'=>0, 'permission'=>0, 'priority'=>1);
		Utils_RecordBrowserCommon::mobile_rb('crm_meeting',array('employees'=>array($me['id'])),array('date'=>'ASC', 'time'=>'ASC', 'priority'=>'DESC', 'title'=>'ASC'),array('date'=>1,'time'=>1,'priority'=>1,'longterm'=>1),$defaults);
	}
}

?>
