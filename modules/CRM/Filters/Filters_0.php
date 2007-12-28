<?php
/**
 *
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-filters
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Filters extends Module {
	private $lang;
	private $tbl_contact_prefix;

	public function construct($prefix=null) {
		$this->lang = $this->init_module('Base/Lang');
		if(isset($prefix))
			$this->tbl_contact_prefix = $prefix;
	}

	public function body() {
		if(!isset($this->tbl_contact_prefix))
			trigger_error('Contact table prefix not set',E_USER_ERROR);

		Base_ActionBarCommon::add('folder','Filters','class="lbOn" rel="crm_filters"');
		$th = $this->init_module('Base/Theme');
		$display_settings = Base_User_SettingsCommon::get('Base/ActionBar','display');
		$display_icon = ($display_settings == 'both' || $display_settings == 'icons only');
		$display_text = ($display_settings == 'both' || $display_settings == 'text only');
		$th->assign('display_icon',$display_icon);
		$th->assign('display_text',$display_text);

		eval_js_once('crm_filters_deactivate = function(){leightbox_deactivate(\'crm_filters\');}');

		$th->assign('my','<a '.$this->create_callback_href(array($this,'set_profile'),'my').' id="crm_filters_my">'.$this->lang->t('My records').'</a>');
		eval_js('Event.observe(\'crm_filters_my\',\'click\', crm_filters_deactivate)');

		$th->assign('all','<a '.$this->create_callback_href(array($this,'set_profile'),'all').' id="crm_filters_all">'.$this->lang->t('All records').'</a>');
		eval_js('Event.observe(\'crm_filters_all\',\'click\', crm_filters_deactivate)');

		$ret = DB::Execute('SELECT id,name FROM crm_filters_group WHERE user_login_id=%d',array(Acl::get_user()));
		$filters = array();
		while($row = $ret->FetchRow()) {
			$filters[] = '<a '.$this->create_callback_href(array($this,'set_profile'),$row['id']).' id="crm_filters_'.$row['id'].'">'.$row['name'].'</a>';
			eval_js('Event.observe(\'crm_filters_'.$row['id'].'\',\'click\', crm_filters_deactivate)');
		}
		$th->assign('filters',$filters);

		$qf = $this->init_module('Libs/QuickForm');
		$contacts = CRM_ContactsCommon::get_contacts(array('company_name'=>CRM_ContactsCommon::get_main_company()));
		$contacts_select = array();
		foreach($contacts as $v)
			$contacts_select[$v['id']] = $v['first_name'].' '.$v['last_name'];
		$qf->addElement('select','contact',$this->lang->t('Records of'),$contacts_select,array('onChange'=>$qf->get_submit_form_js().'crm_filters_deactivate()'));
		if($qf->validate()) {
			$this->set_module_variable('profile',$this->tbl_contact_prefix.'.id='.$qf->exportValue('contact'));
		}
		$th->assign('contacts',$qf->toHtml());

		ob_start();
		$th->display();
		$profiles_out = ob_get_clean();

		Libs_LeightboxCommon::display('crm_filters',$profiles_out,$this->lang->t('Filters'));
	}

	public function set_profile($prof) {
		if(!isset($this->tbl_contact_prefix))
			trigger_error('Contact table prefix not set',E_USER_ERROR);

		if(is_numeric($prof)) {
			DB::Execute('DELETE FROM crm_filters_contacts WHERE (SELECT cd.value FROM contact_data cd WHERE cd.contact_id=contact_id AND cd.field=\'Company Name\')!=%d',CRM_ContactsCommon::get_main_company());
			$c = DB::GetCol('SELECT p.contact_id FROM crm_filters_contacts p WHERE p.group_id=%d',array($prof));
			if($c)
				$ret = '('.$this->tbl_contact_prefix.'.id='.implode(' OR '.$this->tbl_contact_prefix.'.id=',$c).')';
			else
				$ret = '0=1';
		} elseif($prof=='my') {
			$me = CRM_ContactsCommon::get_contact_by_user_id(Acl::get_user());
			$ret = $this->tbl_contact_prefix.'.id='.$me['id'];
		} else $ret = ''; //all and undefined
		$this->set_module_variable('profile',$ret);
	}

	public function get() {
		return $this->get_module_variable('profile','');
	}

	public function edit() {
		Base_ActionBarCommon::add('add',$this->lang->ht('Add group'),$this->create_callback_href(array($this,'edit_group')));

		$gb = $this->init_module('Utils/GenericBrowser',null,'edit');

		$gb->set_table_columns(array(
				array('name'=>$this->lang->t('Name'), 'width'=>30, 'order'=>'g.name'),
				array('name'=>$this->lang->t('Users in category'), 'width'=>70, 'order'=>'')
				));

		$ret = $gb->query_order_limit('SELECT g.name,g.id FROM crm_filters_group g WHERE g.user_login_id='.Acl::get_user(),'SELECT count(g.id) FROM crm_filters_group g WHERE g.user_login_id='.Acl::get_user());
		while($row = $ret->FetchRow()) {
			$gb_row = & $gb->get_new_row();
			$gb_row->add_action($this->create_confirm_callback_href($this->lang->ht('Delete this group?'),array('CRM_Filters','delete_group'), $row['id']),'Delete');
			$gb_row->add_action($this->create_callback_href(array($this,'edit_group'),$row['id']),'Edit');
			$users = DB::GetCol('SELECT '.DB::Concat('(SELECT aa.value FROM contact_data aa WHERE aa.contact_id=c.contact_id AND aa.field=\'First Name\')','\' \'','(SELECT aa.value FROM contact_data aa WHERE aa.contact_id=c.contact_id AND aa.field=\'Last Name\')').' FROM crm_filters_contacts c WHERE c.group_id=%d',array($row['id']));
			$gb_row->add_data($row['name'], implode(', ',$users));
		}

		$this->display_module($gb);
	}

	public function edit_group($id=null) {
		if($this->is_back()) return false;

		$form = $this->init_module('Libs/QuickForm', null, 'edit_group');
		if(isset($id)) {
			$name = DB::GetOne('SELECT name FROM crm_filters_group WHERE id=%d',array($id));
			$form->addElement('header',null,$this->lang->t('Edit group "%s"',array($name)));

			$contacts_def = DB::GetCol('SELECT contact_id FROM crm_filters_contacts WHERE group_id=%d',array($id));

			$form->setDefaults(array('name'=>$name,'contacts'=>$contacts_def));
		} else
			$form->addElement('header',null,$this->lang->t('New group'));
		$form->addElement('text','name',$this->lang->t('Name'));
		$form->addRule('name',$this->lang->t('Field required'),'required');
		$form->registerRule('unique','callback','check_group_name_exists', 'CRM_Filters');
		$form->addRule('name',$this->lang->t('Group with this name already exists'),'unique',$id);
		$contacts = CRM_ContactsCommon::get_contacts(array('company_name'=>CRM_ContactsCommon::get_main_company()));
		$contacts_select = array();
		foreach($contacts as $v)
			$contacts_select[$v['id']] = $v['first_name'].' '.$v['last_name'];
		$form->addElement('multiselect', 'contacts', $this->lang->t('People'), $contacts_select);
		if ($form->validate()) {
			$v = $form->exportValues();
			if(isset($id)) {
				if($v['name']!=$name)
					DB::Execute('UPDATE crm_filters_group SET name=%s WHERE id=%d',array($v['name'],$id));
				DB::Execute('DELETE FROM crm_filters_contacts WHERE group_id=%d',array($id));
			} else {
				DB::Execute('INSERT INTO crm_filters_group(name,user_login_id) VALUES(%s,%d)',array($v['name'],Acl::get_user()));
				$id = DB::Insert_ID('crm_filters_group','id');
			}

			foreach($v['contacts'] as $p)
				DB::Execute('INSERT INTO crm_filters_contacts(group_id,contact_id) VALUES(%d,%d)',array($id,$p));

			return false;
		} else {
			Base_ActionBarCommon::add('save','Save',$form->get_submit_form_href());
			Base_ActionBarCommon::add('back','Cancel',$this->create_back_href());

			$rb1 = $this->pack_module('Utils/RecordBrowser/RecordPicker', array('contact' ,'contacts',array('CRM_Filters','edit_group_sel'), array('company_name'=>CRM_ContactsCommon::get_main_company())));
			Base_ActionBarCommon::add('folder','Detailed selection',$rb1->create_open_href());

			$form->display();
		}

		return true;
	}

	public static function edit_group_sel($id) {
		return $id;
	}

	public static function delete_group($id) {
		DB::Execute('DELETE FROM crm_filters_contacts WHERE group_id=%d',array($id));
		DB::Execute('DELETE FROM crm_filters_group WHERE id=%d',array($id));
	}

	public static function check_group_name_exists($name,$id) {
		if(isset($id))
			return (DB::GetOne('SELECT id FROM crm_filters_group WHERE id!=%d AND name=%s',array($id,$name))===false);
		else
			return (DB::GetOne('SELECT id FROM crm_filters_group WHERE name=%s',array($name))===false);
	}

}

?>
