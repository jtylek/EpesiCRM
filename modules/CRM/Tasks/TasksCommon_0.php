<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage tasks
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
		
		// Build array representing 2-column tooltip
		// Format: array (Label,value)
		$access = Utils_CommonDataCommon::get_translated_array('CRM/Access');
		$priority = Utils_CommonDataCommon::get_translated_array('CRM/Priority');
		$status = Utils_CommonDataCommon::get_translated_array('CRM/Status');

		$args=array(
					'Task:'=>'<b>'.$r['title'].'</b>',
					'Description:'=>$r['description'],
					'Assigned to:'=>CRM_ContactsCommon::display_contact(array('id'=>$r['employees']),true,array('id'=>'id', 'param'=>'::;CRM_ContactsCommon::contact_format_no_company')),
					'Contacts:'=>CRM_ContactsCommon::display_contact(array('id'=>$r['customers']),true,array('id'=>'id', 'param'=>'::;CRM_ContactsCommon::contact_format_default')),
					'Status:'=>$status[$r['status']],
					'Deadline:'=>$r['deadline']!=''?Base_RegionalSettingsCommon::time2reg($r['deadline'],false):Base_LangCommon::ts('CRM_Tasks','Not set'),
					'Longterm:'=>Base_LangCommon::ts('CRM_Tasks',$r['longterm']!=0?'Yes':'No'),
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

		$ret = array('notes'=>Utils_TooltipCommon::format_info_tooltip($args,'CRM_Tasks'));
		if ($bg_color) $ret['row_attrs'] = 'style="background:'.$bg_color.';"';
		return $ret;
	}

	public static function menu() {
		if(self::Instance()->acl_check('browse tasks'))
			return array('CRM'=>array('__submenu__'=>1,'Tasks'=>array()));
		else
			return array();
	}

	public static function task_bbcode($text, $param, $opt) {
		return Utils_RecordBrowserCommon::record_bbcode('task', array('title'), $text, $param, $opt);
	}
	
	public static function get_tasks($crits = array(), $cols = array(), $order = array()) {
		return Utils_RecordBrowserCommon::get_records('task', $crits, $cols, $order);
	}

	public static function get_task($id) {
		return Utils_RecordBrowserCommon::get_record('task', $id);
	}

	public static function access_task($action, $param=null){
		$i = self::Instance();
		switch ($action) {
			case 'browse_crits':	if (!$i->acl_check('browse tasks')) return false;
									$me = CRM_ContactsCommon::get_my_record();
									return ($param['permission']!=2 || $param['employees']==$me['id'] || $param['customers']==$me['id']);
			case 'browse':	return true;
			case 'view':	if (!$i->acl_check('view task')) return false;
							$me = CRM_ContactsCommon::get_my_record();
							return array('(!permission'=>2, '|employees'=>$me['id'], '|customers'=>$me['id']);
			case 'add':		return $i->acl_check('edit task');
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
	public static function display_status($record, $nolink, $desc) {
		$prefix = 'crm_tasks_leightbox';
		CRM_FollowupCommon::drawLeightbox($prefix);

		$v = $record[$desc['id']];
		if (!$v) $v = 0;
		$status = Utils_CommonDataCommon::get_translated_array('CRM/Status');
		if (!self::access_task('edit', $record) && !Base_AclCommon::i_am_admin()) return $status[$v];
		if ($v>=2) return $status[$v];
		if (isset($_REQUEST['form_name']) && $_REQUEST['form_name']==$prefix.'_follow_up_form' && $_REQUEST['id']==$record['id']) {
			unset($_REQUEST['form_name']);
			$v = $_REQUEST['closecancel'];
			$action  = $_REQUEST['action'];

			$note = $_REQUEST['note'];
			if ($note) {
				if (get_magic_quotes_gpc())
					$note = stripslashes($note);
				$note = str_replace("\n",'<br />',$note);
				Utils_AttachmentCommon::add('CRM/Tasks/'.$record['id'],0,Acl::get_user(),$note);
			}

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
				if ($action == 'new_event') CRM_CalendarCommon::view_event('add',array('title'=>$values['title'],'access'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'emp_id'=>$values['employees'],'cus_id'=>$values['customers']));
				if ($action == 'new_phonecall') $x->push_main('Utils/RecordBrowser','view_entry',array('add', null, array('subject'=>$values['title'],'permission'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'date_and_time'=>date('Y-m-d H:i:s'),'employees'=>$values['employees'], 'contact'=>!empty($values['customers'])?array_pop($values['customers']):'')), array('phonecall'));
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
		case 'display':
			$values['date_and_time'] = date('Y-m-d H:i:s');
			$values['title'] = Base_LangCommon::ts('CRM_Tasks','Follow up: ').$values['title'];
			$values['status'] = 0;
			$ret = array();
			if (ModuleManager::is_installed('CRM/Calendar')>=0) $ret['new_event'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM_Tasks','New Event')).' '.CRM_CalendarCommon::create_new_event_href(array('title'=>$values['title'],'access'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'emp_id'=>$values['employees'],'cus_id'=>$values['customers'])).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Calendar','icon-small.png').'"></a>';
			$ret['new_task'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM_Tasks','New Task')).' '.Utils_RecordBrowserCommon::create_new_record_href('task', $values).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Tasks','icon-small.png').'"></a>';
			if (ModuleManager::is_installed('CRM/PhoneCall')>=0) $ret['new_phonecall'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM_Tasks','New Phonecall')).' '.Utils_RecordBrowserCommon::create_new_record_href('phonecall', array('subject'=>$values['title'],'permission'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'date_and_time'=>date('Y-m-d H:i:s'),'employees'=>$values['employees'], 'contact'=>!empty($values['customers'])?array_pop($values['customers']):'')).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_PhoneCall','icon-small.png').'"></a>';
			return $ret;
		case 'add':
			break;
		case 'edit':
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
	public static function watchdog_label($rid = null, $events = array(), $details = true) {
		return Utils_RecordBrowserCommon::watchdog_label(
				'task',
				Base_LangCommon::ts('CRM_Tasks','Tasks'),
				$rid,
				$events,
				'title',
				$details
			);
	}
	
	public static function search_format($id) {
		if(!self::Instance()->acl_check('browse tasks')) return false;
		$row = self::get_tasks(array('id'=>$id));
		if(!$row) return false;
		$row = array_pop($row);
		return Utils_RecordBrowserCommon::record_link_open_tag('task', $row['id']).Base_LangCommon::ts('CRM_Tasks', 'Task (attachment) #%d, %s', array($row['id'], $row['title'])).Utils_RecordBrowserCommon::record_link_close_tag();
	}

	///////////////////////////////////
	// mobile devices

	public function mobile_menu() {
		if(!Acl::is_user())
			return array();
		return array('Tasks'=>array('func'=>'mobile_tasks','color'=>'blue'));
	}
	
	public function mobile_tasks() {
		$me = CRM_ContactsCommon::get_my_record();
		Utils_RecordBrowserCommon::mobile_rb('task',array('employees'=>array($me['id'])),array('deadline'=>'ASC', 'priority'=>'DESC', 'title'=>'ASC'),array('priority'=>1, 'deadline'=>1,'longterm'=>1));
	}
}

?>