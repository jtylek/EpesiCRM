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
			location(array('box_main_module'=>'Utils_RecordBrowser', 'box_main_constructor_arguments'=>array('contact')));
		} else {
			location(array('box_main_module'=>'Utils_RecordBrowser', 'box_main_constructor_arguments'=>array('company')));
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
			$x[$c['id']] = $c['Company Name'].' ('.$c['Short Name'].')';
		$qf->addElement('select','company',$l->t('Choose main company'),$x,array('onChange'=>$qf->get_submit_form_js()));
		$qf->addElement('static',null,null,'Contacts assigned to this company are treated as employees.');
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
		$theme = $this->init_module('Base/Theme');
		$theme->assign('add_contact', '<a '.$this->create_href(array('box_main_module'=>'CRM_Contacts', 'box_main_function'=>'new_contact', 'box_main_arguments'=>array($arg['id']))).'>'.Base_LangCommon::ts('CRM_Contacts','Add new contact').'</a>');
		$rb = $this->init_module('Utils/RecordBrowser','contact','contact_addon');
		$theme->assign('contacts', $this->get_html_of_module($rb, array(array('Company Name'=>$arg['id']), array('Company'=>false), array('Fav'=>'DESC'), true), 'show_data'));
		$theme->display('Company_plugin');
	}

	public function company_attachment_addon($arg){
		$a = $this->init_module('Utils/Attachment',array($arg['id'],'CRM/Company/'.$arg['id']));
		#$a->inline_attach_file(true);
		$a->allow_view_deleted($this->acl_check('view deleted attachments'));
		$a->allow_view($this->acl_check('view attachments'));
		$a->allow_edit($this->acl_check('edit attachments'));
		$a->allow_download($this->acl_check('download attachments'));
		$this->display_module($a);
	}

	public function contact_attachment_addon($arg){
		$a = $this->init_module('Utils/Attachment',array($arg['id'],'CRM/Contact/'.$arg['id']));
		#$a->inline_attach_file(true);
		$a->allow_view_deleted($this->acl_check('view deleted attachments'));
		$a->allow_view($this->acl_check('view attachments'));
		$a->allow_edit($this->acl_check('edit attachments'));
		$a->allow_download($this->acl_check('download attachments'));
		$this->display_module($a);
	}

	public function new_contact($company){
		CRM_ContactsCommon::$paste_or_new = $company;
		$rb = $this->init_module('Utils/RecordBrowser','contact','contact');
		$this->rb = $rb;
		$ret = $rb->view_entry('add', null, array('company_name'=>array($company)));
		$this->set_module_variable('view_or_add', 'add');
		if ($ret==false) {
			unset($_REQUEST['__CRM_Contacts__adding_new_contact']);
			location(array('box_main_module'=>'Utils_RecordBrowser', 'box_main_constructor_arguments'=>array('company'), 'box_main_function'=>'view_entry', 'box_main_arguments'=>array('view', $company, array())));
		}
	}
	public function caption(){
		if (isset($this->rb)) return $this->rb->caption();
	}
}
?>
