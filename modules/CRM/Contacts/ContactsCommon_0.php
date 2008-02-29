<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_ContactsCommon extends ModuleCommon {
	public static $paste_or_new = 'new';

	public static function get_contacts($crits = array(), $cols = array()) {
		return Utils_RecordBrowserCommon::get_records('contact', $crits, $cols);
	}
	public static function get_companies($crits = array(), $cols = array()) {
		return Utils_RecordBrowserCommon::get_records('company', $crits, $cols);
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
	public static function get_my_record() {
		static $me;
		if(!isset($me)) {
			$me = Utils_RecordBrowserCommon::get_records('contact', array('login'=>Acl::get_user()));
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
	public static function admin_caption() {
		return 'Main Company';
	}
	public static function crm_company_datatype($field = array()) {
		$field['QFfield_callback'] = array('CRM_ContactsCommon', 'QFfield_company');
		$field['display_callback'] = array('CRM_ContactsCommon', 'display_company');
		$field['type'] = $field['param']['field_type'];
		unset($field['param']['field_type']);
		if (isset($field['param']['crits'])) $field['param'] = implode('::',$field['param']['crits']);
		else $field['param'] = '';
		return $field;
	}
	public static function crm_contact_datatype($field = array()) {
		$field['QFfield_callback'] = array('CRM_ContactsCommon', 'QFfield_contact');
		$field['display_callback'] = array('CRM_ContactsCommon', 'display_contact');
		$field['type'] = $field['param']['field_type'];
		unset($field['param']['field_type']);
		if (isset($field['param']['format'])) $param = implode('::',$field['param']['format']);
		else $param = '::';
		if (isset($field['param']['crits'])) $param .= ';'.implode('::',$field['param']['crits']);
		else $param .= ';::';
		$field['param'] = $param;
		return $field;
	}

	public static function display_contact($record, $i, $nolink, $desc) {
		$v = $record[$desc['id']];
		$def = '';
		$first = true;
		$param = explode(';',$desc['param']);
		if ($param[0] == '::') $callback = array('CRM_ContactsCommon', 'contact_format_default');
		else $callback = explode('::', $param[0]);
		if (!is_array($v)) $v = array($v);
		foreach($v as $k=>$w){
			if ($w=='') break;
			if ($first) $first = false;
			else $def .= ', ';
			$def .= call_user_func($callback, self::get_contact($w), $nolink);
		}
		if (!$def) 	$def = '--';
		return $def;
	}
	public static function contact_format_default($record, $nolink){
		$ret = '';
		if (!$nolink) $ret .= Utils_RecordBrowserCommon::record_link_open_tag('contact', $record['id']);
		$ret .= $record['last_name'].(isset($record['first_name'][0])?' '.$record['first_name'][0].'.':'');
		if (!$nolink) $ret .= Utils_RecordBrowserCommon::record_link_close_tag();
		if (isset($record['company_name'][0])) $ret .= ' ['.Utils_RecordBrowserCommon::create_linked_label('company', 'Company Name', $record['company_name'][0], $nolink).']';
		return $ret;
	}
	public static function contact_format_no_company($record, $nolink){
		$ret = '';
		if (!$nolink) $ret .= Utils_RecordBrowserCommon::record_link_open_tag('contact', $record['id']);
		$ret .= $record['last_name'].(($record['first_name']!=='')?' '.$record['first_name']:'');
		if (!$nolink) $ret .= Utils_RecordBrowserCommon::record_link_close_tag();
		return $ret;
	}
	public static function contacts_chainedselect_crits($default, $desc, $format_func, $ref_field){
		Utils_ChainedSelectCommon::create($desc['id'],array($ref_field),'modules/CRM/Contacts/update_contact.php', array('format'=>implode('::', $format_func), 'required'=>$desc['required']), $default);
		return null;
	}
	public static function QFfield_contact(&$form, $field, $label, $mode, $default, $desc) {
		$cont = array();
		$param = explode(';',$desc['param']);
		if ($mode=='add' || $mode=='edit') {
			if ($param[0] == '::') $callback = array('CRM_ContactsCommon', 'contact_format_default');
			else $callback = explode('::', $param[0]);
			if ($param[1] != '::') {
				$crit_callback = explode('::',$param[1]);
				if ($crit_callback[0]=='ChainedSelect') {
					$crits = null;
					self::contacts_chainedselect_crits($default, $desc, $callback, $crit_callback[1]);
				} else {
					$crits = call_user_func($crit_callback, false);
//					$adv_crits = call_user_func($crit_callback, true);
				}
			} else $crits = array();
			if ($crits!==null) {
				$contacts = self::get_contacts($crits);
				if (!$desc['required'] && $desc['type']!='multiselect') $cont[''] = '--';
				foreach ($contacts as $v) $cont[$v['id']] = call_user_func($callback, $v, true);
				asort($cont);
			} else $cont = array();
/*			if ($adv_crits!==null) {
				$rpicker = $this->init_module('Utils/RecordBrowser/RecordPicker');
				$this->display_module($rpicker, array('contact', $field, $callback, $adv_crits, array('work_phone'=>false, 'mobile_phone'=>false, 'zone'=>false), array('last_name'=>'ASC')));
				$label .= $rpicker->create_open_link($this->lang->t('More..'));
			}*/
			$form->addElement($desc['type'], $field, $label, $cont, array('id'=>$field));
			if ($mode!=='add') $form->setDefaults(array($field=>$default));
		} else {
			$form->addElement('static', $field, $label, array('id'=>$field));
			$def = '';
			$first = true;
			if (!is_array($default)) $default = array($default);
			foreach($default as $k=>$v){
				if ($v=='') break;
				if ($first) $first = false;
				else $def .= ', ';
				$def .= self::display_contact(array($desc['id']=>$v), $v, false, $desc);
			}
			if (!$def) 	$def = '--';
			$form->setDefaults(array($field=>$def));
		}
	}

	public static function display_company($record, $i, $nolink, $desc) {
		$v = $record[$desc['id']];
		$def = '';
		$first = true;
		if (!is_array($v)) $v = array($v);
		foreach($v as $k=>$w){
			if ($w=='') break;
			if ($first) $first = false;
			else $def .= ', ';
			$def .= Utils_RecordBrowserCommon::create_linked_label('company', 'Company Name', $w, $nolink);
		}
		if (!$def) 	$def = '--';
		return $def;
	}
	public static function QFfield_company(&$form, $field, $label, $mode, $default, $desc) {
		$comp = array();
		if ($mode=='add' || $mode=='edit') {
			if ($desc['param'] != '') $crits = call_user_func(explode('::',$desc['param']));
			else $crits = array();
			$companies = self::get_companies($crits);
			if (!$desc['required'] && $desc['type']!='multiselect') $comp[''] = '--';
			foreach ($companies as $v) $comp[$v['id']] = $v['company_name'];
			asort($comp);
			$form->addElement($desc['type'], $field, $label, $comp, array('id'=>$field));
			if ($mode!=='add') $form->setDefaults(array($field=>$default));
		} else {
			$form->addElement('static', $field, $label, array('id'=>$field));
			$def = '';
			$first = true;
			if (!is_array($default)) $default = array($default);
			foreach($default as $k=>$v){
				if ($v=='') break;
				if ($first) $first = false;
				else $def .= ', ';
				$def .= Utils_RecordBrowserCommon::create_linked_label('company', 'Company Name', $v);
			}
			if (!$def) 	$def = '--';
			$form->setDefaults(array($field=>$def));
		}
	}

	public static function QFfield_webaddress(&$form, $field, $label, $mode, $default) {
		if ($mode=='add' || $mode=='edit') {
			$form->addElement('text', $field, $label);
			if ($mode=='edit') $form->setDefaults(array($field=>$default));
		} else {
			$form->addElement('static', $field, $label);
			$form->setDefaults(array($field=>self::display_webaddress(array('webaddress'=>$default), null, null, array('id'=>'webaddress'))));
		}
	}
	public static function QFfield_email(&$form, $field, $label, $mode, $default) {
		if ($mode=='add' || $mode=='edit') {
			$form->addElement('text', $field, $label);
			if ($mode=='edit') $form->setDefaults(array($field=>$default));
		} else {
			$form->addElement('static', $field, $label);
			$form->setDefaults(array($field=>self::display_email(array('email'=>$default), null, null, array('id'=>'email'))));
		}
	}
	public static function QFfield_login(&$form, $field, $label, $mode, $default) {
		if ($mode=='add'){
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
	public static function display_fname($v, $i, $nolink) {
		return Utils_RecordBrowserCommon::create_linked_label('contact', 'First Name', $i, $nolink);
	}
	public static function display_lname($v, $i, $nolink) {
		return Utils_RecordBrowserCommon::create_linked_label('contact', 'Last Name', $i, $nolink);
	}
	public static function display_cname($v, $i, $nolink) {
		return Utils_RecordBrowserCommon::create_linked_label('company', 'Company Name', $i, $nolink);
	}
	public static function display_webaddress($record, $i, $nolink, $desc) {
		$v = $record[$desc['id']];
		$v = trim($v, ' ');
		if ($v=='') return '';
		if (strpos($v, 'http://')==false && $v) $v = 'http://'.$v;
		return '<a href="'.$v.'" target="_blank">'.$v.'</a>';
	}
	public static function display_email($record, $i, $nolink, $desc) {
		$v = $record[$desc['id']];
		return '<a href="mailto:'.$v.'">'.$v.'</a>';
	}
	public static function display_login($record, $i, $nolink, $desc) {
		$v = $record[$desc['id']];
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

	public static function search($word){
		$ret = array();
		if(self::Instance()->acl_check('browse contacts')) {
			$result = self::get_contacts(array('"first_name'=>DB::Concat('\'%\'',DB::qstr($word),'\'%\'')));

	 		foreach ($result as $row)
 				$ret['Contact #'.$row['id'].', '.$row['first_name'].' '.$row['last_name']] = Utils_RecordBrowserCommon::get_record_href_array('contact',$row['id']);
	 		
			$result = self::get_contacts(array('"last_name'=>DB::Concat('\'%\'',DB::qstr($word),'\'%\'')));

	 		foreach ($result as $row)
 				$ret['Contact #'.$row['id'].', '.$row['first_name'].' '.$row['last_name']] = Utils_RecordBrowserCommon::get_record_href_array('contact',$row['id']);
 		}
		if(self::Instance()->acl_check('browse companies')) {
			$result = self::get_companies(array('"company_name'=>DB::Concat('\'%\'',DB::qstr($word),'\'%\'')));

	 		foreach ($result as $row)
 				$ret['Company #'.$row['id'].', '.$row['company_name']] = Utils_RecordBrowserCommon::get_record_href_array('company',$row['id']);
	 		
			$result = self::get_companies(array('"short_name'=>DB::Concat('\'%\'',DB::qstr($word),'\'%\'')));

	 		foreach ($result as $row)
 				$ret['Company #'.$row['id'].', '.$row['company_name']] = Utils_RecordBrowserCommon::get_record_href_array('company',$row['id']);
 		}
		return $ret;
	}


}
?>
