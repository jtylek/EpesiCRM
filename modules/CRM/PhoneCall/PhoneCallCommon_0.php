<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_PhoneCallCommon extends ModuleCommon {
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
	public static function QFfield_phone(&$form, $field, $label, $mode, $default, $desc) {
		if ($mode=='add' || $mode=='edit') {
			$phone_numbers = CRM_ContactsCommon::get_contacts(array(), array('Work Phone', 'Home Phone', 'Mobile Phone'));
			$js = 	
					'Event.observe(\'other_phone\',\'change\', onchange_other_phone);'.
					'function onchange_other_phone() {'.
					'phone = document.forms[\''.$form->getAttribute('name').'\'].phone;'.
					'o_phone = document.forms[\''.$form->getAttribute('name').'\'].other_phone_number;'.
					'c_phone = document.forms[\''.$form->getAttribute('name').'\'].other_phone;'.
					'if (c_phone.checked) {phone.disable();o_phone.enable();} else {phone.enable();o_phone.disable();}'.
					'};'.
					'onchange_other_phone();';
			eval_js($js);
			$form->addElement('select', $field, $label, array(), array('id'=>$field));
			Utils_ChainedSelectCommon::create($field, array('company_name','contact'),'modules/CRM/PhoneCall/update_phones.php',null,$default);
			print($default);
			if ($mode=='edit') $form->setDefaults(array($field=>$default));
		} else {
			$form->addElement('static', $field, $label);
			$form->setDefaults(array($field=>self::display_phone(array($desc['id']=>$default), null, false, $desc)));
		}
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
		return '['.Base_LangCommon::ts('CRM/PhoneCall',$nr).'] '.$contact[$id];
	}
	public static function display_status($record, $id, $nolink, $desc) {
		$v = $record[$desc['id']];
		$status = Utils_CommonDataCommon::get_array('Ticket_Status');
		if (isset($_REQUEST['increase_phonecall_status'])) {
			if ($_REQUEST['increase_phonecall_status']==$id) $v++;
			Utils_RecordBrowserCommon::update_record('phonecall', $id, array('status'=>$v));
		}
		if ($v==2 || !self::access_phonecall('edit', self::get_phonecall($id))) return $status[$v];
		else return '<a '.
					Module::create_href(array('increase_phonecall_status'=>$id)).
					'>'.$status[$v].'</a>';
	}
	public static function submit_phonecall($values, $mode) {
		if (isset($values['other_phone'])) $values['phone']='';
		else $values['other_phone_number']='';
		return $values;
	}
}
?>
