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

class Base_User_LoginCommon extends ModuleCommon {
	/**
	 * Check if username and password is valid login.
	 * 
	 * @param string
	 * @param string
	 * @return bool
	 */
	public static function check_login($username, $pass, $md5=true) {
		if ($md5) $pass = md5($pass);
		$ret = DB::Execute('SELECT null FROM user_login u JOIN user_password p ON u.id=p.user_login_id WHERE u.login=%s AND p.password=%s AND u.active=1', array($username, $pass));
		if(!$ret->EOF) //user exists, login ok
			return true;
		else
			return false;
	}
	
	public static function submit_login($x) {
		$username = $x[0];
		$pass = $x[1];
		$ret = Base_User_LoginCommon::check_login($username, $pass);
		if(!$ret) {
			$t = Variable::get('host_ban_time');
			if($t>0) {
				DB::Execute('DELETE FROM user_login_ban WHERE failed_on<=%d',array(time()-$t));
				DB::Execute('INSERT INTO user_login_ban(failed_on,from_addr) VALUES(%d,%s)',array(time(),$_SERVER['REMOTE_ADDR']));
				$fails = DB::GetOne('SELECT count(*) FROM user_login_ban WHERE failed_on>%d AND from_addr=%s',array(time()-$t,$_SERVER['REMOTE_ADDR']));
				if($fails>=3)
					location(array());
			}
		}
		return $ret;
	}

	public static function set_logged($user) {
		$uid = Base_UserCommon::get_user_id($user);
		Acl::set_user($uid, true); //tag who is logged
	}

	
	public static function check_username_mail_valid($username, $form) {
		$mail = $form->getElement('mail')->getValue();
		$ret = DB::Execute('SELECT null FROM user_password p JOIN user_login u ON u.id=p.user_login_id WHERE u.login=%s AND p.mail=%s AND u.active=1',array($username, $mail));
		return $ret->FetchRow()!==false;
	}
	
	/**
	 * Checks if username is not assigned to a user.
	 * 
	 * @param string username
	 * @return bool false if there is a user in the system with given name, true otherwise
	 */
	public static function check_username_free($username) {
	    if(is_array($username) && count($username)==2) {
	        $uid = $username[1];
	        $username = $username[0];
	    } else $uid=-1;
		return Base_UserCommon::get_user_id($username)===false || Base_UserCommon::get_user_id($username)===null || Base_UserCommon::get_user_id($username)==$uid;
	}
	
	/**
	 * Send mail with login and password to address....
	 * 
	 * @param string username
	 * @param string password
	 * @param string destination mail address
	 */
	public static function send_mail_with_password($username, $pass, $mail, $recovery=false) {
		$url = get_epesi_url();
		$subject = __('Your account at %s',array($url));
		$header = Variable::get('add_user_email_header','');
		$body = ($header?$header."\n\n":'');
		if ($recovery)
			$body .= __( 'This e-mail is to inform you that your password at %s has been reset.', array($url));
		else
			$body .= __( 'This e-mail is to inform you that a user account was setup for you at: %s.', array($url));
		$body .= "\n".
				__('Your username is: %s', array($username))."\n".
				__('Your password is: %s', array($pass))."\n\n".
				__('For security reasons it is recommened that you log in immediately and change your password.')."\n\n".
				__('This e-mail was generated automatically and you do not need to respond to it.');
		
		return Base_MailCommon::send_critical($mail, $subject, $body);
	}
	
	/**
	 * Add user and send password by mail.
	 * 
	 * @param string username
	 * @param string mail address
	 * @param string password
 	 * @return bool everything is ok? 
	 */
	public static function add_user($username, $mail, $pass = null, $send_mail=true) {
		
		if($pass==null)
			$pass = generate_password();
		
		if(!Base_UserCommon::add_user($username)) {
			print(__('Account creation failed.').'<br>'.__('Unable to add user to database.').'<br>');
			return false;	
		}
		$user_id = Base_UserCommon::get_user_id($username);
		if($user_id===false) {
			print(__('Account creation failed.').'<br>'.__('Unable to get id of added user.').'<br>');
			return false;
		}
		$ret = DB::Execute('INSERT INTO user_password(user_login_id,password,mail) VALUES(%d,%s, %s)', array($user_id, md5($pass), $mail));
		
		if($send_mail) {
			if(!self::send_mail_with_password($username, $pass, $mail)) {
				print(__('Warning: Unable to send e-mail with password. Check Mail module configuration or contact system administrator for password recovery.'));
			}
		}

		return ($ret!==false);
	}
	
