<?php
/**
 * CRMHR class.
 *
 * This class is just my first module, test only.
 *
 * @author Kuba Sławiński <ruud@o2.pl>, Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.99
 * @package tcms-extra
 */

defined("_VALID_ACCESS") || die();

class CRM_Contacts extends Module {
	private $rb = null;

	public function body() {
		if (isset($_REQUEST['mode'])) $this->set_module_variable('mode', $_REQUEST['mode']);
		$mode = $this->get_module_variable('mode');
		if ($mode == 'contact') {
			location(array('box_main_module'=>'Utils_RecordBrowser', 'box_main_constructor_arguments'=>array('contact'), 'box_main_arguments'=>array(array('last_name'=>'ASC'))));
		} else {
			location(array('box_main_module'=>'Utils_RecordBrowser', 'box_main_constructor_arguments'=>array('company'), 'box_main_arguments'=>array(array('company_name'=>'ASC'))));
		}
	}

	public function admin() {
		$tb = $this->init_module('Utils/TabbedBrowser');
		$tb->set_tab('Contacts', array($this, 'contact_admin'));
		$tb->set_tab('Companies', array($this, 'company_admin'));
		$tb->set_tab('Main company', array($this, 'main_company_admin'));
		$this->display_module($tb);
		$tb->tag();
	}
	public function main_company_admin(){
		$qf = $this->init_module('Libs/QuickForm','my_company');
		$l = $this->init_module('Base/Lang');
		$companies = CRM_ContactsCommon::get_companies();
		$x = array();
		foreach($companies as $c)
			$x[$c['id']] = $c['company_name'].' ('.$c['short_name'].')';
		$qf->addElement('select','company',$l->t('Choose main company'),$x,array('onChange'=>$qf->get_submit_form_js()));
		$qf->addElement('static',null,null,'Contacts assigned to this company are treated as employees. You should set the main company only once.');
		try {
			$main_company = Variable::get('main_company');
			$qf->setDefaults(array('company'=>$main_company));
		} catch(NoSuchVariableException $e) {
		}

		if($qf->validate()) {
			Variable::set('main_company',$qf->exportValue('company'));
		}
		$qf->display();
	}
	public function contact_admin(){
		$rb = $this->init_module('Utils/RecordBrowser','contact','contact');
		$this->display_module($rb, null, 'admin');
	}
	public function company_admin(){
		$rb = $this->init_module('Utils/RecordBrowser','company','company');
		$this->display_module($rb, null, 'admin');
	}

	public function company_addon($arg){
		$rb = $this->init_module('Utils/RecordBrowser','contact','contact_addon');
		Base_ActionBarCommon::add('add',Base_LangCommon::ts('CRM_Contacts','Add contact'), $this->create_callback_href(array($this, 'company_addon_new_contact'), array($arg['id'])));
		$rb->set_button($this->create_callback_href(array($this, 'company_addon_new_contact'), array($arg['id'])));
		$this->display_module($rb, array(array('company_name'=>array($arg['id'])), array('company_name'=>false), array('Fav'=>'DESC', 'Last Name'=>'ASC'), true), 'show_data');
	}

	public function company_addon_new_contact($id){
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('CRM/Contacts','new_contact',$id,array());
		return false;
	}

	public function company_attachment_addon($arg){
		$a = $this->init_module('Utils/Attachment',array($arg['id'],'CRM/Company/'.$arg['id']));
		$a->additional_header('Company: '.$arg['company_name']);
		$a->allow_view_deleted($this->acl_check('view deleted notes'));
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
		$a->allow_view_deleted($this->acl_check('view deleted notes'));
		$a->allow_protected($this->acl_check('view protected notes'),$this->acl_check('edit protected notes'));
		$a->allow_public($this->acl_check('view public notes'),$this->acl_check('edit public notes'));
		$this->display_module($a);
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
	public function caption(){
		if (isset($this->rb)) return $this->rb->caption();
	}
}
?>
