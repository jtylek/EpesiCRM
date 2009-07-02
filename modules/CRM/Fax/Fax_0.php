<?php
/**
 * Fax abstraction layer module
 * @author pbukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-CRM
 * @subpackage Fax
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Fax extends Module {

	public function body() {
	
	}
	
	public function send($file) {
		if($this->is_back()) {
		        return $this->go_back($file);
		}
		$qf = $this->init_module('Libs/QuickForm',null,'send_fax');
		
		$providers_arr = ModuleManager::call_common_methods('fax_provider',true,array($file));
		$providers = array();
		foreach($providers_arr as $module=>$arr) {
			if(!$arr) {
				unset($providers_arr[$module]);
				continue;
			}
			$providers[$module] = $arr['name'];
		}
		$qf->addElement('header',null,$this->t('Faxing file: %s',array(basename($file))));
		$qf->addElement('select','provider',$this->t('Provider'),$providers);
		
		$qf->addElement('header',null,$this->t('Contact'));
		$fav_contact = CRM_ContactsCommon::get_contacts(array(':Fav'=>true,'!fax'=>''),array('id','login','first_name','last_name','company_name'));
		$fav_contact2 = array();
		foreach($fav_contact as $v)
			$fav_contact2[$v['id']] = CRM_ContactsCommon::contact_format_default($v,true);
		$rb_contact = $this->init_module('Utils/RecordBrowser/RecordPicker');
		$this->display_module($rb_contact, array('contact' ,'dest_contact',array('CRM_FaxCommon','rpicker_contact_format'),array('!fax'=>''),array('fax'=>true)));
		$qf->addElement('multiselect','dest_contact','',$fav_contact2);
		$qf->addElement('static',null,$rb_contact->create_open_link('Add contact'));

		$qf->addElement('header',null,$this->t('Company'));
		$fav_company = CRM_ContactsCommon::get_companies(array(':Fav'=>true,'!fax'=>''),array('id','company_name'));
		$fav_company2 = array();
		foreach($fav_company as $v)
			$fav_company2[$v['id']] = $v['company_name'];
		$rb_company = $this->init_module('Utils/RecordBrowser/RecordPicker');
		$this->display_module($rb_company, array('company' ,'dest_company',array('CRM_FaxCommon','rpicker_company_format'),array('!fax'=>''),array('fax'=>true)));
		$qf->addElement('multiselect','dest_contact','',$fav_company2);
		$qf->addElement('static',null,$rb_company->create_open_link('Add company'));

		$qf->addElement('header',null,$this->t('Other'));
		$qf->addElement('text','dest_other',$this->t('Other fax numbers (comma separated)'));
		
		if($qf->validate()) {
			$data = $qf->exportValues();
			if(!isset($providers_arr[$data['provider']]['func'])) {
				Epesi::alert($this->ht('Invalid fax provider.'));
			} else {
				$fax_func = array($data['provider'].'Common',$providers_arr[$data['provider']]['func']);
				call_user_func($fax_func,$file);
				return $this->go_back($file);
			}
		}
		$qf->display();
		
		Base_ActionBarCommon::add('send','Send',$qf->get_submit_form_href());
		Base_ActionBarCommon::add('back','Back',$this->create_back_href());
	}
	
	public function go_back($file) {
		unlink($file);
		
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->pop_main();
	}

}

?>