	/**
	 * Change user mail and password.
	 * 
	 * @param integer user id (get from User module)
	 * @param string new mail address
	 * @param string password, if empty, don't change pass
	 */
	public static function change_user_preferences($id, $mail, $pass='') {
		
		if(DB::Execute('UPDATE user_password SET mail=%s WHERE user_login_id=%d', array($mail, $id)) === false)
			return false;
		
		if($pass!='' && DB::Execute('UPDATE user_password SET password=%s WHERE user_login_id=%d', array(md5($pass), $id))===false)
			return false;
		
		return true;
	}
	
	public static function get_mail($id) {
		return DB::GetOne('SELECT mail FROM user_password WHERE user_login_id=%d',array($id));
	}
	
	////////////////////////////////////////////////////
	// mobile devices
	
	public static function mobile_menu() {
		if(Acl::is_user())
			return array(__('Logout')=>array('func'=>'logout','weight'=>100));
		return array(__('Login')=>'mobile_login');
	}
	
	public static function logout() {
		if(isset($_COOKIE['autologin_id'])) {
			$arr = explode(' ',$_COOKIE['autologin_id']);
			if(count($arr)==2) {
				list($user,$autologin_id) = $arr;
				if($user==Base_UserCommon::get_my_user_login())
                        		DB::Execute('DELETE FROM user_autologin WHERE autologin_id=%s AND user_login_id=%d',array($autologin_id,Acl::get_user()));
                        }
                }
		Acl::set_user(null, true);
		return false;
	}
	
	public static function new_autologin_id() {
		$uid = Acl::get_user();
		$user = Base_UserCommon::get_my_user_login();
		$autologin_id = md5(mt_rand().md5($user.$uid).mt_rand());
		setcookie('autologin_id',$user.' '.$autologin_id,time()+60*60*24*30);
		DB::Execute('INSERT INTO user_autologin(user_login_id,autologin_id,description,last_log) VALUES(%d,%s,%s,%T)',array($uid,$autologin_id,$_SERVER['REMOTE_ADDR'],time()));
	}

	public static function autologin() {
		if(isset($_COOKIE['autologin_id'])) {
			$arr = explode(' ',$_COOKIE['autologin_id']);
			if(count($arr)==2) {
				list($user,$autologin_id) = $arr;
				$ret = DB::GetOne('SELECT 1 FROM user_login u JOIN user_autologin p ON u.id=p.user_login_id WHERE u.login=%s AND u.active=1 AND p.autologin_id=%s', array($user,$autologin_id));
				if($ret) {
					Base_User_LoginCommon::set_logged($user);
                        		setcookie('autologin_id',$user.' '.$autologin_id,time()+60*60*24*30);
                        		DB::Execute('UPDATE user_autologin SET last_log=%T WHERE user_login_id=%d AND autologin_id=%s',array(time(),Acl::get_user(),$autologin_id));
                        		return true;
				}
			}
		}
		return false;
	}

	public static function mobile_login() {
		$t = Variable::get('host_ban_time');
		if($t>0) {
			$fails = DB::GetOne('SELECT count(*) FROM user_login_ban WHERE failed_on>%d AND from_addr=%s',array(time()-$t,$_SERVER['REMOTE_ADDR']));
			if($fails>=3) {
				print(__('You have exceeded the number of allowed login attempts.').'<br>');
				print('<a href="'.get_epesi_url().'">'.__('Host banned. Click here to refresh.').'</a>');
				return;
			}
		}
		

		$qf = new HTML_QuickForm('login', 'post','mobile.php?'.http_build_query($_GET));

		$qf->addElement('text', 'username', __('Login'));
		$qf->addElement('password', 'password', __('Password'));
		$qf->addElement('submit', 'submit_button', __('Login'));

		$qf->registerRule('check_login', 'callback', 'submit_login', 'Base_User_LoginCommon');
		$qf->addRule(array('username','password'), __('Login or password incorrect'), 'check_login');
		$qf->addRule('username', __('Field required'), 'required');
		$qf->addRule('password', __('Field required'), 'required');


		if($qf->validate()) {
			self::set_logged($qf->exportValue('username'));
			self::new_autologin_id();
			return false;
		}
		$qf->display();
	}
}

if(!Acl::is_user())
	Base_User_LoginCommon::autologin();

?>
