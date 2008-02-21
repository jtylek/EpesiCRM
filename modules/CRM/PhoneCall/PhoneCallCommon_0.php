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
	public function admin_caption() {
		return 'Phone Calls';
	}

	public static function QFfield_phone(&$form, $field, $label, $mode, $default) {
		if ($mode=='add' || $mode=='edit') {
			$phone_numbers = CRM_ContactsCommon::get_contacts(array(), array('Work Phone', 'Home Phone', 'Mobile Phone'));
			$js = 	'Event.observe(\'contact\',\'change\', onchange_contact_phone);'.
					'function onchange_contact_phone() {'.
					'contact = document.forms[\''.$form->getAttribute('name').'\'].contact.value;'.
					'phone = \'\';'.
					'switch(contact){';
			foreach($phone_numbers as $k=>$v) {
				$phone = $v['mobile_phone'];
				if (!$phone) $phone = $v['work_phone'];
				if (!$phone) $phone = $v['home_phone'];
				if ($phone) $js .= 'case"'.$k.'":phone=\''.$phone.'\';break;';
			}
			$js .= 	'}'.
					'document.forms[\''.$form->getAttribute('name').'\'].phone.value = phone;'.
					'};';
			eval_js($js);
			$form->addElement('text', $field, $label);
			if ($mode=='edit') $form->setDefaults(array($field=>$default));
		} else {
			$form->addElement('static', $field, $label);
			$form->setDefaults(array($field=>$default));
		}
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
		if (isset($values['create_company'])) {
			$comp_id = Utils_RecordBrowserCommon::new_record('company',
				array(	'company_name'=>$values['first_name'].' '.$values['last_name'],
						'address_1'=>$values['address_1'],
						'address_2'=>$values['address_2'],
						'country'=>$values['country'],
						'city'=>$values['city'],
						'zone'=>isset($values['zone'])?$values['zone']:'',
						'postal_code'=>$values['postal_code'],
						'phone'=>$values['work_phone'],
						'fax'=>$values['fax'],
						'web_address'=>$values['web_address'])
			);
			$values['company_name'] = array($comp_id);
		}
		if ($values['email']=='' && $values['login']!=0 && $mode=='add')
			$values['email'] = DB::GetOne('SELECT mail FROM user_password WHERE user_login_id=%d', array($values['login']));
		return $values;
	}
}
?>
