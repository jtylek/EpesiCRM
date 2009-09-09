<?php
/**
 * CRM Phone Call Class
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage phonecall
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_PhoneCallCommon extends ModuleCommon {
	public static function applet_caption() {
		return 'Phone Calls';
	}
	public static function applet_info() {
		return 'List of phone calls to do';
	}
	public static function applet_settings() {
		return Utils_RecordBrowserCommon::applet_settings();		
	}
	public static function applet_info_format($r){

		if (isset($r['contact']) && $r['contact']!='') {
			$c = CRM_ContactsCommon::get_contact($r['contact']);
			$contact = $c['last_name'].' '.$c['first_name'];
			$company = CRM_ContactsCommon::display_company(array('id'=>$r['company_name']),true);
			if (isset($r['phone']) && $r['phone']!='') {
				$num = $r['phone'];
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
			$company = '---';
		}

		// Build array representing 2-column tooltip
		// Format: array (Label,value)
		$access = Utils_CommonDataCommon::get_translated_array('CRM/Access');
		$priority = Utils_CommonDataCommon::get_translated_array('CRM/Priority');
		$status = Utils_CommonDataCommon::get_translated_array('CRM/Status');

		$args=array(
					'Call:'=>'<b>'.$phone.'</b>',
					'Contact:'=>$contact,
					'Company:'=>$company,
					'Subject:'=>'<b>'.$r['subject'].'</b>',
					'Description:'=>$r['description'],
					'Assigned to:'=>CRM_ContactsCommon::display_contact(array('id'=>$r['employees']),true,array('id'=>'id', 'param'=>'::;CRM_ContactsCommon::contact_format_no_company')),
					'Date and Time:'=>Base_RegionalSettingsCommon::time2reg($r['date_and_time']),
					'Status:'=>$status[$r['status']],
					'Permission:'=>$access[$r['permission']],
					'Priority:'=>$priority[$r['priority']]
					);

		// Pass 2 arguments: array containing pairs: label/value
		// and the name of the group for translation
		$bg_color = '';
		switch ($r['priority']) {
			case 1: $bg_color = '#FFFFD5'; break; 
			case 2: $bg_color = '#FFD5D5'; break; 
		}
		$ret = array('notes'=>Utils_TooltipCommon::format_info_tooltip($args,'CRM_PhoneCall'));
		if ($bg_color) $ret['row_attrs'] = 'style="background:'.$bg_color.';"';
		return $ret;

	/*
		return 	Base_LangCommon::ts('CRM_PhoneCall','Subject: %s', array($r['subject'])).'<br>'.
				Base_LangCommon::ts('CRM_PhoneCall','Description: %s', array($r['description'])).'<br>'.
				Base_LangCommon::ts('CRM_PhoneCall','Contact: %s', array($contact)).'<br>'.
				Base_LangCommon::ts('CRM_PhoneCall','Phone: %s', array($phone)).'<br>'.
				Base_LangCommon::ts('CRM_PhoneCall','Date and Time: %s', array(Base_RegionalSettingsCommon::time2reg($r['date_and_time'])));
	*/
	}

	public static function get_phonecalls($crits = array(), $cols = array(), $order = array()) {
		return Utils_RecordBrowserCommon::get_records('phonecall', $crits, $cols, $order);
	}
	public static function get_phonecall($id) {
		return Utils_RecordBrowserCommon::get_record('phonecall', $id);
	}
	public static function access_phonecall($action, $param=null){
		$i = self::Instance();
		switch ($action) {
			case 'browse_crits':	if ($i->acl_check('browse phonecalls')) return array('(!permission'=>2, '|:Created_by'=>Acl::get_user());
									return false;
			case 'browse':	return true;
			case 'view':	if ($param['permission']==2 && $param['created_by']!=Acl::get_user()) return false;
							return $i->acl_check('view phonecall');
			case 'add':		return $i->acl_check('edit phonecall');
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
		}
		return false;
	}
	/*--------------------------------------------------------------------*/
	public static function employees_crits(){
		// Select only main company contacts and only office staff employees
		return array('company_name'=>array(CRM_ContactsCommon::get_main_company()),'group'=>array('office'));
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
					'function enable_disable_contact(arg) {'.
					'contact = document.forms[\''.$form->getAttribute('name').'\'].contact;'.
					'o_contact = document.forms[\''.$form->getAttribute('name').'\'].other_contact_name;'.
					'c_phone = document.forms[\''.$form->getAttribute('name').'\'].other_phone;'.
					'if (arg) {c_phone.disable();contact.disable();o_contact.enable();} else {c_phone.enable();if(contact.length!=0)contact.enable();o_contact.disable();}'.
					'c_phone.checked=arg;'.
					'phone = document.forms[\''.$form->getAttribute('name').'\'].phone;'.
					'o_phone = document.forms[\''.$form->getAttribute('name').'\'].other_phone_number;'.
					'if (arg) {phone.disable();o_phone.enable();} else {if(phone.length!=0)phone.enable();o_phone.disable();}'.
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
	public static function check_contact_not_empty($v) {
		$ret = array();
		if ((isset($v['other_phone']) && $v['other_phone']) || (isset($v['other_contact']) && $v['other_contact'])) {
			if (!isset($v['other_phone_number']) || !$v['other_phone_number']) 
				$ret['other_phone_number'] = Base_LangCommon::ts('CRM_PhoneCall','Field required');
		} else {
			if (!isset($v['phone']) || !$v['phone']) 
				$ret['phone'] = Base_LangCommon::ts('CRM_PhoneCall','Field required');
		}
		if (!isset($v['other_contact']) || !$v['other_contact']) {
			if (!isset($v['contact']) || !$v['contact']) 
				$ret['contact'] = Base_LangCommon::ts('CRM_PhoneCall','Field required');
		} else {
			if (!isset($v['other_contact_name']) || !$v['other_contact_name']) 
				$ret['other_contact_name'] = Base_LangCommon::ts('CRM_PhoneCall','Field required');
		}
		return empty($ret)?true:$ret;
	}
	public static function QFfield_phone(&$form, $field, $label, $mode, $default, $desc) {
		if ($mode=='add' || $mode=='edit') {
			$form->addElement('select', $field, $label, array(), array('id'=>$field));
			if ($mode=='edit') $form->setDefaults(array($field=>$default));
			Utils_ChainedSelectCommon::create($field, array('company_name','contact'),'modules/CRM/PhoneCall/update_phones.php',null,$form->exportValue($field));
			$form->addFormRule(array('CRM_PhoneCallCommon','check_contact_not_empty'));
		} else {
			$form->addElement('static', $field, $label);
			$form->setDefaults(array($field=>self::display_phone(Utils_RecordBrowser::$last_record, false, $desc)));
		}
	}
    public static function display_subject($record, $nolink = false) {
		$ret = Utils_RecordBrowserCommon::create_linked_label_r('phonecall', 'Subject', $record, $nolink);
		if (isset($record['description']) && $record['description']!='') $ret = '<span '.Utils_TooltipCommon::open_tag_attrs($record['description'], false).'>'.$ret.'</span>';
		return $ret;
	}
	public static function display_phone_number($record, $nolink) {
		if ($record['other_phone']) {
			if(MOBILE_DEVICE && IPHONE && !$nolink && preg_match('/^([0-9\t\+-]+)/',$record['other_phone_number'],$args))
				return '<a href="tel:'.$args[1].'">'.$record['other_phone_number'].'</a>';
			return CRM_CommonCommon::get_dial_code($record['other_phone_number']);
		} else return self::display_phone($record,false,array('id'=>'phone'));
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
		$num = $record[$desc['id']];
		$contact = CRM_ContactsCommon::get_contact($record['contact']);
		$nr = '';
		switch ($num) {
			case 1: $nr = 'Mobile Phone'; break;
			case 2: $nr = 'Work Phone'; break;
			case 3: $nr = 'Home Phone'; break;
		}
		if (!$nr) return '';
		$id = strtolower(str_replace(' ','_',$nr));
		$l = Base_LangCommon::ts('CRM/PhoneCall',$nr);
		
		$number = $contact[$id];
		if($number && strpos($number,'+')===false) {
			if($contact['country']) {
				$calling_code = Utils_CommonDataCommon::get_value('Calling_Codes/'.$contact['country']);
				if($calling_code)
					$number = $calling_code.$number;
			}
		}

		if(MOBILE_DEVICE && IPHONE)
			return $l[0].': '.'<a href="tel:'.$number.'">'.$number.'</a>';
		return $l[0].': '.CRM_CommonCommon::get_dial_code($number);
	}
	public static function display_status($record, $nolink, $desc) {
		$prefix = 'crm_phonecall_leightbox';
		CRM_FollowupCommon::drawLeightbox($prefix);
		$v = $record[$desc['id']];
		if (!$v) $v = 0;
		$status = Utils_CommonDataCommon::get_translated_array('CRM/Status');
		if (!self::access_phonecall('edit', $record) && !Base_AclCommon::i_am_admin()) return $status[$v];
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
				Utils_AttachmentCommon::add('CRM/PhoneCall/'.$record['id'],0,Acl::get_user(),$note);
			}

			if ($action == 'set_in_progress') $v = 1;
			Utils_RecordBrowserCommon::update_record('phonecall', $record['id'], array('status'=>$v));
			if ($action == 'set_in_progress') location(array());

			$values = $record;
			$values['date_and_time'] = date('Y-m-d H:i:s');
			$values['title'] = Base_LangCommon::ts('CRM/PhoneCall','Follow up: ').$values['subject'];
			$values['status'] = 0;

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
		switch ($mode) {
		case 'display':
			$values['date_and_time'] = date('Y-m-d H:i:s');
			$values['subject'] = Base_LangCommon::ts('CRM/PhoneCall','Follow up: ').$values['subject'];
			$values['status'] = 0;
			$ret = array();
			if (ModuleManager::is_installed('CRM/Calendar')>=0) $ret['new_event'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM/PhoneCall','New Event')).' '.CRM_CalendarCommon::create_new_event_href(array('title'=>$values['subject'],'access'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'emp_id'=>$values['employees'],'cus_id'=>$values['contact'])).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Calendar','icon-small.png').'"></a>';
			if (ModuleManager::is_installed('CRM/Tasks')>=0) $ret['new_task'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM/PhoneCall','New Task')).' '.Utils_RecordBrowserCommon::create_new_record_href('task', array('title'=>$values['subject'],'permission'=>$values['permission'],'priority'=>$values['priority'],'description'=>$values['description'],'deadline'=>date('Y-m-d H:i:s', strtotime('+1 day')),'employees'=>$values['employees'], 'customers'=>$values['contact'])).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Tasks','icon-small.png').'"></a>';
			$ret['new_phonecall'] = '<a '.Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('CRM/PhoneCall','New Phonecall')).' '.Utils_RecordBrowserCommon::create_new_record_href('phonecall', $values).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_PhoneCall','icon-small.png').'"></a>';
			return $ret;
		case 'edit':
			$old_vals = Utils_RecordBrowserCommon::get_record('phonecall',$values['id']);
			$old_related = $old_vals['employees'];
			if (!isset($old_vals['other_contact'])) $old_related[] = $old_vals['contact'];
		case 'added':
			$related = $values['employees'];
			if (!isset($values['other_contact'])) $related[] = $values['contact'];
			foreach ($related as $v) {
				if ($mode==='edit' && in_array($v, $old_related)) continue;
				$subs = Utils_WatchdogCommon::get_subscribers('contact',$v);
				foreach($subs as $s)
					Utils_WatchdogCommon::user_subscribe($s, 'phonecall',$values['id']);
			}
			if ($mode=='added') break;
		case 'add':
			if (isset($values['other_contact']) && $values['other_contact']) {
				$values['other_phone']=1;
				$values['contact']='';
			} else {
				$values['other_contact_name']='';
			}
			if (isset($values['other_phone']) && $values['other_phone']) $values['phone']='';
			else $values['other_phone_number']='';
		}
		return $values;
	}

	public static function watchdog_label($rid = null, $events = array(), $details = true) {
		return Utils_RecordBrowserCommon::watchdog_label(
				'phonecall',
				Base_LangCommon::ts('CRM_PhoneCall','Phonecalls'),
				$rid,
				$events,
				'subject',
				$details
			);
	}

	//////////////////////////
	// mobile devices
	public function mobile_menu() {
		if(!Acl::is_user())
			return array();
		return array('Phone Calls'=>array('func'=>'mobile_phone_calls','color'=>'blue'));
	}
	
	public function mobile_phone_calls() {
		$me = CRM_ContactsCommon::get_my_record();
		Utils_RecordBrowserCommon::mobile_rb('phonecall',array('employees'=>array($me['id'])),array('status'=>'ASC', 'date_and_time'=>'ASC', 'subject'=>'ASC'));
	}
}
?>
