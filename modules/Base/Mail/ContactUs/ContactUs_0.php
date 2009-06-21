<?php
/**
 * Mail_ContactUs class. 
 * This class provides functionality for sending mail.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage mail-contactus
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Mail_ContactUs extends Module {

	public function body() {
		$form = & $this->init_module('Libs/QuickForm','Sending message');
		$form->addElement('header', null, $this->t('Support'));
		$form->addElement('html', '<tr><td colspan=2>'.$this->t('You can write a message to administrator here.').'</td><tr>');
		if(!Acl::is_user()) {
    		    $form->addElement('text','mail', $this->t('E-mail address:'));
		    $form->addRule('mail', $this->t('Field required'), 'required');
    	    	    $form->addRule('mail', $this->t('Not valid e-mail address'), 'email',true);
		}
		
		$body = HTML_QuickForm::createElement('textarea', 'body',null,array('id'=>'contact_us'));
		$this->js('focus_by_id(\'contact_us\')');
		
		$body->setCols(50);
		$body->setRows(15);
		$form->addElement($body);
		
		$form->addElement('submit','submit_button',$this->ht('Send'));
		
		if($form->validate()) {
			if($form->process(array(&$this, 'submit_body')))
				print($this->t('Message sent to administrator.'));
		} else
			$form->display();
	}
	
	public function submit_body($data) {
		$to = Variable::get('mail_from_addr');
		if(Acl::is_user())
		    $mail = DB::GetOne('SELECT mail FROM user_password WHERE user_login_id=%d',Acl::get_user());
		else
		    $mail = $data['mail'];
		if(!Base_MailCommon::send($to, Base_UserCommon::get_my_user_login().' comment ('.get_epesi_url().')', $data['body'],$mail,Base_UserCommon::get_my_user_login())) {
			print($this->t('Unable to send message. Invalid configuration.'));
			return false;
		}
		Base_StatusBarCommon::message('Message sent');
		return true;
	}

	public function caption() {
		return "Support";
	}
}
?>
