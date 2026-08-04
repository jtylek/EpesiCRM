<?php
/**
 * Mail class.
 * 
 * This class provides mail sending functionality.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage mail
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Mail extends Module implements Base_AdminInterface {

	public function body() {
	}

	/**
	 * For internal use only.
	 */
	public function admin() {
		if($this->is_back()) {
			$this->parent->reset();
			return;
		}
		
		$form = $this->init_module(Libs_QuickForm::module_name());
		//defaults
		$defaults = array();
		$defaults['mail_method'] = Variable::get('mail_method');
		$defaults['mail_use_replyto'] = Variable::get('mail_use_replyto');
		$defaults['mail_user'] = Variable::get('mail_user');
		$defaults['mail_from_addr'] = Variable::get('mail_from_addr');
		$defaults['mail_from_name'] = Variable::get('mail_from_name');
		$defaults['mail_host'] = Variable::get('mail_host');
        $defaults['mail_security'] = Variable::get('mail_security', false);
		$defaults['mail_auth'] = Variable::get('mail_auth');
		$defaults['mail_password'] = Variable::get('mail_password');
				
		$form->setDefaults($defaults);
	
		//form
		$form->addElement('header',null, __('Mail settings'));
		$form->addElement('select','mail_method', __('Choose method'), array('smtp'=>__('remote smtp server'), 'mail'=>__('local php.ini settings')), 'onChange="'.$form->get_submit_form_js(false).'"');
		
		$form->addElement('text','mail_from_addr', __('Administrator e-mail address'));
		$form->addRule('mail_from_addr', __('Invalid e-mail address'), 'email');
		$form->addRule('mail_from_addr', __('Field required'), 'required');	
		
		$form->addElement('text','mail_from_name', __('Send e-mails from name'));
		$form->addElement('text','mail_use_replyto', __('Set "Reply-To" email address'));
		$form->addRule('mail_use_replyto', __('Invalid e-mail address'), 'email');
	
		$method = $form->getElement('mail_method')->getSelected();
		if($method[0]=='smtp') {
			
			$form->addElement('text','mail_host', __('SMTP host address'));
			$form->addRule('mail_host', __('Field required'),'required');
            
            $form->addElement('select', 'mail_security', __('Security'),
                    array('' => __('None'), 'ssl' => 'SSL', 'ssl_ssc'=>'SSL (self signed certificate)', 'tls' => 'TLS', 'tls_ssc' => 'TLS (self signed certificate)'));
			
			$form->addElement('checkbox','mail_auth', __('SMTP authorization'),'','onChange="'.$form->get_submit_form_js(false).'"');
			
			$auth = $form->getElement('mail_auth')->getValue();
			if($auth) {
				$form->addElement('text','mail_user', __('Login'));					
				$form->addElement('password','mail_password', __('Password'));
			}
		}
		
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
		Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
		if (ModuleManager::is_installed('CRM_Contacts')>=0) {
			$me = CRM_ContactsCommon::get_my_record();
			$email = $me['email'];
			Base_ActionBarCommon::add('search', __('Test'), $this->create_callback_href($this->test_mail_config(...), array($email)), __('E-mail will be sent to %s to test the configuration', array('<b>'.$email.'</b>')));
		}
		
		if($form->getSubmitValue('submited') && $form->validate() && $form->process($this->submit_admin(...))) {
			Base_StatusBarCommon::message(__('Settings saved'));
		}
		$form->display();					
		
	}
	
	public function test_mail_config($email) {
		ob_start();
		// Short connect timeout: this is an interactive admin action, not a
		// background send - a host/port that's unreachable (wrong port,
		// blocked outbound, typo) should surface as "An error has occured"
		// within a few seconds, not leave the UI on "Loading..." for up to
		// PHPMailer's default 300s. See Base_MailCommon::send()'s $timeout doc.
		$ret = Base_MailCommon::send($email, __('E-mail configuration test'), __('If you are reading this, it means that your e-mail server configuration at %s is working properly.', array(get_epesi_url())), timeout: 10);
		$msg = ob_get_clean();
		if ($msg) print('<span class="important_notice">'.$msg.'</span>');
		if ($ret) {
			Base_StatusBarCommon::message(__('E-mail was sent successfully'));
		} else {
			$error = Base_MailCommon::get_last_error();
			// htmlspecialchars: $error can echo back server-controlled text
			// (e.g. a TLS certificate's CN, as in the mismatch this is
			// mainly here for) - Base_StatusBarCommon::message() doesn't
			// escape its $text, so this is the only guard against it.
			Base_StatusBarCommon::message($error ? __('An error has occured: %s', array(htmlspecialchars($error, ENT_QUOTES))) : __('An error has occured'), 'error');
		}
		return false;
	}
	
	/**
	 * For internal use only.
	 */
	public function submit_admin($data) {
		$method = $data['mail_method'];
		Variable::set('mail_method', $method);
		Variable::set('mail_from_addr', $data['mail_from_addr']);
		Variable::set('mail_from_name', $data['mail_from_name']);
		Variable::set('mail_use_replyto', $data['mail_use_replyto']);
		if($method=='smtp') {
			Variable::set('mail_host', $data['mail_host']);
			
			$auth = isset($data['mail_auth']) && $data['mail_auth'];
			Variable::set('mail_auth', $auth);
			if($auth) {
				Variable::set('mail_user', $data['mail_user']);
				Variable::set('mail_password', $data['mail_password']);
			}
            
            $security = $data['mail_security'] ?? '';
            Variable::set('mail_security', $security);
		}
		return true;
	}
}
?>
