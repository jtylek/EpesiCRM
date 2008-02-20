<?php
/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-tasks
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Tasks extends Module {

	public function body() {

		$f = $this->pack_module('CRM/Filters');
		$filter = $f->get();
	
		$term = & $this->get_module_variable('term','s');
		$closed = & $this->get_module_variable('closed',false);

		//ustawiacz long, short term, oraz closed
		$l = $this->init_module('Base/Lang');
		$f = $this->init_module('Libs/QuickForm',null,'filters');
		$f->addElement('select','term',$l->t('Display tasks marked as'),array('s'=>'Short term','l'=>'Long term','b'=>'Both'));
		$f->addElement('checkbox','closed',$l->t('Display closed tasks'));
		$f->addElement('submit','submit',$l->ht('OK'));
		$f->setDefaults(array('term'=>$term,'closed'=>$closed));
		if($f->validate()) {
			$v = $f->exportValues();
			$term = $v['term'];
			$closed = isset($v['closed']) && $v['closed'];
		}

		$theme = $this->init_module('Base/Theme');

		$f->assign_theme('form',$theme);
		
		$tasks = $this->init_module('Utils/Tasks',array('crm_tasks',true,($term=='s' || $term=='b'),($term=='l' || $term=='b'),$closed,$filter));
		$theme->assign('tasks',$this->get_html_of_module($tasks));
		$theme->display();
	}
	
	public function applet($conf,$opts) {
		$opts['go'] = true;
		$opts['title'] = 'Tasks'.($conf['term']=='s'?' - short term':($conf['term']=='l'?' - long term':''));
		$me = CRM_ContactsCommon::get_contact_by_user_id(Acl::get_user());
		$this->pack_module('Utils/Tasks',null,'applet',array('crm_tasks',false,($conf['term']=='s' || $conf['term']=='b'),($conf['term']=='l' || $conf['term']=='b'),(isset($conf['closed']) && $conf['closed']),'('.$me['id'].')'));
	}

	public function caption() {
		return "Tasks";
	}

}

?>