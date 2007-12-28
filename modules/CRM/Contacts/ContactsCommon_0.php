<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_ContactsCommon extends ModuleCommon {
	public static $paste_or_new = 'new';

	public static function get_contacts($crits = array()) {
		return Utils_RecordBrowserCommon::get_records('contact', $crits);
	}
	public static function get_companies($crits = array()) {
		return Utils_RecordBrowserCommon::get_records('company', $crits);
	}
	public static function get_contact_by_user_id($uid) {
		$rec = Utils_RecordBrowserCommon::get_records('contact', array('login'=>$uid));
		if (is_array($rec) && !empty($rec)) return array_shift($rec);
		else return null;
	}
	public static function get_contact($id) {
		return Utils_RecordBrowserCommon::get_record('contact', $id);
	}
	public static function get_company($id) {
		return Utils_RecordBrowserCommon::get_record('company', $id);
	}
	public static function get_main_company() {
		try {
			return Variable::get('main_company');
		} catch(NoSuchVariableException $e) {
			return null;
		}
	}
	private static function get_my_record() {
		static $me;
		if(!isset($me)) {
			$me = Utils_RecordBrowserCommon::get_records('contact', array('login'=>Acl::get_user()),false,true);
			if (is_array($me) && !empty($me)) $me = array_shift($me);
		}
		return $me;
	}
	public static function access_company($action, $param){
		$i = self::Instance();
		switch ($action) {
			case 'browse':	return $i->acl_check('browse companies');
			case 'view':	if ($i->acl_check('view company')) return true;
							$me = self::get_my_record();
							if ($me) return array('company_name'=>$me['company_name']);
							else return false;
			case 'edit':	$me = self::get_my_record();
					if ($me && in_array($param['id'],$me['company_name']) && $i->acl_check('edit my company')) return true; //my company
					return $i->acl_check('edit company');
			case 'delete':	return $i->acl_check('delete company');
			case 'edit_fields':
					if($i->acl_check('edit company')) return array();
					return array('company_name'=>false,'short_name'=>false,'group'=>false);
		}
		return false;
	}
	public static function access_contact($action, $param){
		$i = self::Instance();
		switch ($action) {
			case 'browse':	return $i->acl_check('browse contacts');
			case 'view':	if ($i->acl_check('view contact')) return true;
							else return array('login'=>Acl::get_user());
			case 'delete':	return $i->acl_check('delete contact');
			case 'edit':
					if($i->acl_check('edit contact')) return true;
					$me = self::get_my_record();
					if($me && $me['id']==$param['id']) return true; //me
					if($i->acl_check('edit my company contacts'))
						foreach($param['company_name'] as $cid)
							if(in_array($cid,$me['company_name'])) return true; //customer
					return false;
			case 'edit_fields':
					if($i->acl_check('edit contact')) return array();
					return array('company_name'=>false,'last_name'=>false,'first_name'=>false,'group'=>false);
		}
		return false;
	}

	/*--------------------------------------------------------------------*/
	public static function menu() {
		return array('CRM'=>array('__submenu__'=>1,'Contacts'=>array('mode'=>'contact','__icon__'=>'contacts.png'),'Companies'=>array('mode'=>'company','__icon__'=>'companies.png')));
	}
	public static function caption() {
		return 'Companies & Contacts';
	}
	public function admin_caption() {
		return 'Companies & Contacts';
	}

	public static function QFfield_company(&$form, $field, $label, $mode, $default) {
		$comp = array();
		if ($mode=='add' || $mode=='edit') {
			$ret = DB::Execute('SELECT * FROM company_data WHERE field=%s ORDER BY value', array('Company Name'));
			while ($row = $ret->FetchRow()) $comp[$row['company_id']] = $row['value'];
			$form->addElement('multiselect', $field, $label, $comp);
			if ($mode!=='add') $form->setDefaults(array($field=>$default));
			else {
				if (self::$paste_or_new=='new')
					$form->addElement('checkbox', 'create_company', 'Create new company', null, array('onClick'=>'document.getElementsByName("company_namefrom[]")[0].disabled=document.getElementsByName("company_nameto[]")[0].disabled=this.checked;'));
				else {
					$comp = self::get_company(self::$paste_or_new);
					$paste_company_info =
						'document.getElementsByName("address_1")[0].value="'.$comp['address_1'].'";'.
						'document.getElementsByName("address_2")[0].value="'.$comp['address_2'].'";'.
						'document.getElementsByName("work_phone")[0].value="'.$comp['phone'].'";'.
						'document.getElementsByName("fax")[0].value="'.$comp['fax'].'";'.
						'document.getElementsByName("city")[0].value="'.$comp['city'].'";'.
						'document.getElementsByName("postal_code")[0].value="'.$comp['postal_code'].'";'.
						'var country = $(\'country\');'.
						'var k = 0; while (k < country.options.length) if (country.options[k].value=="'.$comp['country'].'") break; else k++;'.
						'country.selectedIndex = k;'.
						'country.fire(\'e_u_cd:load\');'.
						'zone = $(\'zone\');'.
						'setTimeout("'.
						'k = 0; while (k < zone.options.length) if (zone.options[k].value==\''.$comp['zone'].'\') break; else k++;'.
						'zone.selectedIndex = k;'.
						'",900);'.
						'document.getElementsByName("web_address")[0].value="'.$comp['web_address'].'";';
					;
					$form->addElement('button', 'paste_company_info', 'Paste Company Info', array('onClick'=>$paste_company_info));
				}
			}
		} else {
			$form->addElement('static', $field, $label, array('id'=>$field));

			$def = '';
			$first = true;
			foreach($default as $k=>$v){
				if ($first) $first = false;
				else $def .= '<br>';
				$def .= Utils_RecordBrowserCommon::create_linked_label('company', 'company_name', $v);
			}
			$form->setDefaults(array($field=>$def));
		}
	}

	public static function QFfield_webaddress(&$form, $field, $label, $mode, $default) {
		if ($mode=='add' || $mode=='edit') {
			$form->addElement('text', $field, $label);
			if ($mode=='edit') $form->setDefaults(array($field=>$default));
		} else {
			$form->addElement('static', $field, $label);
			$form->setDefaults(array($field=>self::display_webaddress($default)));
		}
	}
	public static function QFfield_email(&$form, $field, $label, $mode, $default) {
		if ($mode=='add' || $mode=='edit') {
			$form->addElement('text', $field, $label);
			if ($mode=='edit') $form->setDefaults(array($field=>$default));
		} else {
			$form->addElement('static', $field, $label);
			$form->setDefaults(array($field=>self::display_email($default)));
		}
	}
	public static function QFfield_login(&$form, $field, $label, $mode, $default) {
		$ret = DB::Execute('SELECT id, login FROM user_login ORDER BY login');
		$users = array(''=>'--');
		while ($row=$ret->FetchRow()) {
			if (DB::GetOne('SELECT contact_id FROM contact_data WHERE field=\'Login\' AND value=%d', array($row['id']))===false || $row['id']===$default)
				$users[$row['id']] = $row['login'];
		}
		$form->addElement('select', $field, $label, $users);
		$form->setDefaults(array($field=>$default));
		if (!Base_AclCommon::i_am_admin()) $form->freeze($field);
	}
	public static function display_fname($v, $i) {
		return Utils_RecordBrowserCommon::create_linked_label('contact', 'first_name', $i);
	}
	public static function display_lname($v, $i) {
		return Utils_RecordBrowserCommon::create_linked_label('contact', 'last_name', $i);
	}
	public static function display_cname($v, $i) {
		return Utils_RecordBrowserCommon::create_linked_label('company', 'company_name', $i);
	}
	public static function display_webaddress($v) {
		$v = trim($v, ' ');
		if ($v=='') return '';
		if (strpos($v, 'http://')==false && $v) $v = 'http://'.$v;
		return '<a href="'.$v.'" target="_blank">'.$v.'</a>';
	}
	public static function display_email($v) {
		return '<a href="mailto:'.$v.'">'.$v.'</a>';
	}
	public static function display_login($v) {
		if (!$v)
			return '--';
		else
			return Base_UserCommon::get_user_login($v);
	}
	public static function submit_contact($values, $mode) {
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
