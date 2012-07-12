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
		if (!Acl::is_user()) return;
		$th = $this->init_module('Base/Theme');
		$display_settings = Base_User_SettingsCommon::get('Base/ActionBar','display');
		$display_icon = ($display_settings == 'both' || $display_settings == 'icons only');
		$display_text = ($display_settings == 'both' || $display_settings == 'text only');
		$th->assign('display_icon',$display_icon);
		$th->assign('display_text',$display_text);

		eval_js_once('crm_filters_deactivate = function(){leightbox_deactivate(\'crm_filters\');}');

		$th->assign('my','<a '.$this->create_callback_href(array('CRM_FiltersCommon','set_profile'),'my').' id="crm_filters_my">'.__('My records').'</a>');
		eval_js('Event.observe(\'crm_filters_my\',\'click\', crm_filters_deactivate)');

		$th->assign('all','<a '.$this->create_callback_href(array('CRM_FiltersCommon','set_profile'),'all').' id="crm_filters_all">'.__('All records').'</a>');
		eval_js('Event.observe(\'crm_filters_all\',\'click\', crm_filters_deactivate)');

		$th->assign('manage','<a '.$this->create_callback_href(array($this,'manage_filters')).' id="crm_filters_manage">'.__('Manage presets').'</a>');
		eval_js('Event.observe(\'crm_filters_manage\',\'click\', crm_filters_deactivate)');

		$ret = DB::Execute('SELECT id,name,description FROM crm_filters_group WHERE user_login_id=%d',array(Acl::get_user()));
		$filters = array();
		while($row = $ret->FetchRow()) {
			$filters[] = array('title'=>$row['name'],'description'=>'','open'=>'<a '.Utils_TooltipCommon::open_tag_attrs($row['description'],false).' '.$this->create_callback_href(array('CRM_FiltersCommon','set_profile'),$row['id']).' id="crm_filters_'.$row['id'].'">','close'=>'</a>');
			eval_js('Event.observe(\'crm_filters_'.$row['id'].'\',\'click\', crm_filters_deactivate)');
		}
		$th->assign('filters',$filters);

		$qf = $this->init_module('Libs/QuickForm');
		$fcallback = array('CRM_ContactsCommon', 'contact_format_no_company');
		$recent_crits = array();
		if (!Base_User_SettingsCommon::get('CRM_Contacts','show_all_contacts_in_filters')) $recent_crits = array('(company_name'=>CRM_ContactsCommon::get_main_company(),'|related_companies'=>array(CRM_ContactsCommon::get_main_company()));
		$contacts = CRM_ContactsCommon::get_contacts($recent_crits,array(),array(),15);
		$cont = array();
		foreach ($contacts as $v) { 
			$cont[$v['id']] = call_user_func($fcallback, $v, true);
		}
		asort($cont);
		$crits = array();
		if (!Base_User_SettingsCommon::get('CRM_Contacts','show_all_contacts_in_filters')) $crits = array('(company_name'=>CRM_ContactsCommon::get_main_company(),'|related_companies'=>array(CRM_ContactsCommon::get_main_company()));
		$qf->addElement('autoselect','crm_filter_contact',__('Records of'),$cont,array(array('CRM_ContactsCommon','autoselect_contact_suggestbox'), array($crits, $fcallback, false)), $fcallback);
		if(isset($_SESSION['client']['filter_'.Acl::get_user()]['value'])) {
			$qf->setDefaults(array('crm_filter_contact'=>explode(',',$_SESSION['client']['filter_'.Acl::get_user()]['value'])));
		}
		$qf->addElement('submit','submit',__('Show'), array('onclick'=>'crm_filters_deactivate()'));
		if($qf->validate()) {
			$c = $qf->exportValue('crm_filter_contact');
			CRM_FiltersCommon::set_profile('c'.$c);
			location(array());
		}
		$th->assign('saved_filters',__('Saved Presets'));
		$qf->assign_theme('contacts', $th);
		//$th->assign('contacts',$qf->toHtml());

		ob_start();
		$th->display();
		$profiles_out = ob_get_clean();

		Libs_LeightboxCommon::display('crm_filters',$profiles_out,__('Perspective'),true);
		if(!isset($_SESSION['client']['filter_'.Acl::get_user()]['desc']))
			CRM_FiltersCommon::set_profile($this->get_default_filter());
		    
		//Base_ActionBarCommon::add('folder',__('Filters'),'class="lbOn" rel="crm_filters"',$this->get_module_variable('profile_desc',__('My records')));
		if (isset($_REQUEST['__location'])) $in_use = (CRM_FiltersCommon::$in_use===$_REQUEST['__location']);
		else $in_use = CRM_FiltersCommon::$in_use;
		print('<a class="lbOn'.($in_use?'':' disabled').' button" rel="crm_filters">'.__('Perspective').': '.'<b>'.$_SESSION['client']['filter_'.Acl::get_user()]['desc'].'</b><div class="filter_icon_img"></div></a>');
	}

	public function manage_filters() {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main($this->get_type(),'edit', array(false));
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

	public function edit($user_settings_nav=true) {
		if ($user_settings_nav)
			Base_ActionBarCommon::add('back',__('Back'),$this->create_main_href('Base_User_Settings'));

		Base_ActionBarCommon::add('add',__('Add preset'),$this->create_callback_href(array($this,'edit_group')));

		$gb = $this->init_module('Utils/GenericBrowser',null,'edit');

		$gb->set_table_columns(array(
				array('name'=>__('Name'), 'width'=>20, 'order'=>'g.name'),
				array('name'=>__('Description'), 'width'=>30, 'order'=>'g.description'),
				array('name'=>__('Users in category'), 'width'=>50, 'order'=>'')
				));

		$def_opts = array('my'=>__('My records'), 'all'=>__('All records'));
		$contacts = CRM_ContactsCommon::get_contacts(array(),array('first_name','last_name'),array('last_name'=>'ASC','first_name'=>'ASC'));
		foreach($contacts as $v)
			$def_opts['c'.$v['id']] = $v['last_name'].' '.$v['first_name'];

		$ret = DB::Execute('SELECT g.name,g.id,g.description FROM crm_filters_group g WHERE g.user_login_id='.Acl::get_user());
		while($row = $ret->FetchRow()) {
			$def_opts[$row['id']] = $row['name'];
		
			$gb_row = & $gb->get_new_row();
			$gb_row->add_action($this->create_confirm_callback_href(__('Delete this group?'),array('CRM_Filters','delete_group'), $row['id']),'Delete');
			$gb_row->add_action($this->create_callback_href(array($this,'edit_group'),$row['id']),'Edit');
			$cids = DB::GetAssoc('SELECT c.contact_id, c.contact_id FROM crm_filters_contacts c WHERE c.group_id=%d',array($row['id']));
			$users = array();
			foreach ($cids as $v)
				$users[] = CRM_ContactsCommon::contact_format_no_company(CRM_ContactsCommon::get_contact($v),true);
			$gb_row->add_data($row['name'], $row['description'], implode(', ',$users));
		}

		$this->display_module($gb);
		
		$qf = $this->init_module('Libs/QuickForm',null,'default_filter');
		$qf->addElement('select','def_filter',__('Default perspective'),$def_opts,array('onChange'=>$qf->get_submit_form_js()));
		$qf->addElement('checkbox','show_all_contacts_in_filters',__('Show all contacts in Perspective selection'),null,array('onChange'=>$qf->get_submit_form_js()));
		$qf->addRule('def_filter',__('Field required'),'required');
		$qf->setDefaults(array(	'def_filter'=>$this->get_default_filter($def_filter_exists),
								'show_all_contacts_in_filters'=>Base_User_SettingsCommon::get('CRM_Contacts','show_all_contacts_in_filters')
						));
		if($qf->validate()) {
		    $vals = $qf->exportValues();
			if (!isset($vals['show_all_contacts_in_filters'])) $vals['show_all_contacts_in_filters'] = 0;
			Base_User_SettingsCommon::save('CRM_Contacts','show_all_contacts_in_filters',$vals['show_all_contacts_in_filters']);
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
			$form->addElement('header',null,__('Edit group "%s"',array($name)));

			$contacts_def = DB::GetCol('SELECT contact_id FROM crm_filters_contacts WHERE group_id=%d',array($id));

			$form->setDefaults(array('name'=>$name,'contacts'=>$contacts_def,'description'=>$description));
		} else
			$form->addElement('header',null,__('New preset'));
		$form->addElement('text','name',__('Name'));
		$form->addElement('text','description',__('Description'));
		$form->addRule('name',__('Max length of field exceeded'),'maxlength',128);
		$form->addRule('description',__('Max length of field exceeded'),'maxlength',256);
		$form->addRule('name',__('Field required'),'required');
		$form->registerRule('unique','callback','check_group_name_exists', 'CRM_Filters');
		$form->addRule('name',__('Group with this name already exists'),'unique',$id);
		$form->addElement('automulti','contacts',__('Records of'),array('CRM_ContactsCommon','automulti_contact_suggestbox'), array(array(), array('CRM_ContactsCommon', 'contact_format_no_company')), array('CRM_ContactsCommon', 'contact_format_no_company'));
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
			Base_ActionBarCommon::add('save',__('Save'),$form->get_submit_form_href());
			Base_ActionBarCommon::add('back',__('Cancel'),$this->create_back_href());

			$form->display_as_column();
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
