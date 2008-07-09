<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_PhoneCallCommon extends ModuleCommon {
	public static function applet_caption() {
		return 'Phone Calls';
	}
	public static function applet_info() {
		return 'List of phone calls to do';
	}
	public static function applet_info_format($r){
		if (isset($r['contact']) && $r['contact']!='') {
			$c = CRM_ContactsCommon::get_contact($r['contact']);
			$contact = $c['last_name'].' '.$c['first_name'];
			if (isset($r['phone']) && $r['phone']!='') {
				list($ret, $num) = explode('__',$r['phone']);
				switch ($num) {
					case 1: $nr = 'Mobile Phone'; break;
					case 2: $nr = 'Work Phone'; break;
					case 3: $nr = 'Home Phone'; break;
				}
				$id = strtolower(str_replace(' ','_',$nr));
				$l = Base_LangCommon::ts('CRM/PhoneCall',$nr);
				$phone = $l.': '.$c[$id];
			} else $phone = $r['other_phone_number'];
		} else {
			$contact = $r['other_contact_name'];
			$phone = $r['other_phone_number'];
		}
		return 	Base_LangCommon::ts('CRM_PhoneCall','Subject: %s', array($r['subject'])).'<br>'.
				Base_LangCommon::ts('CRM_PhoneCall','Description: %s', array($r['description'])).'<br>'.
				Base_LangCommon::ts('CRM_PhoneCall','Contact: %s', array($contact)).'<br>'.
				Base_LangCommon::ts('CRM_PhoneCall','Phone: %s', array($phone)).'<br>'.
				Base_LangCommon::ts('CRM_PhoneCall','Date and Time: %s', array(Base_RegionalSettingsCommon::time2reg($r['date_and_time'])));
	}
	public static function get_phonecalls($crits = array(), $cols = array(), $order = array()) {
		return Utils_RecordBrowserCommon::get_records('phonecall', $crits, $cols, $order);
	}
	public static function get_phonecall($id) {
		return Utils_RecordBrowserCommon::get_record('phonecall', $id);
	}
	public static function access_phonecall($action, $param){
		$i = self::Instance();
		switch ($action) {
			case 'browse':
							return $i->acl_check('browse phonecalls');
			case 'view':	if ($i->acl_check('view phonecall')) return array('(!permission'=>2, '|:Created_by'=>Acl::get_user());
							else return false;
			case 'edit':	if ($param['permission']>=1 && $param['created_by']!=Acl::get_user()) return false;
							if ($i->acl_check('edit phonecall')) return true;
							$me = CRM_ContactsCommon::get_my_record();
							if (is_array($param['employees']) && in_array($me['id'], $param['employees'])) return true;
							if ($me['id']==$param['contact']) return true;
							$info = Utils_RecordBrowserCommon::get_record_info('phonecall',$param['id']);
							if ($me['login']==$info['created_by']) return true;
							return false;
			case 'delete':
							if ($i->acl_check('delete phonecall')) return true;
							$me = CRM_ContactsCommon::get_my_record();
							if (is_array($param['employees']) && in_array($me['id'], $param['employees'])) return true;
							if ($me['id']==$param['contact']) return true;
							$info = Utils_RecordBrowserCommon::get_record_info('phonecall',$param['id']);
							if ($me['login']==$info['created_by']) return true;
							return false;
			case 'fields':
							//if ($i->acl_check('edit phonecall')) return array();
							return array();
		}
		return false;
	}
	/*--------------------------------------------------------------------*/
	public static function employees_crits(){
		// Select only main company contacts and only office staff employees
		return array('company_name'=>array(CRM_ContactsCommon::get_main_company()),'Group'=>array('office'));
	}
	public static function company_crits(){
		return array('_no_company_option'=>true);
	}
	public static function menu() {
		return array('CRM'=>array('__submenu__'=>1,'Phone Calls'=>array()));
	}
	public static function caption() {
		return 'Phone Calls';
	}
	public static function QFfield_other_phone(&$form, $field, $label, $mode, $default, $desc) {
		if ($mode=='add' || $mode=='edit') {
			$js =
					'Event.observe(\'other_phone\',\'change\', onchange_other_phone);'.
					'function enable_disable_phone(arg) {'.
					'phone = document.forms[\''.$form->getAttribute('name').'\'].phone;'.
					'o_phone = document.forms[\''.$form->getAttribute('name').'\'].other_phone_number;'.
					'if (arg) {phone.disable();o_phone.enable();} else {if(phone.length!=0)phone.enable();o_phone.disable();}'.
					'};'.
					'function onchange_other_phone() {'.
					'c_phone = document.forms[\''.$form->getAttribute('name').'\'].other_phone;'.
					'enable_disable_phone(c_phone.checked);'.
					'};'.
					'c_phone = document.forms[\''.$form->getAttribute('name').'\'].other_phone;'.
					'enable_disable_phone('.($default?'1':'0').' || c_phone.checked);';
			eval_js($js);
			$form->addElement('checkbox', $field, $label, null, array('id'=>$field));
			if ($mode=='edit') $form->setDefaults(array($field=>$default));
		} else {
			$form->addElement('checkbox', $field, $label);
			$form->setDefaults(array($field=>$default));//self::display_phone(array($desc['id']=>$default), null, false, $desc)));
		}
	}
	public static function QFfield_other_contact(&$form, $field, $label, $mode, $default, $desc) {
		if ($mode=='add' || $mode=='edit') {
			$js =
					'Event.observe(\'other_contact\',\'change\', onchange_other_contact);'.
//					'Event.observe(\'phone\',\'change\', onchange_other_phone);'.
					'function enable_disable_contact(arg) {'.
					'contact = document.forms[\''.$form->getAttribute('name').'\'].contact;'.
					'o_contact = document.forms[\''.$form->getAttribute('name').'\'].other_contact_name;'.
					'c_phone = document.forms[\''.$form->getAttribute('name').'\'].other_phone;'.
					'if (arg) {c_phone.disable();contact.disable();o_contact.enable();} else {c_phone.enable();if(contact.length!=0)contact.enable();o_contact.disable();}'.
					'if (enable_disable_phone) enable_disable_phone(arg);'.
					'};'.
					'function onchange_other_contact() {'.
					'c_contact = document.forms[\''.$form->getAttribute('name').'\'].other_contact;'.
					'c_phone = document.forms[\''.$form->getAttribute('name').'\'].other_phone;'.
					'c_phone.checked = c_contact.checked;'.
					'enable_disable_contact(c_contact.checked);'.
					'};'.
					'c_contact = document.forms[\''.$form->getAttribute('name').'\'].other_contact;'.
					'enable_disable_contact('.($default?'1':'0').' || c_contact.checked);';
			eval_js($js);
			$form->addElement('checkbox', $field, $label, null, array('id'=>$field));
			if ($mode=='edit') $form->setDefaults(array($field=>$default));
		} else {
			$form->addElement('checkbox', $field, $label);
			$form->setDefaults(array($field=>$default));//self::display_phone(array($desc['id']=>$default), null, false, $desc)));
		}
	}
	public static function QFfield_phone(&$form, $field, $label, $mode, $default, $desc) {
		if ($mode=='add' || $mode=='edit') {
			$form->addElement('select', $field, $label, array(), array('id'=>$field));
			Utils_ChainedSelectCommon::create($field, array('company_name','contact'),'modules/CRM/PhoneCall/update_phones.php',null,$default);
			if ($mode=='edit') $form->setDefaults(array($field=>$default));
		} else {
			$form->addElement('static', $field, $label);
			$form->setDefaults(array($field=>self::display_phone(array($desc['id']=>$default), false, $desc)));
		}
	}
    public static function display_subject($record, $nolink = false) {
		$ret = Utils_RecordBrowserCommon::create_linked_label('phonecall', 'Subject', $record['id'], $nolink);
		if (isset($record['description']) && $record['description']!='') $ret = '<span '.Utils_TooltipCommon::open_tag_attrs($record['description'], false).'>'.$ret.'</span>';
		return $ret;
	}
	public static function display_phone_number($record, $nolink) {
		if ($record['other_phone']) return $record['other_phone_number'];
		else return self::display_phone(array('phone'=>$record['phone']),false,array('id'=>'phone'));
	}
	public static function display_contact_name($record, $nolink) {
		if ($record['other_contact']) return $record['other_contact_name'];
		if ($record['contact']=='') return '---';
		$ret = '';
		if (!$nolink) $ret .= Utils_RecordBrowserCommon::record_link_open_tag('contact', $record['contact']);
		$cont = CRM_ContactsCommon::get_contact($record['contact']);
		$ret .= $cont['last_name'].(($cont['first_name']!=='')?' '.$cont['first_name']:'');
		if (!$nolink) $ret .= Utils_RecordBrowserCommon::record_link_close_tag();
		return $ret;
	}
	public static function display_phone($record, $nolink, $desc) {
		if ($record[$desc['id']]=='') return '';
		list($ret, $num) = explode('__',$record[$desc['id']]);
		$contact = CRM_ContactsCommon::get_contact($ret);
		switch ($num) {
			case 1: $nr = 'Mobile Phone'; break;
			case 2: $nr = 'Work Phone'; break;
			case 3: $nr = 'Home Phone'; break;
		}
		$id = strtolower(str_replace(' ','_',$nr));
		$l = Base_LangCommon::ts('CRM/PhoneCall',$nr);
		return $l[0].': '.$contact[$id];
	}
	public static function display_status($record, $nolink, $desc) {
		$prefix = 'crm_phonecall_leightbox';
		CRM_FollowupCommon::drawLeightbox($prefix);
		$v = $record[$desc['id']];
		if (!$v) $v = 0;
		$status = Utils_CommonDataCommon::get_array('Ticket_Status');
		if (!self::access_phonecall('edit', $record) && !Base_AclCommon::i_am_admin()) return $status[$v];
		if ($v>=2) return $status[$v];
		if (isset($_REQUEST['form_name']) && $_REQUEST['form_name']==$prefix.'_follow_up_form' && $_REQUEST['id']==$record['id']) {
			unset($_REQUEST['form_name']);
			$v = $_REQUEST['closecancel'];
			$action  = $_REQUEST['action'];
			if ($action == 'set_in_progress') $v = 1;
			Utils_RecordBrowserCommon::update_record('phonecall', $record['id'], array('status'=>$v));
			if ($action == 'set_in_progress') location(array());

			$values = $record;
			$values['date_and_time'] = date('Y-m-d H:i:s');
			$values['title'] = Base_LangCommon::ts('CRM/PhoneCall','Follow up: ').$values['subject'];
			unset($values['status']);

			if ($action != 'none') {		
				$x = ModuleManager::get_instance('/Base_Box|0');
				if ($action == 'new_task') $x->push_main('Utils/RecordBrowser','view_entry',array('add', null, array('title'=>$values['subject'],'permission'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'deadline'=>date('Y-m-d H:i:s', strtotime('+1 day')),'employees'=>$values['employees'], 'customers'=>$values['contact'])), array('task'));
				if ($action == 'new_phonecall') $x->push_main('Utils/RecordBrowser','view_entry',array('add', null, $values), array('phonecall'));
				if ($action == 'new_event') CRM_CalendarCommon::view_event('add',array('title'=>$values['title'],'access'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'emp_id'=>$values['employees'],'cus_id'=>$values['contact']));
				return false;
			}

			location(array());
		}
		if ($v==0) {
			return '<a href="javascript:void(0)" onclick="'.$prefix.'_set_action(\'set_in_progress\');'.$prefix.'_set_id(\''.$record['id'].'\');'.$prefix.'_submit_form();">'.$status[$v].'</a>';
		}
		return '<a href="javascript:void(0)" class="lbOn" rel="'.$prefix.'_followups_leightbox" onMouseDown="'.$prefix.'_set_id('.$record['id'].');">'.$status[$v].'</a>';
	}

	public static function submit_phonecall($values, $mode) {
		if ($mode=='view') {
			$values['date_and_time'] = date('Y-m-d H:i:s');
			$values['subject'] = Base_LangCommon::ts('CRM/PhoneCall','Follow up: ').$values['subject'];
			unset($values['status']);
			$ret = array();
			if (ModuleManager::is_installed('CRM/Calendar')>=0) $ret['new_event'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM/PhoneCall','New Event')).' '.CRM_CalendarCommon::create_new_event_href(array('title'=>$values['subject'],'access'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'emp_id'=>$values['employees'],'cus_id'=>$values['contact'])).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Calendar','icon-small.png').'"></a>';
			if (ModuleManager::is_installed('CRM/Tasks')>=0) $ret['new_task'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM/PhoneCall','New Task')).' '.Utils_RecordBrowserCommon::create_new_record_href('task', array('title'=>$values['subject'],'permission'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'deadline'=>date('Y-m-d H:i:s', strtotime('+1 day')),'employees'=>$values['employees'], 'customers'=>$values['contact'])).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Tasks','icon-small.png').'"></a>';
			$ret['new_phonecall'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM/PhoneCall','New Phonecall')).' '.Utils_RecordBrowserCommon::create_new_record_href('phonecall', $values).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_PhoneCall','icon-small.png').'"></a>';
			return $ret;
		}
		if (isset($values['other_contact'])) {
			$values['other_phone']=1;
			$values['contact']='';
		} else $values['other_contact_name']='';
		if (isset($values['other_phone'])) $values['phone']='';
		else $values['other_phone_number']='';
		return $values;
	}
}
?>
