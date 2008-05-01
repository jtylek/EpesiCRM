<?php
/**
 * CRMHR class.
 *
 * This class is just my first module, test only.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.99
 * @package tcms-extra
 */

defined("_VALID_ACCESS") || die();

class CRM_Contacts extends Module {
	private $rb = null;

	public function body() {
		if (isset($_REQUEST['mode']) && ($_REQUEST['mode']=='contact' || $_REQUEST['mode']=='company')) $this->set_module_variable('mode', $_REQUEST['mode']);
		$mode = $this->get_module_variable('mode');

		$this->rb = $this->init_module('Utils/RecordBrowser',$mode,$mode);
		$this->rb->set_defaults(array(	'country'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_country'),
										'zone'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_state')));
		if ($mode=='contact') {
			$this->rb->set_defaults(array(	'home_country'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_country'),
											'home_zone'=>Base_User_SettingsCommon::get('Base_RegionalSettings','default_state')));
			$this->rb->set_default_order(array('last_name'=>'ASC', 'first_name'=>'ASC'));
		} else {
			$this->rb->set_default_order(array('company_name'=>'ASC'));
		}
		$this->display_module($this->rb);
	}

	public function admin(){
		if($this->is_back()) {
			if($this->parent->get_type()=='Base_Admin')
				$this->parent->reset();
			else
				location(array());
			return;
		}
		
		$c = CRM_ContactsCommon::get_contacts(array('company_name'=>array(CRM_ContactsCommon::get_main_company())),array('first_name','last_name','login'));
		$gb = $this->init_module('Utils/GenericBrowser',null,'my_contacts');
		$gb->set_table_columns(array(
			array('name'=>'Login','search'=>1,'order'=>'l'),
			array('name'=>'Contact','search'=>1,'order'=>'c')
			
			));

		foreach($c as $r) {
			$gb->add_row(is_numeric($r['login'])?Base_UserCommon::get_user_login($r['login']):'--',CRM_ContactsCommon::contact_format_no_company($r));
		}
		$this->display_module($gb,array(true),'automatic_display');


		Base_ActionBarCommon::add('settings', 'Change main company', $this->create_callback_href(array($this,'admin_main_company')));
	}
	
	public function admin_main_company() {
		if($this->is_back()) {
			return false;
		}
		$qf = $this->init_module('Libs/QuickForm',null,'my_company');
		$l = $this->init_module('Base/Lang');
		$companies = CRM_ContactsCommon::get_companies(array(), array(), array('company_name'=>'ASC'));
		$x = array();
		foreach($companies as $c)
			$x['s'.$c['id']] = htmlentities($c['company_name']);//.' ('.$c['short_name'].')'
		$qf->addElement('select','company',$l->t('Choose main company'),$x);
		$qf->addElement('static',null,null,'Contacts assigned to this company are treated as employees. You should set the main company only once.');
		try {
			$main_company = Variable::get('main_company');
			$qf->setDefaults(array('company'=>'s'.$main_company));
		} catch(NoSuchVariableException $e) {
		}

		if($qf->validate()) {
			Variable::set('main_company',trim($qf->exportValue('company'),'s'));
			return false;
		}
		$qf->display();

		Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());
		Base_ActionBarCommon::add('save', 'Save', $qf->get_submit_form_href());
		return true;
	}
	
	public function company_addon($arg){
		$rb = $this->init_module('Utils/RecordBrowser','contact','contact_addon');
		Base_ActionBarCommon::add('add',Base_LangCommon::ts('CRM_Contacts','Add contact'), $this->create_callback_href(array($this, 'company_addon_new_contact'), array($arg['id'])));
		$rb->set_button($this->create_callback_href(array($this, 'company_addon_new_contact'), array($arg['id'])));
		$this->display_module($rb, array(array('company_name'=>array($arg['id'])), array('company_name'=>false), array('Fav'=>'DESC', 'Last Name'=>'ASC')), 'show_data');
	}

	public function company_addon_new_contact($id){
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('CRM/Contacts','new_contact',$id,array());
		return false;
	}

	public function new_contact($company){
		CRM_ContactsCommon::$paste_or_new = $company;
		$rb = $this->init_module('Utils/RecordBrowser','contact','contact');
		$this->rb = $rb;
		$ret = $rb->view_entry('add', null, array('company_name'=>array($company)));
		$this->set_module_variable('view_or_add', 'add');
		if ($ret==false) {
			$x = ModuleManager::get_instance('/Base_Box|0');
			if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
			return $x->pop_main();
		}
	}

	public function company_attachment_addon($arg){
		$a = $this->init_module('Utils/Attachment',array($arg['id'],'CRM/Company/'.$arg['id']));
		$a->additional_header('Company: '.$arg['company_name']);
		$a->allow_protected($this->acl_check('view protected notes'),$this->acl_check('edit protected notes'));
		$a->allow_public($this->acl_check('view public notes'),$this->acl_check('edit public notes'));
		$this->display_module($a);
	}

	public function contact_attachment_addon($arg){
		$a = $this->init_module('Utils/Attachment',array($arg['id'],'CRM/Contact/'.$arg['id']));
		$l = $this->init_module('Base/Lang');
		$companies = array();
		foreach($arg['company_name'] as $comp) {
			$company = CRM_ContactsCommon::get_company($comp);
			$companies[] = $company['company_name'].($company['short_name']?' ('.$company['short_name'].')':'');
		}
		$a->additional_header($l->t('%s %s from %s',array($arg['first_name'],$arg['last_name'],implode(', ',$companies))));
		$a->allow_protected($this->acl_check('view protected notes'),$this->acl_check('edit protected notes'));
		$a->allow_public($this->acl_check('view public notes'),$this->acl_check('edit public notes'));
		$this->display_module($a);
	}

	public function caption(){
		if (isset($this->rb)) return $this->rb->caption();
	}
}
?>
