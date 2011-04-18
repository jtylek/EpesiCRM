<?php
/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-Base
 * @subpackage AppStore
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_AppStore extends Module {

	public function body() {
	
	}
	
	public function admin() {
	    if($this->is_back()) {
	        return $this->parent->reset();
	    }
		Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());
//        Variable::set("license_key",'');
        
	    if(Variable::get("license_key")=="") {
	        return $this->register();
	    }
	    
	    print("TODO: appstore");
	}
	
	public function register() {
		$f = $this->init_module('Libs/QuickForm');

		$f->addElement('text','company_name',$this->t('Company name'),array('maxlength'=>128));
		$f->addRule('company_name',$this->t('Field required'),'required');
		$f->addRule('company_name',$this->t('Max length exceeded'),'maxlength',128);

		$f->addElement('text','short_name',$this->t('Short name'),array('maxlength'=>64));
		$f->addRule('short_name',$this->t('Max length exceeded'),'maxlength',64);

		$f->addElement('text','phone',$this->t('Phone'),array('maxlength'=>64));
		$f->addRule('phone',$this->t('Field required'),'required');
		$f->addRule('phone',$this->t('Max length exceeded'),'maxlength',64);

		$f->addElement('text','fax',$this->t('Fax'),array('maxlength'=>64));
		$f->addRule('fax',$this->t('Max length exceeded'),'maxlength',64);
		
		$f->addElement('text','email',$this->t('Email'),array('maxlength'=>128));
		$f->addRule('email',$this->t('Field required'),'required');
		$f->addRule('email',$this->t('Max length exceeded'),'maxlength',128);
        $f->addRule('email', $this->t('Invalid e-mail address'), 'email');

		$f->addElement('text','web_address',$this->t('Web address'),array('maxlength'=>64));
		$f->addRule('web_address',$this->t('Max length exceeded'),'maxlength',64);

		$f->addElement('text','address_1',$this->t('Address 1'),array('maxlength'=>64));
		$f->addRule('address_1',$this->t('Field required'),'required');
		$f->addRule('address_1',$this->t('Max length exceeded'),'maxlength',64);
		
		$f->addElement('text','address_2',$this->t('Address 2'),array('maxlength'=>64));
		$f->addRule('address_2',$this->t('Max length exceeded'),'maxlength',64);
		
		$f->addElement('text','city',$this->t('City'),array('maxlength'=>64));
		$f->addRule('city',$this->t('Field required'),'required');
		$f->addRule('city',$this->t('Max length exceeded'),'maxlength',64);
		
		$f->addElement('commondata','country',$this->t('Country'),'Countries');
		$f->addRule('country',$this->t('Field required'),'required');
		$f->addElement('commondata','zone',$this->t('Zone'),array('Countries','country'),array('empty_option'=>true));

		$f->addElement('text','postal_code',$this->t('Postal Code'),array('maxlength'=>64));
		$f->addRule('postal_code',$this->t('Field required'),'required');
		$f->addRule('postal_code',$this->t('Max length exceeded'),'maxlength',64);

		$f->addElement('text','tax_id',$this->t('Tax ID'),array('maxlength'=>64));
		$f->addRule('tax_id',$this->t('Field required'),'required');
		$f->addRule('tax_id',$this->t('Max length exceeded'),'maxlength',64);
		
		$f->setDefaults(CRM_ContactsCommon::get_company(CRM_ContactsCommon::get_main_company()));
		
		Base_ActionBarCommon::add('send','Register',$f->get_submit_form_href());
		
		if($f->validate()) {
			$ret = $f->exportValues();
			
			require_once('modules/Base/AppStore/ClientRequester.php');;
			$cr = new ClientRequester();
			$lic = $cr->register_client_id_request($ret);
			
			if($lic) {
    			Variable::set("license_key",$lic);
			
	    		Base_StatusBarCommon::message($this->t('Registered successfully'));
	        } else {
	            Base_StatusBarCommon::message($this->t('Registration error'));
	        }
//		   	Epesi::redisplay();
		}
		$f->display();	
	}

}

?>