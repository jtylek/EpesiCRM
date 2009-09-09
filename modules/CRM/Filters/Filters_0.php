<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage filters
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Filters extends Module {
	private $contacts_select;

	public function body() {
		$th = $this->init_module('Base/Theme');
		$display_settings = Base_User_SettingsCommon::get('Base/ActionBar','display');
		$display_icon = ($display_settings == 'both' || $display_settings == 'icons only');
		$display_text = ($display_settings == 'both' || $display_settings == 'text only');
		$th->assign('display_icon',$display_icon);
		$th->assign('display_text',$display_text);

		eval_js_once('crm_filters_deactivate = function(){leightbox_deactivate(\'crm_filters\');}');

		$th->assign('my','<a '.$this->create_callback_href(array($this,'set_profile'),'my').' id="crm_filters_my">'.$this->t('My records').'</a>');
		eval_js('Event.observe(\'crm_filters_my\',\'click\', crm_filters_deactivate)');

		$th->assign('all','<a '.$this->create_callback_href(array($this,'set_profile'),'all').' id="crm_filters_all">'.$this->t('All records').'</a>');
		eval_js('Event.observe(\'crm_filters_all\',\'click\', crm_filters_deactivate)');

		$th->assign('manage','<a '.$this->create_callback_href(array($this,'manage_filters')).' id="crm_filters_manage">'.$this->t('Manage filters').'</a>');
		eval_js('Event.observe(\'crm_filters_manage\',\'click\', crm_filters_deactivate)');

		$ret = DB::Execute('SELECT id,name,description FROM crm_filters_group WHERE user_login_id=%d',array(Acl::get_user()));
		$filters = array();
		while($row = $ret->FetchRow()) {
			$filters[] = array('title'=>$row['name'],'description'=>'','open'=>'<a '.Utils_TooltipCommon::open_tag_attrs($row['description'],false).' '.$this->create_callback_href(array($this,'set_profile'),$row['id']).' id="crm_filters_'.$row['id'].'">','close'=>'</a>');
			eval_js('Event.observe(\'crm_filters_'.$row['id'].'\',\'click\', crm_filters_deactivate)');
		}
		$th->assign('filters',$filters);

		$qf = $this->init_module('Libs/QuickForm');
		$contacts = CRM_ContactsCommon::get_contacts(array('company_name'=>CRM_ContactsCommon::get_main_company()),array('first_name','last_name'),array('last_name'=>'ASC','first_name'=>'ASC'));
		$this->contacts_select = array(''=>'---');
		foreach($contacts as $v)
			$this->contacts_select[$v['id']] = $v['last_name'].' '.$v['first_name'];
		$qf->addElement('select','contact',$this->t('Records of'),$this->contacts_select,array('onChange'=>'if(this.value!=\'\'){'.$qf->get_submit_form_js().'crm_filters_deactivate();}'));
		if($qf->validate()) {
			$c = $qf->exportValue('contact');
			$this->set_profile('c'.$c);
			location(array());
		}
		$th->assign('saved_filters',$this->t('Saved Filters'));
		$th->assign('contacts',$qf->toHtml());

		ob_start();
		$th->display();
		$profiles_out = ob_get_clean();

		Libs_LeightboxCommon::display('crm_filters',$profiles_out,$this->t('Filters'));
		if(!$this->isset_module_variable('profile_desc'))
			$this->set_profile($this->get_default_filter());
		    
		//Base_ActionBarCommon::add('folder','Filters','class="lbOn" rel="crm_filters"',$this->get_module_variable('profile_desc',$this->t('My records')));
		print($this->t('%s',array('<a class="lbOn" rel="crm_filters">'.$this->ht('Filters: ').'<b>'.$this->get_module_variable('profile_desc').'</b></a>')));
	}

	public function manage_filters() {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main($this->get_type(),'edit');
	}

	public function set_profile($prof) {
		if(preg_match('/^c([0-9]+)$/',$prof,$reqs)) {
			$ret = $reqs[1];
			$desc = $this->contacts_select[$reqs[1]];
		} elseif(is_numeric($prof)) {
			$cids = DB::GetAssoc('SELECT contact_id, contact_id FROM crm_filters_contacts');
			foreach ($cids as $v) {
				$c = CRM_ContactsCommon::get_contact($v);
				if (!in_array(CRM_ContactsCommon::get_main_company(), $c['company_name'])) {
					DB::Execute('DELETE FROM crm_filters_contacts WHERE contact_id=%d',array($v));
				}
			}
			$c = DB::GetCol('SELECT p.contact_id FROM crm_filters_contacts p WHERE p.group_id=%d',array($prof));
			if($c)
				$ret = implode(',',$c);
			else
				$ret = '-1';
			$desc = DB::GetOne('SELECT name FROM crm_filters_group WHERE id=%d',array($prof));
		} elseif($prof=='my') {
			$ret = CRM_FiltersCommon::get_my_profile();
			$desc = $this->t('My records');
		} else {//all and undefined
			$contacts = Utils_RecordBrowserCommon::get_records('contact', array('company_name'=>CRM_ContactsCommon::get_main_company()));
			$contacts_select = array();
			foreach($contacts as $v)
				$contacts_select[] = $v['id'];
			if($contacts_select)
				$ret = implode(',',$contacts_select);
			else
				$ret = '-1';

			$desc = $this->t('All records');
		}
//		$this->set_module_variable('profile',$ret);
		$_SESSION['client']['filter_'.Acl::get_user()] = $ret;
		$this->set_module_variable('profile_desc',$desc);
		location(array());
	}

/*	public function get() {
		if(!$this->isset_module_variable('profile'))
			$this->set_module_variable('profile',CRM_FiltersCommon::get_my_profile());
		$ret = $this->get_module_variable('profile');
		return '('.$ret.')';
	}

	public function get_description() {
		return $this->get_module_variable('profile_desc');
	}*/

	public function edit() {
		Base_ActionBarCommon::add('add','Add group',$this->create_callback_href(array($this,'edit_group')));

		$gb = $this->init_module('Utils/GenericBrowser',null,'edit');

		$gb->set_table_columns(array(
				array('name'=>$this->t('Name'), 'width'=>20, 'order'=>'g.name'),
				array('name'=>$this->t('Description'), 'width'=>30, 'order'=>'g.description'),
				array('name'=>$this->t('Users in category'), 'width'=>50, 'order'=>'')
				));

		$def_opts = array('my'=>$this->ht('My records'), 'all'=>$this->ht('All records'));
		$contacts = CRM_ContactsCommon::get_contacts(array('company_name'=>CRM_ContactsCommon::get_main_company()),array('first_name','last_name'),array('last_name'=>'ASC','first_name'=>'ASC'));
		foreach($contacts as $v)
			$def_opts['c'.$v['id']] = $v['last_name'].' '.$v['first_name'];

		$ret = DB::Execute('SELECT g.name,g.id,g.description FROM crm_filters_group g WHERE g.user_login_id='.Acl::get_user());
		while($row = $ret->FetchRow()) {
			$def_opts[$row['id']] = $row['name'];
		
			$gb_row = & $gb->get_new_row();
			$gb_row->add_action($this->create_confirm_callback_href($this->ht('Delete this group?'),array('CRM_Filters','delete_group'), $row['id']),'Delete');
			$gb_row->add_action($this->create_callback_href(array($this,'edit_group'),$row['id']),'Edit');
			$cids = DB::GetAssoc('SELECT c.contact_id, c.contact_id FROM crm_filters_contacts c WHERE c.group_id=%d',array($row['id']));
			$users = array();
			foreach ($cids as $v)
				$users[] = CRM_ContactsCommon::contact_format_no_company(CRM_ContactsCommon::get_contact($v),true);
			$gb_row->add_data($row['name'], $row['description'], implode(', ',$users));
		}

		$this->display_module($gb);
		
		$qf = $this->init_module('Libs/QuickForm',null,'default_filter');
		$qf->addElement('select','def_filter',$this->t('Default filter'),$def_opts,array('onChange'=>$qf->get_submit_form_js()));
		$qf->addRule('def_filter',$this->t('Field required'),'required');
		$qf->setDefaults(array('def_filter'=>$this->get_default_filter($def_filter_exists)));
		if($qf->validate()) {
		    $vals = $qf->exportValues();
		    if($def_filter_exists)
			DB::Execute('UPDATE crm_filters_default SET filter=%s WHERE user_login_id=%d',array($vals['def_filter'],Acl::get_user()));
		    else
			DB::Execute('INSERT INTO crm_filters_default(filter,user_login_id) VALUES (%s, %d)',array($vals['def_filter'],Acl::get_user()));
		}
		$qf->display();
	}
	
	private function get_default_filter(& $def_filter_exists = false) {
	    $def = DB::GetOne('SELECT filter FROM crm_filters_default WHERE user_login_id=%d',array(Acl::get_user()));
	    if(!$def) {
		$def_filter_exists = false;
		$def = 'my';
	    } else {
		$def_filter_exists = true;
	    }
	    return $def;
	}

	public function edit_group($id=null) {
		if($this->is_back()) return false;

		$form = $this->init_module('Libs/QuickForm', null, 'edit_group');
		if(isset($id)) {
			$name = DB::GetOne('SELECT name FROM crm_filters_group WHERE id=%d',array($id));
			$description = DB::GetOne('SELECT description FROM crm_filters_group WHERE id=%d',array($id));
			$form->addElement('header',null,$this->t('Edit group "%s"',array($name)));

			$contacts_def = DB::GetCol('SELECT contact_id FROM crm_filters_contacts WHERE group_id=%d',array($id));

			$form->setDefaults(array('name'=>$name,'contacts'=>$contacts_def,'description'=>$description));
		} else
			$form->addElement('header',null,$this->t('New group'));
		$form->addElement('text','name',$this->t('Name'));
		$form->addElement('text','description',$this->t('Description'));
		$form->addRule('name',$this->t('Max length of field exceeded'),'maxlength',128);
		$form->addRule('description',$this->t('Max length of field exceeded'),'maxlength',256);
		$form->addRule('name',$this->t('Field required'),'required');
		$form->registerRule('unique','callback','check_group_name_exists', 'CRM_Filters');
		$form->addRule('name',$this->t('Group with this name already exists'),'unique',$id);
		$contacts = CRM_ContactsCommon::get_contacts(array('company_name'=>CRM_ContactsCommon::get_main_company()));
		$contacts_select = array();
		foreach($contacts as $v)
			$contacts_select[$v['id']] = $v['first_name'].' '.$v['last_name'];
		$form->addElement('multiselect', 'contacts', $this->t('People'), $contacts_select);
		if ($form->validate()) {
			$v = $form->exportValues();
			if(isset($id)) {
				DB::Execute('UPDATE crm_filters_group SET name=%s,description=%s WHERE id=%d',array($v['name'],$v['description'],$id));
				DB::Execute('DELETE FROM crm_filters_contacts WHERE group_id=%d',array($id));
			} else {
				DB::Execute('INSERT INTO crm_filters_group(name,description,user_login_id) VALUES(%s,%s,%d)',array($v['name'],$v['description'],Acl::get_user()));
				$id = DB::Insert_ID('crm_filters_group','id');
			}

			foreach($v['contacts'] as $p)
				DB::Execute('INSERT INTO crm_filters_contacts(group_id,contact_id) VALUES(%d,%d)',array($id,$p));

			return false;
		} else {
			Base_ActionBarCommon::add('save','Save',$form->get_submit_form_href());
			Base_ActionBarCommon::add('back','Cancel',$this->create_back_href());

			$rb1 = $this->pack_module('Utils/RecordBrowser/RecordPicker', array('contact' ,'contacts',array('CRM_Filters','edit_group_sel'), array('company_name'=>CRM_ContactsCommon::get_main_company()), array(), array('last_name'=>'ASC')));
			Base_ActionBarCommon::add('filter','Detailed selection',$rb1->create_open_href(false));

			$form->display();
		}

		return true;
	}

	public static function edit_group_sel($r) {
		return $r['last_name'].' '.$r['first_name'];
	}

	public static function delete_group($id) {
		DB::Execute('DELETE FROM crm_filters_contacts WHERE group_id=%d',array($id));
		DB::Execute('DELETE FROM crm_filters_group WHERE id=%d',array($id));
	}

	public static function check_group_name_exists($name,$id) {
		if(isset($id)) {
			$ret = DB::GetOne('SELECT id FROM crm_filters_group WHERE id!=%d AND name=%s AND user_login_id=%d',array($id,$name,Acl::get_user()));
		} else {
			$ret = DB::GetOne('SELECT id FROM crm_filters_group WHERE name=%s AND user_login_id=%d',array($name,Acl::get_user()));
		}
		return $ret===false || $ret===null;
	}

}

?>
