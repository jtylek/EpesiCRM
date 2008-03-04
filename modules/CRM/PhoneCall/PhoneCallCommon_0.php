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
		return 	Base_LangCommon::ts('CRM_PhoneCall','Description: %s', array($r['description'])).'<br>'.
				Base_LangCommon::ts('CRM_PhoneCall','Contact: %s', array($contact)).'<br>'.
				Base_LangCommon::ts('CRM_PhoneCall','Phone: %s', array($phone)).'<br>'.
				Base_LangCommon::ts('CRM_PhoneCall','Date and Time: %s', array(Base_RegionalSettingsCommon::time2reg($r['date_and_time'])));
	}
	public static function get_phonecalls($crits = array(), $cols = array()) {
		return Utils_RecordBrowserCommon::get_records('phonecall', $crits, $cols);
	}
	public static function get_phonecall($id) {
		return Utils_RecordBrowserCommon::get_record('phonecall', $id);
	}
	public static function access_phonecall($action, $param){
		$i = self::Instance();
		switch ($action) {
			case 'browse':	
							return $i->acl_check('browse phonecalls');
			case 'view':	
							if ($i->acl_check('view phonecall')) return true;
			case 'edit':	
							if ($i->acl_check('edit phonecall')) return true;
							$me = CRM_ContactsCommon::get_my_record();
							if (in_array($me['id'], $param['employees'])) return true;
							if ($me['id']==$param['contact']) return true;
							$info = Utils_RecordBrowserCommon::get_record_info('phonecall',$param['id']);
							if ($me['login']==$info['created_by']) return true;
							return false;
			case 'delete':	
							if ($i->acl_check('delete phonecall')) return true;
							$me = CRM_ContactsCommon::get_my_record();
							if (in_array($me['id'], $param['employees'])) return true;
							if ($me['id']==$param['contact']) return true;
							$info = Utils_RecordBrowserCommon::get_record_info('phonecall',$param['id']);
							if ($me['login']==$info['created_by']) return true;
							return false;
			case 'edit_fields':
							if ($i->acl_check('edit phonecall')) return array();
							return array();
		}
		return false;
	}
	/*--------------------------------------------------------------------*/
	public static function phonecall_employees_crits(){
		return array('company_name'=>array(CRM_ContactsCommon::get_main_company()));
	}
	public static function phonecall_contact_crits(){
		return array('|:Fav'=>true, '|:Recent'=>true);
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
//					'Event.observe(\'phone\',\'change\', onchange_other_phone);'.
					'function enable_disable_phone(arg) {'.
					'phone = document.forms[\''.$form->getAttribute('name').'\'].phone;'.
					'o_phone = document.forms[\''.$form->getAttribute('name').'\'].other_phone_number;'.
					'if (arg) {phone.disable();o_phone.enable();} else {if(phone.length!=0)phone.enable();o_phone.disable();}'.
					'};'.
					'function onchange_other_phone() {'.
					'c_phone = document.forms[\''.$form->getAttribute('name').'\'].other_phone;'.
					'enable_disable_phone(c_phone.checked);'.
					'};'.
					'enable_disable_phone('.$default.');';
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
					'enable_disable_contact('.$default.');';
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
			$form->setDefaults(array($field=>self::display_phone(array($desc['id']=>$default), null, false, $desc)));
		}
	}
    public static function display_subject($record, $i) {
		$ret = Utils_RecordBrowserCommon::create_linked_label('phonecall', 'Subject', $i);
		if (isset($record['description']) && $record['description']!='') $ret = '<span '.Utils_TooltipCommon::open_tag_attrs($record['description'], false).'>'.$ret.'</span>';
		return $ret;
	}
	public static function display_phone_number($record, $id, $nolink, $desc) {
		if ($record['other_phone']) return $record['other_phone_number'];
		else return self::display_phone(array('phone'=>$record['phone']),null,null,array('id'=>'phone'));
	}
	public static function display_contact_name($record, $id, $nolink, $desc) {
		if ($record['other_contact']) return $record['other_contact_name'];
		if ($record['contact']=='') return '--';
		$ret = '';
		if (!$nolink) $ret .= Utils_RecordBrowserCommon::record_link_open_tag('contact', $record['contact']);
		$cont = CRM_ContactsCommon::get_contact($record['contact']);
		$ret .= $cont['last_name'].(($cont['first_name']!=='')?' '.$cont['first_name']:'');
		if (!$nolink) $ret .= Utils_RecordBrowserCommon::record_link_close_tag();
		return $ret;
	}
	public static function display_phone($record, $id, $nolink, $desc) {
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
	public static function display_status($record, $id, $nolink, $desc) {
		$v = $record[$desc['id']];
		$status = Utils_CommonDataCommon::get_array('Ticket_Status');
		if (isset($_REQUEST['increase_phonecall_status'])) {
			if ($_REQUEST['increase_phonecall_status']==$id) $v++;
			Utils_RecordBrowserCommon::update_record('phonecall', $id, array('status'=>$v));
			location(array());
		}
		if ($v==2 || !self::access_phonecall('edit', self::get_phonecall($id))) return $status[$v];
		else return '<a '.
					Module::create_href(array('increase_phonecall_status'=>$id)).
					'>'.$status[$v].'</a>';
	}
	public static function submit_phonecall($values, $mode) {
		if (isset($values['other_contact'])) {
			$values['other_phone']=1;
			$values['contact']='';
		}
		if (isset($values['other_phone'])) $values['phone']='';
		else $values['other_phone_number']='';
		return $values;
	}
}
?>
