<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_TasksCommon extends ModuleCommon {
	public static function delete_page($id) {
		$mid = md5($id);
//		Utils_AttachmentCommon::persistent_mass_delete(null,'CRM/Tasks/'.$mid);
		DB::Execute('UPDATE task SET active=0 WHERE page_id=%s',array($mid));
	}
	public static function applet_info_format($r){
		return 	Base_LangCommon::ts('Utils_Tasks','Title: %s', array($r['title'])).'<br>'.
				Base_LangCommon::ts('Utils_Tasks','Description: %s', array($r['description'])).'<br>'.
				Base_LangCommon::ts('Utils_Tasks','Deadline: %s', array(Base_RegionalSettingsCommon::time2reg($r['deadline'])));
	}
	public static function get_tasks($crits = array(), $cols = array(), $order=array()) {
		return Utils_RecordBrowserCommon::get_records('task', $crits, $cols, $order);
	}
	public static function get_task($id) {
		return Utils_RecordBrowserCommon::get_record('task', $id);
	}
	public static function access_task($action, $param){
		$i = self::Instance(); // TODO: adjust rights
		switch ($action) {
			case 'browse':
							return $i->acl_check('browse tasks');
			case 'view':	if ($i->acl_check('view task')) return array('(!permission'=>2, '|:Created_by'=>Acl::get_user());
							else return false;
			case 'edit':	if ($param['permission']>=1 && $param['created_by']!=Acl::get_user()) return false;
							if ($i->acl_check('edit task')) return true;
							$me = CRM_ContactsCommon::get_my_record();
							if (is_array($param['employees']) && in_array($me['id'], $param['employees'])) return true;
							$info = Utils_RecordBrowserCommon::get_record_info('task',$param['id']);
							if ($me['login']==$info['created_by']) return true;
							return false;
			case 'delete':
							if ($i->acl_check('delete task')) return true;
							$me = CRM_ContactsCommon::get_my_record();
							if (is_array($param['employees']) && in_array($me['id'], $param['employees'])) return true;
							$info = Utils_RecordBrowserCommon::get_record_info('task',$param['id']);
							if ($me['login']==$info['created_by']) return true;
							return false;
			case 'edit_fields':
							if ($i->acl_check('edit task')) return array();
							return array();
		}
		return false;
	}
	/*--------------------------------------------------------------------*/
	public static function caption() {
		return 'Tasks';
	}
	/*--------------------------------------------------------------------*/
	public static function employees_crits(){
		return array('company_name'=>array(CRM_ContactsCommon::get_main_company()));
	}
	public static function customers_crits($arg){
		if (!$arg) return array('!company_name'=>array(CRM_ContactsCommon::get_main_company()), '(:Fav'=>true, '|:Recent'=>true);
		else return array('!company_name'=>array(CRM_ContactsCommon::get_main_company()));
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
		if (!$def) 	$def = '--';
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
				$form->addElement('checkbox', 'notify', Base_LangCommon::ts('Utils/Tasks','Notify'), null, array('id'=>'notify'));
			}
		} else {
			$form->addElement('checkbox', $field, $label);
			$form->setDefaults(array($field=>$default));
		}
	}
	public static function display_status($record, $nolink, $desc) {
		$v = $record[$desc['id']];
		if (!$v) $v = 0;
		$status = Utils_CommonDataCommon::get_array('Ticket_Status');
		if (!self::access_task('edit', $record) && !Base_AclCommon::i_am_admin()) return $status[$v];
		if (isset($_REQUEST['increase_task_status'])) {
			if ($_REQUEST['increase_task_status']==$record['id']) $v++;
			Utils_RecordBrowserCommon::update_record('task', $record['id'], array('status'=>$v));
			location(array());
		}
		if ($v==2) return $status[$v];
		return '<a '.
					Module::create_href(array('increase_task_status'=>$record['id'])).
					'>'.$status[$v].'</a>';
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
			return null;
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
