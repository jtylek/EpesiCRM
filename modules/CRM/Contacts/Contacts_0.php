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
	public function body() {
		if (isset($_REQUEST['mode'])) $this->set_module_variable('mode', $_REQUEST['mode']);
		$mode = $this->get_module_variable('mode');
		if ($mode == 'contact') {
			$rb = $this->init_module('Utils/RecordBrowser','contact');
			$this->display_module($rb);
		} else {
			$rb = $this->init_module('Utils/RecordBrowser','company');
			$this->display_module($rb);
		}
	}

	public function admin() {
		$tb = $this->init_module('Utils/TabbedBrowser');
		$tb->set_tab('Contacts', array($this, 'contact_admin'));
		$tb->set_tab('Companies', array($this, 'company_admin'));
		$this->display_module($tb);
		$tb->tag();
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
		$theme->assign('add_contact', '<a '.$this->create_href(array('box_main_module'=>'Utils_RecordBrowser', 'box_main_constructor_arguments'=>array('contact'), 'tab'=>'contact', 'action'=>'add', 'id'=>'', 'defaults'=>array('company'=>array($arg['id'])))).'>'.Base_LangCommon::ts('CRM_Contacts','Add new contact').'</a>');
		$rb = $this->init_module('Utils/RecordBrowser','contact');
		$theme->assign('contacts', $this->get_html_of_module($rb, array(array('Company'=>$arg['id']), array('Company'=>false), true), 'show_data'));
		$theme->display('Company_plugin');
	}
	public function new_contact($arg){
		$rb = $this->init_module('Utils/RecordBrowser','contact','contact');
		$rb->view_entry('add', null, array('company'=>array($arg['id'])));
		return false;
	}
}
?>
