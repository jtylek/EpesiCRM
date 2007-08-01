<?php
/**
 * Login class.
 * 
 * This class provides for basic login functionality, saves passwords to database and enables password recvery.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 * @subpackage user-login
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_User_LoginCommon extends Module {
	/**
	 * Check if username and password is valid login.
	 * 
	 * @param string
	 * @param string
	 * @return bool
	 */
	public static function check_login($username, $pass) {
		$ret = DB::Execute('SELECT null FROM user_login u JOIN user_password p ON u.id=p.user_login_id WHERE u.login=%s AND p.password=%s AND u.active=1', array($username, md5($pass)));
		if(!$ret->EOF) //user exists, login ok
			return true;
		else
			return false;
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
		return Base_UserCommon::get_user_id($username)===false;
	}
	
	/**
	 * Send mail with login and password to address....
	 * 
	 * @param string username
	 * @param string password
	 * @param string destination mail address
	 */
	private static function send_mail_with_password($username, $pass, $mail) {
		$url = 'http'.(($_SERVER['HTTPS'])?'s':'').'://'. $_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
		$subject = sprintf(Base_LangCommon::ts('Login','Your account at %s'),$url);
		$body = sprintf(Base_LangCommon::ts('Login', "This e-mail is to inform you that a user account was setup for you at: %s
Your login name is: %s
Your password is: %s

For security reasons it is recommened that you login immediately and change your password.

This e-mail was automatically generated and you do not need to respond to it."),$url,$username,$pass);
		
		return Base_MailCommon::send($mail, $subject, $body);
	}
	
	/**
	 * Add user and send password by mail.
	 * 
	 * @param string username
	 * @param string mail address
	 * @param string password
 	 * @return bool everything is ok? 
	 */
	public static function add_user($username, $mail, $pass = null) {
		
		if($pass==null)
			$pass = generate_password();
		
		if(!Base_UserCommon::add_user($username)) {
			print(Base_LangCommon::ts('Base/User/Login','Account creation failed.<br> Unable to add user to database.<br>'));
			return false;	
		}
		$user_id = Base_UserCommon::get_user_id($username);
		if($user_id===false) {
			print(Base_LangCommon::ts('Base/User/Login','Account creation failed.<br> Unable to get id of added user.<br>'));
			return false;
		}
		$ret = DB::Execute('INSERT INTO user_password VALUES(%d,%s, %s)', array($user_id, md5($pass), $mail));
		
		if(!self::send_mail_with_password($username, $pass, $mail)) {
			print(Base_LangCommon::ts('Base/User/Login','Warning: Unable to send e-mail with password. Check Mail module configuration.'));
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
	
}
?>
