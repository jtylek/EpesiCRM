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
	public static function check_login($username, $pass) {
		$hash = DB::GetOne('SELECT p.password FROM user_login u JOIN user_password p ON u.id=p.user_login_id WHERE u.login=%s AND u.active=1', array($username));
		if(!$hash) return false;
		if(strlen($hash)==32) //md5
		    return md5($pass)==$hash;
		return password_verify($pass,$hash);
	}
	
	public static function submit_login($x) {
		$username = $x[0];
		$pass = $x[1];
        $ret = Base_User_LoginCommon::check_login($username, $pass);
        if (!$ret) {
            $limit_exceeded = self::log_failed_login($username);
            if ($limit_exceeded)
                location(array());
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
		$pass_hash = function_exists('password_hash')?password_hash($pass,PASSWORD_DEFAULT):md5($pass);
		$ret = DB::Execute('INSERT INTO user_password(user_login_id,password,mail) VALUES(%d,%s, %s)', array($user_id, $pass_hash, $mail));
		
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
		
		$pass_hash = function_exists('password_hash')?password_hash($pass,PASSWORD_DEFAULT):md5($pass);
		if($pass!='' && DB::Execute('UPDATE user_password SET password=%s WHERE user_login_id=%d', array($pass_hash, $id))===false)
			return false;
		
		return true;
	}
	
	public static function get_mail($id) {
		return DB::GetOne('SELECT mail FROM user_password WHERE user_login_id=%d',array($id));
	}
    
    public static function is_banned($login = null, $current_time = null) {
        $time_seconds = Variable::get('host_ban_time');
        $tries = Variable::get('host_ban_nr_of_tries', false);
        if ($tries === '') {// default value when there is no such variable
            $tries = 3;
            Variable::set('host_ban_nr_of_tries', $tries);
        }
        // Some kind of hack. We are using md5 hash of user login and IP address
        // like address to match host + username. We can because md5 is 32 char.
        // long as from_addr field
        $ip = get_client_ip_address();
        $param = $login ? md5($login . $ip) : $ip;

		// Hidden feature to allow login only from desired IP
		// TODO: add GUI
		// Example:
		// $allowed_ip = array('user_1' => array('1.2.3.4', '5.6.7.8'), 'user_2' => array('2.3.4.5', '3.4.5.6'));
		// Variable::set('allowed_ip_login', $allowed_ip);
		$allowed_ip = Variable::get('allowed_ip_login', false);
		if ($allowed_ip) {
			// use '' key to match all users
			if (isset($allowed_ip[''])) $login = '';
			if (isset($allowed_ip[$login])) {
				if (!in_array($ip, $allowed_ip[$login])) return true; // is banned
			}
		}

        // allow to inject time parameter
        if (!$current_time) $current_time = time();
		if($tries > 0 && $time_seconds > 0) {
			$fails = DB::GetOne('SELECT count(*) FROM user_login_ban WHERE failed_on>%d AND from_addr=%s',array($current_time-$time_seconds,$param));
			if($fails>=$tries) {
                return true;
			}
		}
        return false;
    }
    
    public static function rule_login_banned($login = null) {
        $ban_by_login = Variable::get('host_ban_by_login', false);
        if (!$ban_by_login)
            return true;
        return !self::is_banned($login);
    }
    
    public static function log_failed_login($login) {
        $ban_seconds = Variable::get('host_ban_time');
        $tries = Variable::get('host_ban_nr_of_tries');
        if ($ban_seconds > 0 && $tries > 0) {
            // we have to option to ban - by ip or by login. In both cases
            // from_addr column is used to store ip or login name
            $ban_by_login = Variable::get('host_ban_by_login', false);
			$ip = get_client_ip_address();
            $param = $ban_by_login ? md5($login . $ip) : $ip;
            $current_time = time();
            DB::Execute('DELETE FROM user_login_ban WHERE failed_on<=%d', array($current_time - $ban_seconds));
            DB::Execute('INSERT INTO user_login_ban(failed_on,from_addr) VALUES(%d,%s)', array($current_time, $param));
            return self::is_banned($ban_by_login ? $login : null, $current_time);
        }
        return null;
    }

    public static function logout()
    {
        if (isset($_COOKIE['autologin_id'])) {
            $arr = explode(' ', $_COOKIE['autologin_id']);
            if (count($arr) == 2) {
                list($user, $autologin_id) = $arr;
                if ($user == Base_UserCommon::get_my_user_login())
                    DB::Execute('DELETE FROM user_autologin WHERE autologin_id=%s AND user_login_id=%d', array($autologin_id, Acl::get_user()));
            }
        }
        Acl::set_user(null, true);
        return false;
    }

	public static function clean_old_autologins()
	{
		DB::Execute('DELETE FROM user_autologin WHERE last_log<%T', array(strtotime('-30 days')));
	}
	
	public static function new_autologin_id($old_autologin_id = null)
	{
		$uid = Acl::get_user();
		$user = Base_UserCommon::get_my_user_login();
		$autologin_id = md5(mt_rand().md5($user.$uid).mt_rand());
		$ssl = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS'])!== "off") ? 1 : 0;
		setcookie('autologin_id',$user.' '.$autologin_id,time()+60*60*24*30, EPESI_DIR, '', $ssl);
		$ip = get_client_ip_address();
		if ($old_autologin_id) {
			DB::Execute('DELETE FROM user_autologin WHERE user_login_id=%d AND autologin_id=%s', array($uid, $old_autologin_id));
		}
		DB::Execute('INSERT INTO user_autologin(user_login_id,autologin_id,description,last_log) VALUES(%d,%s,%s,%T)', array($uid, $autologin_id, $ip, time()));
		self::clean_old_autologins();
	}

    public static function is_autologin_forbidden()
    {
        return true == Variable::get('forbid_autologin', false);
    }

	public static function autologin() {
        if (self::is_autologin_forbidden()) return false;
		if(isset($_COOKIE['autologin_id'])) {
			$arr = explode(' ',$_COOKIE['autologin_id']);
			if(count($arr)==2) {
				list($user,$autologin_id) = $arr;
				$ret = DB::GetOne('SELECT 1 FROM user_login u JOIN user_autologin p ON u.id=p.user_login_id WHERE u.login=%s AND u.active=1 AND p.autologin_id=%s', array($user,$autologin_id));
				if($ret) {
					Base_User_LoginCommon::set_logged($user);
					self::new_autologin_id($autologin_id);
					return true;
				}
			}
		}
		return false;
	}

}

if(!Acl::is_user())
	Base_User_LoginCommon::autologin();

?>
