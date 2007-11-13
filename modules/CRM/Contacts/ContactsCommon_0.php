<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_ContactsCommon extends ModuleCommon {
	public static function get_contacts($crits) {
		return Utils_RecordBrowserCommon::get_records('contact', $crits);
	}
	public static function get_contact_by_user_id($uid) {
		$rec = Utils_RecordBrowserCommon::get_records('contact', array('login'=>$uid));
		if (is_array($rec) && !empty($rec)) return array_shift($rec);
		else return null;
	}
	public static function get_contact($id) {
		return Utils_RecordBrowserCommon::get_record('contact', $id);
	}
	
	/*--------------------------------------------------------------------*/
	public static function menu() {
		return array('CRM'=>array('__submenu__'=>1,'Contacts'=>array('mode'=>'contact'),'Companies'=>array('mode'=>'company')));
	}
	public static function caption() {
		return 'Companies & Contacts';
	}
	public function admin_caption() {
		return 'Companies & Contacts';	
	}
	public static function QFfield_country(&$form, $field, $label, $mode, $default) {
		$form->addElement('commondata', $field, $label, array('Countries'), array('empty_option'=>true));
		if ($mode!=='add') $form->setDefaults(array($field=>$default));
		else $form->setDefaults(array($field=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_country')));
	}
	public static function QFfield_zone(&$form, $field, $label, $mode, $default) {
		$form->addElement('commondata', $field, $label, array('Countries', 'country'), array('empty_option'=>true));
		if ($mode!=='add') $form->setDefaults(array($field=>$default));
		else $form->setDefaults(array($field=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_state')));	
	}
	public static function QFfield_company(&$form, $field, $label, $mode, $default) {
		$comp = array();
		$col='Name';
		if ($mode=='add' || $mode=='edit') {
			$ret = DB::Execute('SELECT * FROM company_data WHERE field=%s ORDER BY value', array($col));
			while ($row = $ret->FetchRow()) $comp[$row['company_id']] = $row['value'];
			$form->addElement('multiselect', $field, $label, $comp);
			if ($mode!=='add') $form->setDefaults(array($field=>$default));
			else {
				$form->addElement('checkbox', 'create_company', 'Create new company', null, array('onClick'=>'document.getElementsByName("companyfrom[]")[0].disabled=document.getElementsByName("companyto[]")[0].disabled=this.checked;'));
			}
		} else {
			$form->addElement('static', $field, $label, array('id'=>$field));
			
			$def = '';
			$first = true;
			foreach($default as $k=>$v){
				if ($first) $first = false;
				else $def .= '<br>';
				$def .= Utils_RecordBrowserCommon::create_linked_label('company', $col, $v);
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
		if (Base_AclCommon::i_am_admin()) {
			$ret = DB::Execute('SELECT id, login FROM user_login ORDER BY login');
			$users = array(''=>'--');
			while ($row=$ret->FetchRow()) {
				if (DB::GetOne('SELECT contact_id FROM contact_data WHERE field=\'Login\' AND value=%d', array($row['id']))===false || $row['id']===$default)
					$users[$row['id']] = $row['login'];
			}
			$form->addElement('select', $field, $label, $users);
			$form->setDefaults(array($field=>$default));
		} else {
			$form->addElement('static', $field, $label);
			$form->setDefaults(array($field=>self::display_login($default)));
		}
	}
	public static function display_webaddress($v) {
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
				array(	'name'=>$values['first_name'].' '.$values['last_name'],
						'address_1'=>$values['address_1'],
						'address_2'=>$values['address_2'],
						'country'=>$values['country'],
						'zone'=>isset($values['zone'])?$values['zone']:'',
						'postal_code'=>$values['postal_code'],
						'phone'=>$values['phone'],
						'fax'=>$values['fax'],
						'web_address'=>$values['web_address'])
			);
			$values['company'] = array($comp_id);
		}
		if ($values['email']=='' && $values['login']!=0 && $mode=='add')
			$values['email'] = DB::GetOne('SELECT mail FROM user_password WHERE user_login_id=%d', array($values['login']));
		return $values;
	}
}
?>