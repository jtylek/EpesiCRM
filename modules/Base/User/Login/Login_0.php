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
	private $theme;

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
				print __('You have exceeded the number of allowed login attempts.').'<br>';
				print('<a href="'.get_epesi_url().'">'.__('Host banned. Click here to refresh.').'</a>');
				return;
			}
		}

		$this->theme = $this->pack_module('Base/Theme');

		//if logged
		$this->theme->assign('is_logged_in', Acl::is_user());
		$this->theme->assign('is_demo', DEMO_MODE);
		if (SUGGEST_DONATION) {
			$this->theme->assign('donation_note', __('If you find our software useful, please support us by making a %s.<br>Your funding will help to ensure continued development of this project.', array('<a href="http://epe.si/cost" target="_blank">'.__('donation').'</a>')));
		}
		if(Acl::is_user()) {
			if($this->get_unique_href_variable('logout')) {
			        Base_User_LoginCommon::logout();
				eval_js('document.location=\'index.php\';',false);
			} else {
				$this->theme->assign('logged_as', '<div class="logged_as">'.__('Logged as %s',array('</br><b class="green">'.Base_UserCommon::get_my_user_login().'</b>')).'</div>');
				$this->theme->assign('logout', '<div class="logout_css3_box"><a class="logout_icon" '.$this->create_unique_href(array('logout'=>1)).'>'.__('Logout').'<div class="logout_icon_img"></div></a></div>');
				$this->theme->display();
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
		if (isset($_REQUEST['password_recovered'])) {
			$this->theme->assign('message', __('An e-mail with a new password has been sent.').'<br><a href="'.get_epesi_url().'">'.__('Login').'</a>');
			$this->theme->display();
			return;
		}
		if($this->autologin()) return;

		//else just login form
		$form = $this->init_module('Libs/QuickForm',__('Logging in'));
		$form->addElement('header', 'login_header', __('Login'));
		
		if(DEMO_MODE) {
			global $demo_users;
			$form->addElement('select', 'username', __('Username'), $demo_users, array('id'=>'username', 'onChange'=>'this.form.elements["password"].value=this.options[this.selectedIndex].value;'));
			$form->addElement('hidden', 'password', key($demo_users));
		} else {
			$form->addElement('text', 'username', __('Username'),array('id'=>'username'));
			$form->addElement('password', 'password', __('Password'));
		}

		// Display warning about storing a cookie
		$warning=__('Keep this box unchecked if using a public computer');
		$form->addElement('static','warning',null,$warning);
		$form->addElement('checkbox', 'autologin', '',__('Remember me'));

		$form->addElement('static', 'recover_password', null, '<a '.$this->create_unique_href(array('mail_recover_pass'=>1)).'>'.__('Recover password').'</a>');
		$form->addElement('submit', 'submit_button', __('Login'), array('class'=>'submit'));

		// register and add a rule to check if a username and password is ok
		$form->registerRule('check_login', 'callback', 'submit_login', 'Base_User_LoginCommon');
		$form->addRule(array('username','password'), __('Login or password incorrect'), 'check_login');

		$form->addRule('username', __('Field required'), 'required');
		$form->addRule('password', __('Field required'), 'required');

		if($form->validate()) {
			$user = $form->exportValue('username');
			$autologin = $form->exportValue('autologin');

			Base_User_LoginCommon::set_logged($user);

			if($autologin)
				Base_User_LoginCommon::new_autologin_id();

			location(array());
		} else {
			$form->assign_theme('form', $this->theme);
			$this->theme->assign('mode', 'login');
			$this->theme->display();

			eval_js("focus_by_id('username')");
		}
	}

	public function recover_pass() {
		$form = $this->init_module('Libs/QuickForm',__('Processing request'));

		$form->addElement('header', null, __('Recover password'));
		$form->addElement('hidden', $this->create_unique_key('mail_recover_pass'), '1');
		$form->addElement('text', 'username', __('Username'));
		$form->addElement('text', 'mail', __('E-mail'));
		$ok_b = & HTML_QuickForm::createElement('submit', 'submit_button', __('OK'));
		$cancel_b = & HTML_QuickForm::createElement('button', 'cancel_button', __('Cancel'), $this->create_back_href());
		$form->addGroup(array($ok_b,$cancel_b),'buttons');

		// require a username
		$form->addRule('username', __('A username must be between 3 and 32 chars'), 'rangelength', array(3,32));
		// register and add a rule to check if a username and password is ok
		$form->registerRule('check_username', 'callback', 'check_username_mail_valid', 'Base_User_Login');
		$form->addRule('username', __('Username or e-mail invalid'), 'check_username', $form);
		$form->addRule('username', __('Field required'), 'required');
		//require valid e-mail address
		$form->addRule('mail', __('Field required'), 'required');
		$form->addRule('mail', __('Invalid e-mail address'), 'email');

		if($form->validate()) {
			if($form->process(array(&$this, 'submit_recover')))
				$this->theme->assign('message', __('Password reset instructions were sent.').'<br><a '.$this->create_back_href().'>'.__('Login').'</a>');
		} else {
			$this->theme->assign('mode', 'recover_pass');
			$form->assign_theme('form', $this->theme);
			eval_js("focus_by_id('username')");
		}

		$this->theme->display();
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

		$user_id = Base_UserCommon::get_user_id($username);
		DB::Execute('DELETE FROM user_reset_pass WHERE created_on<%T',array(time()-3600*2));
		
		if($user_id===false) {
			print('No such user!');
			return false;
		}
		$hash = md5($user_id.''.time());
		DB::Execute('INSERT INTO user_reset_pass(user_login_id,hash_id,created_on) VALUES (%d,%s,%T)',array($user_id, $hash,time()));
		
		$subject = __('Password recovery');
		$message = __('A password recovery for the account with the e-mail address %s has been requested.',array($mail))."\n\n".
				   __('If you want to reset your password, visit the following URL:')."\n".
				   get_epesi_url().'/modules/Base/User/Login/reset_pass.php?hash='.$hash."\n".
				   __('or just ignore this message and your login and password will remain unchanged.')."\n\n".
				   __('If you did not use the Password Recovery form, inform your administrator about a potential unauthorized attempt to login using your credentials.')."\n\n".
				   __('This e-mail was generated automatically and you do not need to respond to it.');
		$sendMail = Base_MailCommon::send_critical($mail, $subject, $message);

		return true;
	}

}
?>
