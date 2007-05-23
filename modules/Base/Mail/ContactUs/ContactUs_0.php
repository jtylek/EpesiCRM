<?php
/**
 * Mail_ContactUs class. 
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides functionality for sending mail.
 * @package tcms-base-extra
 * @subpackage mail-contactus
 */
class Base_Mail_ContactUs extends Module {

	public function body($arg) {
		$this->lang = $this->pack_module('Base/Lang');
		
		$form = & $this->init_module('Libs/QuickForm','Sending message');
		$form->addElement('header', null, $this->lang->t('Contact us'));
		if(!Acl::is_user()) {
    		    $form->addElement('text','mail', $this->lang->t('E-mail address:'));
		    $form->addRule('mail', $this->lang->t('Field required'), 'required');
    	    	    $form->addRule('mail', $this->lang->t('Not valid e-mail address'), 'email');
		}
		
		$body = HTML_QuickForm::createElement('textarea', 'body',null,array('id'=>'contact_us'));
		eval_js('focus_by_id(\'contact_us\')');
		
		$body->setCols(50);
		$body->setRows(15);
		$form->addElement($body);
		
		$form->addElement('submit','submit_button',$this->lang->ht('Send'));
		
		if($form->validate()) {
			if($form->process(array(&$this, 'submit_body')))
				print($this->lang->t('Message sent to administrator.'));
		} else
			$form->display();
	}
	
	public function submit_body($data) {
		$to = Variable::get('mail_from_addr');
		if(Acl::is_user())
		    $mail = DB::GetOne('SELECT mail FROM user_password WHERE user_login_id=%d',Base_UserCommon::get_my_user_id());
		else
		    $mail = $data['mail'];
		if(!Base_MailCommon::send($to, Acl::get_user().' comment (http'.(($_SERVER['HTTPS'])?'s':'').'://'. $_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].')', $data['body'],$mail,Acl::get_user())) {
			print($this->lang->t('Unable to send message. Invalid configuration.'));
			return false;
		}
		Base_StatusBarCommon::message('Message sent');
		return true;
	}
}
?>
