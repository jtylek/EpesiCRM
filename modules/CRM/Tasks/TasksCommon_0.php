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
		if(self::Instance()->acl_check('access'))
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
							$info = Utils_RecordBrowserCommon::get_record_info('task',$param['id']);
							if ($me['login']==$info['created_by']) return true;
							return false;
			case 'edit_fields':
							if ($i->acl_check('edit task')) return array();
							return array();
		}
		return false;
	}

	public static function applet_settings() {
		return array(
			array('label'=>'Display tasks marked as','name'=>'term','type'=>'select','values'=>array('s'=>'Short term','l'=>'Long term','b'=>'Both'),'default'=>'s','rule'=>array(array('message'=>'Field required', 'type'=>'required'))),
			array('label'=>'Display closed tasks','name'=>'closed','type'=>'checkbox','default'=>false),
			array('label'=>'Related','name'=>'related','type'=>'select','values'=>array('Employee','Customer','Both'),'default'=>'0')
			);
	}
	
	public static function body_access() {
		return self::Instance()->acl_check('access');
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
		$v = $record[$desc['id']];
		$def = '';
		$first = true;
		$param = explode(';',$desc['param']);
		if ($param[1] == '::') $callback = array('CRM_ContactsCommon', 'contact_format_default');
		else $callback = explode('::', $param[1]);
		if (!is_array($v)) $v = array($v);
		$ret = DB::GetAssoc('SELECT contact_id, 1 FROM task_employees_notified WHERE task_id=%d', array($record['id']));
		foreach($v as $k=>$w){
			if ($w=='') break;
			if ($first) $first = false;
			else $def .= '<br>';
			$def .= '<img src="'.(isset($ret[$w])?$icon_on:$icon_off).'" />'.Utils_RecordBrowserCommon::no_wrap(call_user_func($callback, CRM_ContactsCommon::get_contact($w), $nolink));
		}
		if (!$def) 	$def = '---';
		return $def;
	}
	public static function contact_format_with_balls($record, $nolink=false){
		$ret = '<span id="contact_confirmed_'.$record['id'].'"></span>';
		if (!$nolink) $ret .= Utils_RecordBrowserCommon::record_link_open_tag('contact', $record['id']);
		$ret .= $record['last_name'].(($record['first_name']!=='')?' '.$record['first_name']:'');
		if (!$nolink) $ret .= Utils_RecordBrowserCommon::record_link_close_tag();		return $ret;
	}
    public static function display_title($record, $nolink) {
		$ret = Utils_RecordBrowserCommon::create_linked_label('task', 'Title', $record['id'], $nolink);
		if (isset($record['description']) && $record['description']!='') $ret = '<span '.Utils_TooltipCommon::open_tag_attrs($record['description'], false).'>'.$ret.'</span>';
		return $ret;
	}
    public static function display_title_with_mark($record) {
		$me = CRM_ContactsCommon::get_my_record();
		$ret = self::display_title($record, false);
		if (!in_array($me['id'], $record['employees'])) return $ret;
		$notified = DB::GetOne('SELECT 1 FROM task_employees_notified WHERE contact_id=%d AND task_id=%d', array($me['id'],$record['id']));
		if ($notified===false) $ret = '<b>!</b> '.$ret;
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
				$form->addElement('checkbox', 'notify', Base_LangCommon::ts('CRM_Tasks','Notify'), null, array('id'=>'notify'));
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
		$status = Utils_CommonDataCommon::get_array('Ticket_Status');
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
			unset($values['status']);

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
	public static function set_notified($user, $task) {
		DB::Execute('DELETE FROM task_employees_notified WHERE contact_id=%d AND task_id=%d', array($user,$task));
		DB::Execute('INSERT INTO task_employees_notified (contact_id, task_id) VALUES (%d,%d)', array($user,$task));
	}
	public static function submit_task($values, $mode) {
		$me = CRM_ContactsCommon::get_my_record();
		if ($mode=='view') {
			$ret = DB::GetAssoc('SELECT contact_id, 1 FROM task_employees_notified WHERE task_id=%d', array($values['id']));
			$icon_on = Base_ThemeCommon::get_template_file('images/active_on.png');
			$icon_off = Base_ThemeCommon::get_template_file('images/active_off.png');
			foreach($values['employees'] as $v) {
				if ($values['id']!=null)
					if ($v==$me['id']) {
						$ret[$v] = 1;
						self::set_notified($me['id'],$values['id']);
					}
				eval_js('document.getElementById("contact_confirmed_'.$v.'").innerHTML = "<img src=\"'.(isset($ret[$v])?$icon_on:$icon_off).'\" />";');
			}
			$values['date_and_time'] = date('Y-m-d H:i:s');
			$values['title'] = Base_LangCommon::ts('CRM_Tasks','Follow up: ').$values['title'];
			unset($values['status']);
			$ret = array();
			if (ModuleManager::is_installed('CRM/Calendar')>=0) $ret['new_event'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM_Tasks','New Event')).' '.CRM_CalendarCommon::create_new_event_href(array('title'=>$values['title'],'access'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'emp_id'=>$values['employees'],'cus_id'=>$values['customers'])).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Calendar','icon-small.png').'"></a>';
			$ret['new_task'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM_Tasks','New Task')).' '.Utils_RecordBrowserCommon::create_new_record_href('task', $values).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Tasks','icon-small.png').'"></a>';
			if (ModuleManager::is_installed('CRM/PhoneCall')>=0) $ret['new_phonecall'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM_Tasks','New Phonecall')).' '.Utils_RecordBrowserCommon::create_new_record_href('phonecall', array('subject'=>$values['title'],'permission'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'date_and_time'=>date('Y-m-d H:i:s'),'employees'=>$values['employees'], 'contact'=>isset($values['customers'][0])?$values['customers'][0]:'')).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_PhoneCall','icon-small.png').'"></a>';
			return $ret;
		}
		if ($values['id']!=null) {
			foreach($values['employees'] as $v) {
				if ($v==$me['id']) 
					self::set_notified($me['id'],$values['id']);
			}
		}
		if (!isset($values['is_deadline'])) {
			$values['deadline']='';
		}
		if (isset($values['notify'])) {
			DB::Execute('DELETE FROM task_employees_notified WHERE task_id=%s', array($values['id']));
		}
		return $values;
	}
}

?>