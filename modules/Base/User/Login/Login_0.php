<?php
/**
 * Login class.
 *
 * This class provides for basic login functionality, saves passwords to database and enables password recvery.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage user-login
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_User_Login extends Module {

	public function construct() {
	}

	private function autologin() {
	        if(Base_User_LoginCommon::autologin()) {
	            location(array());
	            return true;
	        }
		return false;
	}

	public function body() {
		//check bans
		$t = Variable::get('host_ban_time');
		if($t>0) {
			$fails = DB::GetOne('SELECT count(*) FROM user_login_ban WHERE failed_on>%d AND from_addr=%s',array(time()-$t,$_SERVER['REMOTE_ADDR']));
			if($fails>=3) {
				print $this->t('You have exceeded the number of allowed login attempts.<BR>');
				print('<a href="'.get_epesi_url().'">'.$this->t('Host banned. Click here to refresh.').'</a>');
				return;
			}
		}

		$theme =  & $this->pack_module('Base/Theme');

		//if logged
		$theme->assign('is_logged_in', Acl::is_user());
		$theme->assign('is_demo', DEMO_MODE);
		if(Acl::is_user()) {
			if($this->get_unique_href_variable('logout')) {
			        Base_User_LoginCommon::logout();
				eval_js('document.location=\'index.php\';',false);
			} else {
				$theme->assign('logged_as', '<div class="logged_as">'.$this->t('Logged as %s',array('</br><b class="green">'.Base_UserCommon::get_my_user_login().'</b>')).'</div>');
				$theme->assign('logout', '<div class="logout_css3_box"><a class="logout_icon" '.$this->create_unique_href(array('logout'=>1)).'>'.$this->t('Logout').'<div class="logout_icon_img"></div></a></div>');
				$theme->display();
			}
			return;
		}

		if($this->is_back())
		    $this->unset_module_variable('mail_recover_pass');

		//if recover pass
		if($this->get_module_variable_or_unique_href_variable('mail_recover_pass')=='1') {
			$this->recover_pass();
			return;
		}
		if($this->autologin()) return;

		//else just login form
		$form = & $this->init_module('Libs/QuickForm',$this->t('Logging in'));
		$form->addElement('header', 'login_header', $this->t('Login'));
		
		if(DEMO_MODE) {
    		$form->addElement('select', 'username', $this->t('Username'),array('admin'=>'admin','admin_pl'=>'admin_pl','admin_ru'=>'admin_ru'), array('id'=>'username', 'onChange'=>'this.form.elements["password"].value=this.options[this.selectedIndex].value;'));
	    	$form->addElement('hidden', 'password', 'admin');		
		} else {
    		$form->addElement('text', 'username', $this->t('Username'),array('id'=>'username'));
	    	$form->addElement('password', 'password', $this->t('Password'));
		}

		// Display warning about storing a cookie
		$warning=$this->t('Keep this box unchecked if using a  public computer');
		$form->addElement('static','warning',null,$warning);
		$form->addElement('checkbox', 'autologin', '',$this->t('Remember me'));

		$form->addElement('static', 'recover_password', null, '<a '.$this->create_unique_href(array('mail_recover_pass'=>1)).'>'.$this->t('Recover password').'</a>');
		$form->addElement('submit', 'submit_button', $this->t('Login'), array('class'=>'submit'));

		// register and add a rule to check if a username and password is ok
		$form->registerRule('check_login', 'callback', 'submit_login', 'Base_User_LoginCommon');
		$form->addRule(array('username','password'), $this->t('Login or password incorrect'), 'check_login');

		$form->addRule('username', $this->t('Field required'), 'required');
		$form->addRule('password', $this->t('Field required'), 'required');

		if($form->validate()) {
			$user = $form->exportValue('username');
			$autologin = $form->exportValue('autologin');

			Base_User_LoginCommon::set_logged($user);

			if($autologin)
				Base_User_LoginCommon::new_autologin_id();

			location(array());
		} else {
			$form->assign_theme('form', $theme);
			if (SUGGEST_DONATION) {
				$theme->assign('donation_note', $this->t('If you find our software useful, please support us by making a %s.<br>Your funding will help to ensure continued development of this project.', array('<a href="http://www.epesibim.com/cost" target="_blank">'.$this->t('donation').'</a>')));
			}
			$theme->display();

			eval_js("focus_by_id('username')");
		}
	}

	public function recover_pass() {
		$form = & $this->init_module('Libs/QuickForm',$this->t('Processing request'));

		$form->addElement('header', null, $this->t('Recover password'));
		$form->addElement('hidden', $this->create_unique_key('mail_recover_pass'), '1');
		$form->addElement('text', 'username', $this->t('Username'));
		$form->addElement('text', 'mail', $this->t('e-mail'));
		$ok_b = & HTML_QuickForm::createElement('submit', 'submit_button', $this->t('OK'));
		$cancel_b = & HTML_QuickForm::createElement('button', 'cancel_button', $this->t('Cancel'), $this->create_back_href());
		$form->addGroup(array($ok_b,$cancel_b),'buttons');

		// require a username
		$form->addRule('username', $this->t('A username must be between 3 and 32 chars'), 'rangelength', array(3,32));
		// register and add a rule to check if a username and password is ok
		$form->registerRule('check_username', 'callback', 'check_username_mail_valid', 'Base_User_Login');
		$form->addRule('username', $this->t('Username or e-mail invalid'), 'check_username', $form);
		$form->addRule('username', $this->t('Field required'), 'required');
		//require valid e-mail address
		$form->addRule('mail', $this->t('Field required'), 'required');
		$form->addRule('mail', $this->t('This isn\'t valid e-mail address'), 'email');

		if($form->validate()) {
			if($form->process(array(&$this, 'submit_recover')))
				print($this->t('Mail with password sent.').' <a '.$this->create_back_href().'>'.$this->t('Login').'</a>');
		} else $form->display();
	}

	public static function check_username_mail_valid($username, $form) {
		$mail = $form->getElement('mail')->getValue();
		$ret = DB::Execute('SELECT null FROM user_password p JOIN user_login u ON u.id=p.user_login_id WHERE u.login=%s AND p.mail=%s AND u.active=1',array($username, $mail));
		return $ret->FetchRow()!==false;
	}

	public function submit_recover($data) {
		$mail = $data['mail'];
		$username = $data['username'];

 		if(DEMO_MODE && $username=='admin') {
 			print('In demo you cannot recover \'admin\' user password. If you want to login please type \'admin\' as password.'); 
			return false;
 		}

		$pass = generate_password();

		$user_id = Base_UserCommon::get_user_id($username);
		if($user_id===false) {
			print('No such user!');
			return false;
		}

		if(!DB::Execute('UPDATE user_password SET password=%s WHERE user_login_id=%d', array(md5($pass), $user_id))) {
			print($this->t('Unable to update password for user %s.',array($username)));
			return false;
		}

		if(!Base_User_LoginCommon::send_mail_with_password($username, $pass, $mail)) {
			print($this->t('Unable to send e-mail with password. Mail module configuration invalid. Please contact system administrator.'));
			return false;
		}

		return true;
	}
}
?>
