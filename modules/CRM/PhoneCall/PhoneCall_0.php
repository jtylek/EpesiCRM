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

class CRM_PhoneCall extends Module {
	private $rb = null;

	public function body() {
		$this->rb = $this->init_module('Utils/RecordBrowser','phonecall','phonecall');
		$this->rb->set_defaults(array('date_and_time'=>date('Y-m-d H:i:s')));
		$this->display_module($this->rb);
	}

	public function company_addon($arg){
		$theme = $this->init_module('Base/Theme');
//		$theme->assign('add_contact', '<a '.$this->create_callback_href(array($this, 'company_addon_new_contact'), array($arg['id'])).'>'.Base_LangCommon::ts('CRM_Contacts','Add new contact').'</a>');
		Base_ActionBarCommon::add('add',Base_LangCommon::ts('CRM_Contacts','Add contact'), $this->create_callback_href(array($this, 'company_addon_new_contact'), array($arg['id'])));
		$rb = $this->init_module('Utils/RecordBrowser','contact','contact_addon');
		$theme->assign('contacts', $this->get_html_of_module($rb, array(array('company_name'=>array($arg['id'])), array('company_name'=>false), array('Fav'=>'DESC', 'Last Name'=>'ASC'), true), 'show_data'));
		$theme->display('Company_plugin');
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
