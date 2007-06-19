<?php
/**
 * Mail class.
 * 
 * This class provides mail sending functionality.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides mail sending functionality.
 * @package epesi-base-extra
 * @subpackage mail
 */
class Base_Mail extends Module implements Base_AdminInterface {

	public function body($arg) {
	}
	
	public function admin() {
		if($this->is_back()) {
			$this->parent->reset();
			//print('ok');
			return;
		}
		
		$lang = & $this->pack_module('Base/Lang');
		
		$form = & $this->init_module('Libs/QuickForm');
		//defaults
		$defaults = array();
		$defaults['mail_method'] = Variable::get('mail_method');
		$defaults['mail_user'] = Variable::get('mail_user');
		$defaults['mail_from_addr'] = Variable::get('mail_from_addr');
		$defaults['mail_from_name'] = Variable::get('mail_from_name');
		$defaults['mail_host'] = Variable::get('mail_host');
		$defaults['mail_auth'] = Variable::get('mail_auth');
		$defaults['mail_password'] = Variable::get('mail_password');
				
		$form->setDefaults($defaults);
	
		//form
		$form->addElement('header',null, $lang->t('Mail settings'));
		$form->addElement('select','mail_method', $lang->t('Choose method'), array('smtp'=>'remote smtp server', 'mail'=>'local php.ini settings'), 'onChange="'.$form->get_submit_form_js(false).'"');
		
		$form->addElement('text','mail_from_addr', $lang->t('Administrator e-mail address'));
		$form->addRule('mail_from_addr', $lang->t('This isn\'t valid e-mail address'), 'email');
		$form->addRule('mail_from_addr', $lang->t('Field required'), 'required');	
		
		$form->addElement('text','mail_from_name', $lang->t('Send e-mails from name'));
	
		$method = $form->getElement('mail_method')->getSelected();
		if($method[0]=='smtp') {
			
			$form->addElement('text','mail_host', $lang->t('SMTP host address'));
			$form->addRule('mail_host', $lang->t('Field required'),'required');
			
			$form->addElement('checkbox','mail_auth', $lang->t('SMTP authorization'),'','onChange="'.$form->get_submit_form_js(false).'"');
			
			$auth = $form->getElement('mail_auth')->getValue();
			if($auth) {
				$form->addElement('text','mail_user', $lang->t('Login'));					
				$form->addElement('password','mail_password', $lang->t('Password'));
			}
		}
		
		$ok_b = HTML_QuickForm::createElement('submit', 'submit_button', $lang->ht('OK'));
		$cancel_b = HTML_QuickForm::createElement('button', 'cancel_button', $lang->ht('Cancel'), $this->create_back_href());
		$form->addGroup(array($ok_b, $cancel_b));
		
		if($form->getSubmitValue('submited') && $form->validate() && $form->process(array(&$this,'submit_admin'))) {
			$this->parent->reset();
		} else {
			$form->display();					
		}
		
	}
	
	public function submit_admin($data) {
		$method = $data['mail_method'];
		Variable::set('mail_method', $method);
		Variable::set('mail_from_addr', $data['mail_from_addr']);
		Variable::set('mail_from_name', $data['mail_from_name']);
		if($method=='smtp') {
			Variable::set('mail_host', $data['mail_host']);
			
			$auth = $data['mail_auth'];
			Variable::set('mail_auth', $auth);
			if($auth) {
				Variable::set('mail_user', $data['mail_user']);
				Variable::set('mail_password', $data['mail_password']);
			}
		}
		return true;
	}
}
?>
