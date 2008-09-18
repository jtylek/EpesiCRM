<?php
/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-tasks
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_TasksCommon extends ModuleCommon {
	public static function applet_caption() {
		return "Tasks";
	}

	public static function applet_info() {
		return "To do list";
	}

	public static function applet_info_format($r){
		return 	Base_LangCommon::ts('CRM_Tasks','Title: %s', array($r['title'])).'<br>'.
				Base_LangCommon::ts('CRM_Tasks','Description: %s', array($r['description'])).'<br>'.
				($r['is_deadline']?
				($r['deadline']!=''?
				Base_LangCommon::ts('CRM_Tasks','Deadline: %s', array(Base_RegionalSettingsCommon::time2reg($r['deadline']))):Base_LangCommon::ts('CRM_Tasks','Deadline: Not set')):'');
	}

	public static function menu() {
		if(self::Instance()->acl_check('browse tasks'))
			return array('CRM'=>array('__submenu__'=>1,'Tasks'=>array()));
		else
			return array();
	}

	public static function get_tasks($crits = array(), $cols = array(), $order = array()) {
		return Utils_RecordBrowserCommon::get_records('task', $crits, $cols, $order);
	}

	public static function get_task($id) {
		return Utils_RecordBrowserCommon::get_record('task', $id);
	}

	public static function access_task($action, $param){
		$i = self::Instance();
		switch ($action) {
			case 'add':
			case 'browse':	return $i->acl_check('browse tasks');
			case 'view':	if (!$i->acl_check('view task')) return false;
							$me = CRM_ContactsCommon::get_my_record();
							return array('(!permission'=>2, '|employees'=>$me['id'], '|customers'=>$me['id']);
			case 'edit':	$me = CRM_ContactsCommon::get_my_record();
							if ($param['permission']>=1 &&
								!in_array($me['id'],$param['employees']) &&
								!in_array($me['id'],$param['customers'])) return false;
							if ($i->acl_check('edit task')) return true;
							return false;
			case 'delete':	if ($i->acl_check('delete task')) return true;
							$me = CRM_ContactsCommon::get_my_record();
							if ($me['login']==$param['created_by']) return true;
							return false;
			case 'fields':
							//if ($i->acl_check('edit task')) return array();
							return array();
		}
		return false;
	}

	public static function applet_settings() {
		return Utils_RecordBrowserCommon::applet_settings(array(
			array('label'=>'Display tasks marked as','name'=>'term','type'=>'select','values'=>array('s'=>'Short term','l'=>'Long term','b'=>'Both'),'default'=>'s','rule'=>array(array('message'=>'Field required', 'type'=>'required'))),
			array('label'=>'Display closed tasks','name'=>'closed','type'=>'checkbox','default'=>false),
			array('label'=>'Related','name'=>'related','type'=>'select','values'=>array('Employee','Customer','Both'),'default'=>'0')
			));
	}
	
	public static function body_access() {
		return self::Instance()->acl_check('browse tasks');
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
					$icon = Utils_WatchdogCommon::user_check_if_notified($contact['login'],'task',$record['id']);
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
		$ret = Utils_RecordBrowserCommon::create_linked_label_r('task', 'Title', $record, $nolink);
		if (isset($record['description']) && $record['description']!='') $ret = '<span '.Utils_TooltipCommon::open_tag_attrs($record['description'], false).'>'.$ret.'</span>';
		return $ret;
	}
    public static function display_title_with_mark($record) {
		$me = CRM_ContactsCommon::get_my_record();
		$ret = self::display_title($record, false);
		if (!in_array($me['id'], $record['employees'])) return $ret;
		$notified = Utils_WatchdogCommon::check_if_notified('task',$record['id']);
		if ($notified!==true && $notified!==null) $ret = '<img src="'.Base_ThemeCommon::get_template_file('CRM_Tasks','notice.png').'">'.$ret;
		return $ret;
	}
	public static function QFfield_is_deadline(&$form, $field, $label, $mode, $default, $desc) {
		if ($mode=='add' || $mode=='edit') {
			$js =
					'Event.observe(\'is_deadline\',\'change\', onchange_is_deadline);'.
					'function enable_disable_deadline(arg) {'.
					'deadline = document.forms[\''.$form->getAttribute('name').'\'].deadline;'.
					'if (arg) {deadline.enable();} else {deadline.disable();}'.
					'};'.
					'function onchange_is_deadline() {'.
					'is_deadline = document.forms[\''.$form->getAttribute('name').'\'].is_deadline;'.
					'enable_disable_deadline(is_deadline.checked);'.
					'};'.
					'is_deadline = document.forms[\''.$form->getAttribute('name').'\'].is_deadline;'.
					'enable_disable_deadline('.($default?'1':'0').' || is_deadline.checked);';
			eval_js($js);
			$form->addElement('checkbox', $field, $label, null, array('id'=>$field));
			if ($mode=='edit') {
				$form->setDefaults(array($field=>$default));
			}
		} else {
			$form->addElement('checkbox', $field, $label);
			$form->setDefaults(array($field=>$default));
		}
	}
	public static function display_status($record, $nolink, $desc) {
		$prefix = 'crm_tasks_leightbox';
		CRM_FollowupCommon::drawLeightbox($prefix);

		$v = $record[$desc['id']];
		if (!$v) $v = 0;
		$status = Utils_CommonDataCommon::get_translated_array('Ticket_Status');
		if (!self::access_task('edit', $record) && !Base_AclCommon::i_am_admin()) return $status[$v];
		if ($v>=2) return $status[$v];
		if (isset($_REQUEST['form_name']) && $_REQUEST['form_name']==$prefix.'_follow_up_form' && $_REQUEST['id']==$record['id']) {
			unset($_REQUEST['form_name']);
			$v = $_REQUEST['closecancel'];
			$action  = $_REQUEST['action'];
			if ($action == 'set_in_progress') $v = 1;
			Utils_RecordBrowserCommon::update_record('task', $record['id'], array('status'=>$v));
			if ($action == 'set_in_progress') location(array());

			$values = $record;
			$values['date_and_time'] = date('Y-m-d H:i:s');
			$values['title'] = Base_LangCommon::ts('CRM/Tasks','Follow up: ').$values['title'];
			$values['status'] = 0;

			if ($action != 'none') {		
				$x = ModuleManager::get_instance('/Base_Box|0');
				if ($action == 'new_task') $x->push_main('Utils/RecordBrowser','view_entry',array('add', null, $values), array('task'));
				if ($action == 'new_phonecall') $x->push_main('Utils/RecordBrowser','view_entry',array('add', null, array('subject'=>$values['title'],'permission'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'date_and_time'=>date('Y-m-d H:i:s'),'employees'=>$values['employees'], 'contact'=>isset($values['customers'][0])?$values['customers'][0]:'')), array('phonecall'));
				if ($action == 'new_event') CRM_CalendarCommon::view_event('add',array('title'=>$values['title'],'access'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'emp_id'=>$values['employees'],'cus_id'=>$values['customers']));
				return false;
			}

			location(array());
		}
		if ($v==0) {
			return '<a href="javascript:void(0)" onclick="'.$prefix.'_set_action(\'set_in_progress\');'.$prefix.'_set_id(\''.$record['id'].'\');'.$prefix.'_submit_form();">'.$status[$v].'</a>';
		}
		return '<a href="javascript:void(0)" class="lbOn" rel="'.$prefix.'_followups_leightbox" onMouseDown="'.$prefix.'_set_id('.$record['id'].');">'.$status[$v].'</a>';
	}
	public static function subscribed_employees($v) {
		if (!is_array($v)) return;
		foreach ($v['employees'] as $k) {
			$user = Utils_RecordBrowserCommon::get_value('contact',$k,'Login');
			if ($user!==false && $user!==null) Utils_WatchdogCommon::user_subscribe($user, 'task', $v['id']);
		}
	}

	public static function submit_task($values, $mode) {
		$me = CRM_ContactsCommon::get_my_record();
		switch ($mode) {
		case 'view':
			$values['date_and_time'] = date('Y-m-d H:i:s');
			$values['title'] = Base_LangCommon::ts('CRM_Tasks','Follow up: ').$values['title'];
			$values['status'] = 0;
			$ret = array();
			if (ModuleManager::is_installed('CRM/Calendar')>=0) $ret['new_event'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM_Tasks','New Event')).' '.CRM_CalendarCommon::create_new_event_href(array('title'=>$values['title'],'access'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'emp_id'=>$values['employees'],'cus_id'=>$values['customers'])).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Calendar','icon-small.png').'"></a>';
			$ret['new_task'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM_Tasks','New Task')).' '.Utils_RecordBrowserCommon::create_new_record_href('task', $values).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Tasks','icon-small.png').'"></a>';
			if (ModuleManager::is_installed('CRM/PhoneCall')>=0) $ret['new_phonecall'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM_Tasks','New Phonecall')).' '.Utils_RecordBrowserCommon::create_new_record_href('phonecall', array('subject'=>$values['title'],'permission'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'date_and_time'=>date('Y-m-d H:i:s'),'employees'=>$values['employees'], 'contact'=>isset($values['customers'][0])?$values['customers'][0]:'')).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_PhoneCall','icon-small.png').'"></a>';
			return $ret;
		case 'add':
			if (!isset($values['is_deadline'])) {
				$values['deadline']='';
			}
			break;
		case 'edit':
			if (!isset($values['is_deadline'])) {
				$values['deadline']='';
			}
			$old_values = Utils_RecordBrowserCommon::get_record('task',$values['id']);
			$old_related = array_merge($old_values['employees'],$old_values['customers']);
		case 'added':
			self::subscribed_employees($values);
			$related = array_merge($values['employees'],$values['customers']);
			foreach ($related as $v) {
				if ($mode==='edit' && in_array($v, $old_related)) continue;
				$subs = Utils_WatchdogCommon::get_subscribers('contact',$v);
				foreach($subs as $s)
					Utils_WatchdogCommon::user_subscribe($s, 'task',$values['id']);
			}
			break;
		}
		return $values;
	}
	public static function watchdog_label($rid = null, $events = array()) {
		return Utils_RecordBrowserCommon::watchdog_label(
				'task',
				Base_LangCommon::ts('CRM_Tasks','Tasks'),
				$rid,
				$events,
				'title'
			);
	}
}

?>